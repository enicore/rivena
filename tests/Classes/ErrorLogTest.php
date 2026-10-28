<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\ErrorLog;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;
use Tests\Support\SmtpServer;
use Tests\Support\TestConfig;

#[CoversClass(ErrorLog::class)]
class ErrorLogTest extends TestCase
{
    private string $sent;
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        // the file with the time of the last email, for the tests' app key
        $this->sent = sys_get_temp_dir() . '/rivena_error_mail_' . md5('key');
        is_file($this->sent) && unlink($this->sent);
        // PHPUnit sets its own error_log after setUp(), so the tests call startLog()
        $this->log = tempnam(sys_get_temp_dir(), 'rivena');
        Database::create();
    }

    // the table's rows: level, message, path, count
    private function rows(): array
    {
        return DB::table('error_log')->orderBy('id')->get()
            ->map(fn($row) => [$row->level, $row->message, $row->path, $row->count])->all();
    }

    protected function tearDown(): void
    {
        is_file($this->sent) && unlink($this->sent);
        isset($this->previousLog) && ini_set('error_log', $this->previousLog);
        unlink($this->log);
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
        TestConfig::load();
    }

    private function startLog(): void
    {
        $this->previousLog = (string)ini_set('error_log', $this->log);
    }

    // runs the PHP code in a new process, with the autoloader and the config
    private function runPhp(string $code, string $options = ''): void
    {
        $config = var_export(['error_notification_email' => 'admin@example.com', 'smtp_host' => '127.0.0.1',
            'smtp_port' => Config::get('smtp_port'), 'mail_from' => 'site@example.com'], true);
        exec(escapeshellarg(PHP_BINARY) . " -d display_errors=0 -d log_errors=0 -d error_log=/dev/null $options -r " .
            escapeshellarg('require "' . __DIR__ . "/../../vendor/autoload.php\"; " .
                "Tests\\Support\\TestConfig::load($config); $code"));
    }

    /**
     * The errors are added and emailed, with the path without its query and tokens.
     */
    public function testAddError(): void
    {
        $smtp = new SmtpServer(['error_notification_email' => 'admin@example.com']);
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/usr/reset-password/' . str_repeat('a1', 32) . '?key=secret';

        ErrorLog::add('error', 'Boom in /app/src/x.php:5');
        $this->assertSame([['error', 'Boom in /app/src/x.php:5', '/usr/reset-password/...', 1]], $this->rows());
        $email = $smtp->emails()[0];
        $this->assertStringContainsString("To: admin@example.com\r\n", $email);
        $this->assertStringContainsString("Subject: Error on Site\r\n", $email);
        $this->assertStringContainsString("\r\n\r\nBoom in /app/src/x.php:5\r\n\r\nPath: /usr/reset-password/...\r\n" .
            'Time: ' . date('Y-m-d'), $email);
        $this->assertStringContainsString("The errors of the next hour aren't emailed, see the Error Log page.",
            $email);

        // one an hour
        ErrorLog::add('error', 'Again');
        $this->assertCount(1, $smtp->emails());
        touch($this->sent, time() - 3601);
        ErrorLog::add('error', 'Later');
        $this->assertCount(2, $smtp->emails());
        $this->assertCount(3, $this->rows());
    }

    /**
     * In development, the errors are added but not emailed.
     */
    public function testNoEmailInDev(): void
    {
        $smtp = new SmtpServer(['error_notification_email' => 'admin@example.com', 'app_env' => 'dev']);
        ErrorLog::add('error', 'Boom');
        $this->assertCount(1, $this->rows());
        $this->assertSame([], $smtp->emails());
    }

    /**
     * A message seen again counts, with the last path and time; the ones not seen for 30 days go.
     */
    public function testRepeated(): void
    {
        $_SERVER['REQUEST_URI'] = '/one';
        ErrorLog::add('warning', 'Careful');
        DB::table('error_log')->update(['first_at' => 100, 'last_at' => time() - 10]);
        $_SERVER['REQUEST_URI'] = '/two';
        ErrorLog::add('warning', 'Careful');
        ErrorLog::add('error', 'Careful');

        $this->assertSame([['warning', 'Careful', '/two', 2], ['error', 'Careful', '/two', 1]], $this->rows());
        $this->assertSame(100, DB::table('error_log')->value('first_at'));
        $this->assertEqualsWithDelta(time(), DB::table('error_log')->value('last_at'), 1);

        DB::table('error_log')->where('level', 'warning')->update(['last_at' => time() - 30 * 86400 - 1]);
        ErrorLog::add('error', 'New');
        $this->assertSame([['error', 'Careful', '/two', 1], ['error', 'New', '/two', 1]], $this->rows());
    }

    /**
     * Invalid UTF-8 and messages longer than the column are added too (replaced and cut).
     */
    public function testInvalidAndLongMessages(): void
    {
        ErrorLog::add('error', "Bad \xff bytes");
        ErrorLog::add('error', str_repeat('é', 40000));

        $messages = DB::table('error_log')->orderBy('id')->pluck('message')->all();
        $this->assertSame('Bad ? bytes', $messages[0]);
        $this->assertSame(str_repeat('é', 32767), $messages[1]);
    }

    /**
     * Without the table (or the database), the error is logged by PHP and emailed.
     */
    public function testWithoutDatabase(): void
    {
        $smtp = new SmtpServer(['error_notification_email' => 'admin@example.com']);
        $this->startLog();
        DB::statement('DROP TABLE error_log');

        ErrorLog::add('error', 'Boom');
        $this->assertStringContainsString('ErrorLog: ', file_get_contents($this->log));
        $this->assertCount(1, $smtp->emails());
    }

    public function testWithoutEmail(): void
    {
        $smtp = new SmtpServer();

        ErrorLog::add('error', 'Boom');
        $this->assertSame([], $smtp->emails());
        $this->assertFileDoesNotExist($this->sent);
    }

    /**
     * A failure to send is logged, not thrown.
     */
    public function testSendFailure(): void
    {
        TestConfig::load(['error_notification_email' => 'admin@example.com', 'smtp_host' => '127.0.0.1',
            'smtp_port' => 1, 'mail_from' => 'site@example.com']);
        $this->startLog();

        ErrorLog::add('error', 'Boom');
        $this->assertStringContainsString('ErrorLog: SMTP Error', file_get_contents($this->log));
    }

    /**
     * The warnings are added, not emailed, and logged by PHP as before; the ones hidden with @ aren't added.
     */
    public function testWarnings(): void
    {
        $smtp = new SmtpServer(['error_notification_email' => 'admin@example.com']);
        $this->startLog();
        // PHPUnit reports only some errors
        $reporting = error_reporting(E_ALL);
        $display = ini_set('display_errors', '0');
        ErrorLog::register();

        @trigger_error('Hidden', E_USER_WARNING);
        $line = __LINE__ + 1;
        trigger_error('Careful', E_USER_WARNING);
        restore_error_handler();
        ini_set('display_errors', $display);
        error_reporting($reporting);

        $this->assertSame([['warning', 'Careful in ' . __FILE__ . ":$line", '', 1]], $this->rows());
        $this->assertSame([], $smtp->emails());
        $this->assertStringContainsString('PHP Warning:  Careful', file_get_contents($this->log));
    }

    /**
     * Without the file of the last email's time (an unwritable temp directory), there's no limit, so no email.
     */
    public function testWithoutLimit(): void
    {
        $smtp = new SmtpServer();

        $this->runPhp('Enicore\Rivena\Classes\ErrorLog::add("error", "Boom");', '-d sys_temp_dir=/nonexistent');
        $this->assertSame([], $smtp->emails());
        $this->runPhp('Enicore\Rivena\Classes\ErrorLog::add("error", "Boom");');
        $this->assertCount(1, $smtp->emails());
    }

    public function testFatalError(): void
    {
        $smtp = new SmtpServer();

        $this->runPhp('Enicore\Rivena\Classes\ErrorLog::register(); undefined_function();');
        $this->assertStringContainsString('Uncaught Error: Call to undefined function undefined_function()',
            $smtp->emails()[0]);
    }
}

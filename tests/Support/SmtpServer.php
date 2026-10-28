<?php
namespace Tests\Support;

use RuntimeException;

/**
 * Runs smtp_server.php and sets the config's SMTP server to it, for code that sends emails.
 */
class SmtpServer
{
    private $process;
    private string $file;

    public function __construct(array $config = [])
    {
        // a free port: bind to port 0 and let the system choose
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->file = tempnam(sys_get_temp_dir(), 'rivena_smtp');
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/smtp_server.php', $port, $this->file],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        TestConfig::load($config + ['smtp_host' => '127.0.0.1', 'smtp_port' => $port,
            'mail_from' => 'site@example.com']);

        for ($i = 0; $i < 50; $i++) {
            if ($connection = @fsockopen('127.0.0.1', $port)) {
                fclose($connection);
                return;
            }
            usleep(100000);
        }

        throw new RuntimeException('The SMTP server did not start.');
    }

    public function __destruct()
    {
        proc_terminate($this->process);
        proc_close($this->process);
        unlink($this->file);
    }

    /**
     * Returns the emails received so far (their headers and body, as sent).
     */
    public function emails(): array
    {
        return array_values(array_filter(explode("\0", file_get_contents($this->file))));
    }
}

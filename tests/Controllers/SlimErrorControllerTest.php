<?php
namespace Tests\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Controllers\SlimErrorController;
use Enicore\Rivena\Exceptions\InputException;
use Exception;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\SmtpServer;
use Tests\Support\TestConfig;

#[CoversClass(\Enicore\Rivena\Controllers\SlimErrorController::class)]
class SlimErrorControllerTest extends TestCase
{
    private SlimErrorController $controller;
    private string $log = '';
    private string $previousLog = '';

    protected function setUp(): void
    {
        $this->controller = new SlimErrorController(new View([__DIR__ . '/../Fixtures/app/src/views']));
    }

    protected function tearDown(): void
    {
        if ($this->log) {
            ini_set('error_log', $this->previousLog);
            unlink($this->log);
        }
    }

    /**
     * Sends error_log() to a file (PHPUnit sets its own error_log after setUp()).
     */
    private function startLog(): void
    {
        $this->log = tempnam(sys_get_temp_dir(), 'rivena');
        $this->previousLog = (string)ini_set('error_log', $this->log);
    }

    private function request(bool $json = false): Request
    {
        $request = Request::from((new ServerRequestFactory())->createServerRequest('GET', '/'));
        return $json ? $request->withHeader('Accept', 'application/json') : $request;
    }

    private function page(int $code, string $message): string
    {
        // no session is active, so no CSRF token
        return "<layout csrf=\"\" title=\"$message\">" .
            "<error code=\"$code\">$message</error>\n</layout>\n";
    }

    public function testHttpErrors(): void
    {
        $this->startLog();
        $request = $this->request();

        $response = ($this->controller)($request, new HttpNotFoundException($request), false, false, false);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame($this->page(404, 'Not found.'), (string)$response->getBody());

        $exception = new HttpMethodNotAllowedException($request);
        $response = ($this->controller)($request, $exception, false, false, false);
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame($this->page(405, 'Method not allowed.'), (string)$response->getBody());

        $response = ($this->controller)($request, new HttpException($request, 'Teapot', 418), false, false, false);
        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame($this->page(418, 'Teapot'), (string)$response->getBody());

        // HTTP errors aren't logged
        $this->assertSame('', file_get_contents($this->log));
    }

    public function testJsonErrors(): void
    {
        $this->startLog();
        $request = $this->request(true);

        $response = ($this->controller)($request, new HttpNotFoundException($request), false, false, false);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Not found."}', (string)$response->getBody());

        $request = Request::from((new ServerRequestFactory())->createServerRequest('POST', '/'))
            ->withHeader('Accept', 'application/json');
        $response = ($this->controller)($request, new Exception('Failure 77'), false, false, false);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Server error."}', (string)$response->getBody());
    }

    public function testServerErrors(): void
    {
        $this->startLog();
        $response = ($this->controller)($this->request(), new Exception('Failure 77'), false, false, false);

        // the message is logged, not shown
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($this->page(500, 'Server error.'), (string)$response->getBody());
        $this->assertStringContainsString('Failure 77', file_get_contents($this->log));

        // unless the details are displayed (development)
        $response = ($this->controller)($this->request(true), new Exception('Failure 78'), true, false, false);
        $this->assertSame('{"success":false,"error":"Server error. Failure 78"}', (string)$response->getBody());
    }

    /**
     * An invalid value that a controller didn't catch (e.g. an array for a string) is the client's error: a 400 with
     * the message, not logged.
     */
    public function testInputErrors(): void
    {
        $this->startLog();
        $exception = new InputException('Invalid value: id', 'id');
        $response = ($this->controller)($this->request(true), $exception, true, false, false);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Invalid value: id"}', (string)$response->getBody());
        $this->assertSame('', file_get_contents($this->log));
    }

    /**
     * A query's error is logged with its SQL, not its values (e.g. a password hash).
     */
    public function testQueryErrorsWithoutValues(): void
    {
        $this->startLog();
        $exception = new QueryException('default', 'insert into users (password) values (?)', ['$2y$12$secret'],
            new PDOException('Duplicate entry'));
        ($this->controller)($this->request(), $exception, false, false, false);

        $log = file_get_contents($this->log);
        $this->assertStringContainsString('Duplicate entry (SQL: insert into users (password) values (?))', $log);
        $this->assertStringNotContainsString('secret', $log);
    }

    /**
     * The server errors are emailed (ErrorLog), the HTTP errors aren't.
     */
    public function testServerErrorsAreEmailed(): void
    {
        $smtp = new SmtpServer(['error_notification_email' => 'admin@example.com']);
        $sent = sys_get_temp_dir() . '/rivena_error_mail_' . md5('key');
        is_file($sent) && unlink($sent);
        $this->startLog();
        $request = $this->request();

        ($this->controller)($request, new HttpNotFoundException($request), false, false, false);
        $this->assertSame([], $smtp->emails());

        $line = __LINE__ + 1;
        ($this->controller)($request, new Exception('Failure 79'), false, false, false);
        $this->assertStringContainsString('Exception: Failure 79 in ' . __FILE__ . ":$line\r\n", $smtp->emails()[0]);
        unlink($sent);
        TestConfig::load();
    }
}

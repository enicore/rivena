<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Rivena\Middleware\CsrfMiddleware::class)]
class CsrfMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    private function process(string $method, ?string $token, ?Handler $handler = null, mixed $body = null,
                             bool $json = false): string
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/');
        $token === null || ($request = $request->withHeader('X-CSRF-Token', $token));
        $body === null || ($request = $request->withParsedBody($body));
        $json && ($request = $request->withHeader('Accept', 'application/json'));
        $response = (new CsrfMiddleware())->process($request, $handler ?? new Handler());

        return $response->getStatusCode() . ' ' . $response->getHeaderLine('Content-Type') . ' ' . $response->getBody();
    }

    public function testSafeMethods(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $this->assertSame('200  handled', $this->process($method, null), $method);
        }
    }

    public function testUnsafeMethods(): void
    {
        $error = '403 text/plain; charset=utf-8 Invalid CSRF token. Please reload the page.';

        // no token in the session yet
        $this->assertSame($error, $this->process('POST', 'token'));

        $token = Session::getCsrfToken();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $handler = new Handler();
            $this->assertSame('200  handled', $this->process($method, $token, $handler), $method);
            $this->assertNotNull($handler->request);
            $this->assertSame($error, $this->process($method, null), $method);
            $this->assertSame($error, $this->process($method, ''), $method);
            $this->assertSame($error, $this->process($method, strrev($token)), $method);
        }
    }

    public function testFormField(): void
    {
        $token = Session::getCsrfToken();

        $this->assertSame('200  handled', $this->process('POST', null, body: ['csrf' => $token]));
        $this->assertStringStartsWith('403', $this->process('POST', null, body: ['csrf' => 'wrong']));
        $this->assertStringStartsWith('403', $this->process('POST', null, body: ['csrf' => [$token]]));
        // the header takes precedence
        $this->assertStringStartsWith('403', $this->process('POST', 'wrong', body: ['csrf' => $token]));
    }

    public function testJsonError(): void
    {
        $result = $this->process('POST', 'wrong', json: true);

        // the session's token (a new one after a remember-me login), for postJson() to retry with
        $this->assertSame(
            '403 application/json; charset=utf-8 ' .
            '{"success":false,"error":"Invalid CSRF token. Please reload the page.","csrf":"' .
            Session::getCsrfToken() . '"}',
            $result
        );
    }
}

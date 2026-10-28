<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Rivena\Middleware\SessionMiddleware::class)]
class SessionMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public function testStartsSession(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = (new SessionMiddleware())->process($request, new Handler());

        $this->assertSame('handled', (string)$response->getBody());
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame('Lax', session_get_cookie_params()['samesite']);
    }
}

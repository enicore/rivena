<?php
namespace Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\Handler;

#[CoversClass(\Enicore\Rivena\Middleware\SecurityHeadersMiddleware::class)]
class SecurityHeadersMiddlewareTest extends TestCase
{
    private function headers(SecurityHeadersMiddleware $middleware, ?RequestHandlerInterface $handler = null): array
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://example.com/');

        return array_map(fn($values) => implode(', ', $values),
            $middleware->process($request, $handler ?? new Handler())->getHeaders());
    }

    public function testDefaults(): void
    {
        $headers = $this->headers(new SecurityHeadersMiddleware());

        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);
    }

    public function testKeepsRouteHeaders(): void
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withHeader('X-Frame-Options', 'SAMEORIGIN');
            }
        };

        $this->assertSame('SAMEORIGIN',
            $this->headers(new SecurityHeadersMiddleware(), handler: $handler)['X-Frame-Options']);
    }
}

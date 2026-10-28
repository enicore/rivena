<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(Response::class)]
class ResponseTest extends TestCase
{
    private function json(ResponseInterface $response): array
    {
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        return json_decode((string)$response->getBody(), true);
    }

    public function testSendSuccessAndError(): void
    {
        $response = (new Response())->sendSuccess(['a' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => true, 'data' => ['a' => 1]], $this->json($response));

        $this->assertSame(['success' => true, 'data' => []], $this->json((new Response())->sendSuccess()));
        $this->assertSame(201, (new Response())->sendSuccess([], 201)->getStatusCode());

        $response = (new Response())->sendError('Failed');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Failed'], $this->json($response));

        $response = (new Response())->sendError('Not allowed', 403, ['redirect' => '/login']);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Not allowed', 'redirect' => '/login'],
            $this->json($response));
    }

    public function testSendJson(): void
    {
        $response = (new Response())->sendJson(['url' => 'a/b', 'text' => 'ä', 'invalid' => "\xff"], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"url":"a/b","text":"ä","invalid":"' . "\u{fffd}" . '"}', (string)$response->getBody());

        // values that can't be encoded throw (the error handler sends the error)
        $this->expectException(\JsonException::class);
        (new Response())->sendJson(['number' => INF]);
    }

    public function testSendHtmlTextAndRedirect(): void
    {
        $response = (new Response())->sendHtml('<b>html</b>', 202);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('<b>html</b>', (string)$response->getBody());

        $response = (new Response())->sendText('text');
        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('text', (string)$response->getBody());

        $response = (new Response())->sendRedirect('/login');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertSame(301, (new Response())->sendRedirect('/new', 301)->getStatusCode());
    }

    public function testSendFieldErrors(): void
    {
        $response = (new Response())->sendFieldErrors(['name' => 'Too long', 'email' => 'Invalid']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Too long',
            'errors' => ['name' => 'Too long', 'email' => 'Invalid']], $this->json($response));
    }

    public function testKeepsTheClass(): void
    {
        $this->assertInstanceOf(Response::class, (new Response())->withHeader('X-A', '1')->withStatus(201));
    }
}

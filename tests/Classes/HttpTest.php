<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Http;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(\Enicore\Rivena\Classes\Http::class)]
class HttpTest extends TestCase
{
    private function request(array $headers = [], string $uri = 'http://example.com/',
                             array $server = []): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $uri, $server);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function testWantsJson(): void
    {
        $this->assertTrue(Http::wantsJson($this->request(['Accept' => 'application/json'])));
        $this->assertTrue(Http::wantsJson($this->request(['Accept' => 'text/html, Application/JSON;q=0.9'])));
        $this->assertFalse(Http::wantsJson($this->request(['Accept' => 'text/html,*/*'])));
        $this->assertFalse(Http::wantsJson($this->request()));
    }
}

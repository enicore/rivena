<?php
namespace Tests\Middleware;

use Enicore\Rivena\Middleware\LiveReloadMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

#[CoversClass(LiveReloadMiddleware::class)]
class LiveReloadMiddlewareTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rivena_live_' . uniqid();
        mkdir("$this->dir/css", 0777, true);
        touch("$this->dir/index.php", 1000);
        touch("$this->dir/css/app.css", 2000);
        touch("$this->dir/css/app.scss", 3000);
        touch("$this->dir/css/app.css.map", 3000);
    }

    protected function tearDown(): void
    {
        array_map('unlink', ["$this->dir/index.php", "$this->dir/css/app.css", "$this->dir/css/app.scss",
            "$this->dir/css/app.css.map"]);
        rmdir("$this->dir/css");
        rmdir($this->dir);
    }

    private function process(string $path, string $type = 'text/html', string $body = '<body></body>'): ResponseInterface
    {
        $handler = new class($type, $body) implements RequestHandlerInterface {
            public function __construct(private string $type, private string $body)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response();
                $response->getBody()->write($this->body);
                return $response->withHeader('Content-Type', $this->type);
            }
        };

        $request = (new ServerRequestFactory())->createServerRequest('GET', $path);
        return (new LiveReloadMiddleware([$this->dir, "$this->dir/missing"]))->process($request, $handler);
    }

    public function testTimes(): void
    {
        $response = $this->process(LiveReloadMiddleware::PATH);

        // the SCSS and map files are skipped
        $this->assertSame(['css' => 2000, 'other' => 1000], json_decode((string)$response->getBody(), true));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testScript(): void
    {
        $response = $this->process(LiveReloadMiddleware::PATH . '.js');

        $this->assertStringStartsWith('text/javascript', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('location.reload()', (string)$response->getBody());
    }

    public function testAddsScript(): void
    {
        $script = '<script src="' . LiveReloadMiddleware::PATH . '.js"></script>';

        $this->assertSame("<body>$script</body>",
            (string)$this->process('/', 'text/html; charset=utf-8')->getBody());
        $this->assertSame("<p>no body</p>$script", (string)$this->process('/', body: '<p>no body</p>')->getBody());
        $this->assertSame('<body></body>', (string)$this->process('/', 'application/json')->getBody());
    }
}

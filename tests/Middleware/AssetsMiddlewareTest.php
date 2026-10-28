<?php
namespace Tests\Middleware;

use Enicore\Rivena\Classes\Assets;
use Enicore\Rivena\Middleware\AssetsMiddleware;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\Database;

#[CoversClass(AssetsMiddleware::class)]
class AssetsMiddlewareTest extends TestCase
{
    /**
     * A failed build is in the Error Log, and the request is still handled.
     */
    public function testBuildError(): void
    {
        Database::create();
        $root = sys_get_temp_dir() . '/rivena_assets_' . uniqid();
        mkdir("$root/src/styles", 0777, true);
        file_put_contents("$root/src/styles/app.scss", 'a { color: ');
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        $log = ini_set('error_log', '/dev/null');
        $response = new AssetsMiddleware(new Assets($root))
            ->process((new ServerRequestFactory())->createServerRequest('GET', '/'), $handler);
        ini_set('error_log', $log);
        exec('rm -rf ' . escapeshellarg($root));

        $this->assertSame(204, $response->getStatusCode());
        $row = DB::table('error_log')->first();
        $this->assertSame('warning', $row->level);
        $this->assertStringStartsWith('Dart Sass failed: ', $row->message);
    }
}

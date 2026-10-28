<?php
namespace Tests\Middleware;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Middleware\PermissionMiddleware;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;
use Tests\Support\Handler;
use Tests\Support\PostsController;
use Tests\Support\TestConfig;

#[CoversClass(PermissionMiddleware::class)]
class PermissionMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
    }

    public function testPermission(): void
    {
        TestConfig::load();
        PostsController::route();
        Database::create();
        DB::table('role_permissions')->insert(['role_id' => 1, 'permission' => 'posts.edit']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/posts');
        Auth::logIn(User::forceCreate(['id' => 2, 'email' => 'a@example.com', 'password' => 'x', 'created_at' => 1]));

        $this->assertSame(200, (new PermissionMiddleware('posts.edit'))->process($request, new Handler())
            ->getStatusCode());

        DB::table('role_permissions')->delete();
        $this->expectException(HttpForbiddenException::class);
        (new PermissionMiddleware('posts.edit'))->process($request, new Handler());
    }
}

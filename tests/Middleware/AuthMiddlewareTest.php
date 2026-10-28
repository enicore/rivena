<?php
namespace Tests\Middleware;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Middleware\AuthMiddleware;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;
use Tests\Support\TestConfig;

#[CoversClass(\Enicore\Rivena\Middleware\AuthMiddleware::class)]
class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        TestConfig::load(['admins' => '1']);
        Database::create();
        User::forceCreate(['email' => 'admin@example.com', 'password' => 'x', 'created_at' => 1]);
        User::forceCreate(['email' => 'user@example.com', 'password' => 'x', 'created_at' => 1]);
    }

    protected function tearDown(): void
    {
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
    }

    // the user with the ID logs in first, unless it's null
    private function request(?int $userId = 1, bool $json = false): ResponseInterface
    {
        // in an app, so the request has the route parser for the login URL
        $app = AppFactory::create();
        $app->setBasePath('/app');
        $app->get('/sign-in', fn($request, $response) => $response)->setName('login');
        $app->get('/admin', function ($request, $response) {
            $response->getBody()->write('handled');
            return $response;
        })->add(new AuthMiddleware());
        $userId && Auth::logIn(User::find($userId));

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/app/admin');
        $json && ($request = $request->withHeader('Accept', 'application/json'));

        return $app->handle($request);
    }

    public function testLoggedIn(): void
    {
        $this->assertSame('handled', (string)$this->request()->getBody());
        $this->assertSame('handled', (string)$this->request(2)->getBody());
    }

    public function testRedirectsToLogin(): void
    {
        $response = $this->request(null);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/app/sign-in', $response->getHeaderLine('Location'));
    }

    public function testLogsOutDisabledUsers(): void
    {
        User::where('id', 2)->update(['disabled' => 1]);
        $response = $this->request(2);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull(Auth::id());
    }

    /**
     * Without the "login" permission, the users are logged out, not the admins.
     */
    public function testLogsOutUsersWhoCantLogIn(): void
    {
        DB::table('role_permissions')->delete();
        $this->assertSame('handled', (string)$this->request()->getBody());

        $this->assertSame(302, $this->request(2)->getStatusCode());
        $this->assertNull(Auth::id());
    }

    public function testJsonError(): void
    {
        $response = $this->request(null, true);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Please log in.","redirect":"/app/sign-in"}',
            (string)$response->getBody());
    }
}

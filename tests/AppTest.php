<?php
namespace Tests;

use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Crypt;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Controllers\AdminController;
use Enicore\Rivena\Controllers\AuthController;
use Enicore\Rivena\Middleware\AdminMiddleware;
use Enicore\Rivena\Middleware\AuthMiddleware;
use Enicore\Rivena\Middleware\CsrfMiddleware;
use Enicore\Rivena\Middleware\LiveReloadMiddleware;
use Enicore\Rivena\Middleware\PermissionMiddleware;
use Enicore\Rivena\Middleware\SessionMiddleware;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;

/**
 * A project set up with Rivena, without a database connection (Eloquent connects on the first query).
 */
#[CoversClass(App::class)]
#[CoversClass(AdminController::class)]
#[CoversClass(AuthController::class)]
#[CoversClass(AdminMiddleware::class)]
class AppTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_project_' . uniqid();
        mkdir("$this->root/src", 0777, true);
        symlink(__DIR__ . '/Fixtures/app/src/views', "$this->root/src/views");
        file_put_contents("$this->root/.env", "app_env=dev\napp_key=key\nsite_name=Site\n" .
            "site_url=https://example.com\ndb_host=localhost\ndb_database=db\ndb_username=user\n" .
            "db_password=pass\nrivena_t_key=secret\n");
        file_put_contents("$this->root/src/config.php", "<?php \$config = ['rivena_t_key' => 'file', 'db_port' => 3306];");
    }

    protected function tearDown(): void
    {
        unlink("$this->root/.env");
        unlink("$this->root/src/config.php");
        unlink("$this->root/src/views");
        rmdir("$this->root/src");
        rmdir($this->root);
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
        // new App() sets ErrorLog's
        restore_error_handler();
    }

    // logs in a user with the ID, in SQLite instead of the app's MySQL
    private function logIn(int $id): void
    {
        Database::create();
        $user = User::forceCreate(['id' => $id, 'email' => "$id@example.com", 'password' => 'x', 'created_at' => 1]);
        Auth::logIn($user);
    }

    private function request(App $app, string $method, string $path, bool $json = false): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, "https://example.com$path");
        $json && $request = $request->withHeader('Accept', 'application/json');

        return $app->handle(Request::from($request));
    }

    public function testCreate(): void
    {
        new App($this->root);

        $this->assertSame('dev', Config::get('app_env'));
        $this->assertSame(3306, Config::get('db_port'));
        $this->assertSame('secret', Config::get('rivena_t_key'));
        $this->assertSame('db', Capsule::connection()->getDatabaseName());
    }

    public function testCreateOptions(): void
    {
        $app = new App($this->root, ['services' => ['answer' => 42]]);

        $this->assertSame(42, $app->getContainer()->get('answer'));
    }

    public function testCsp(): void
    {
        $app = new App($this->root, ['csp' => "default-src 'none'"]);
        $app->get('/', fn($request, $response) => $response);

        $this->assertSame("default-src 'none'",
            $this->request($app, 'GET', '/')->getHeaderLine('Content-Security-Policy'));
    }

    public function testAdminRoutes(): void
    {
        // logged out; the login page checks the password_reset permission
        $app = new App($this->root);
        Database::create();
        $app->group('/usr', function ($group) {
            $group->post('/hello', fn($request, $response) => $response->withStatus(204));
        })->add(CsrfMiddleware::class)->add(AuthMiddleware::class)->add(SessionMiddleware::class);

        // the admin area redirects to the login page
        $response = $this->request($app, 'GET', '/usr/users');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/usr/login', $response->getHeaderLine('Location'));
        $this->assertSame(401, $this->request($app, 'POST', '/usr/hello', true)->getStatusCode());

        // the login page: in its own layout (not the app's public one), with noindex, the live reload script
        // (app_env=dev) and the headers
        $response = $this->request($app, 'GET', '/usr/login');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('<!DOCTYPE html>', (string)$response->getBody());
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string)$response->getBody());
        $this->assertStringContainsString('id="login-page"', (string)$response->getBody());
        $this->assertStringContainsString(LiveReloadMiddleware::PATH, (string)$response->getBody());
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

        // the login needs the CSRF token
        $this->assertSame(403, $this->request($app, 'POST', '/usr/login', true)->getStatusCode());
    }

    /**
     * Rivena's routes are named; removeRoutes() removes them with the routes named after them with a dot, and the
     * menu links (w_route()) go with them. user_path moves them.
     */
    public function testRemoveRoutes(): void
    {
        file_put_contents("$this->root/.env", "user_path=/panel\n", FILE_APPEND);
        $app = new App($this->root);
        $app->removeRoutes('users', 'system');
        $app->get('/panel', fn($request, $response) => $response)->setName('dashboard');

        $routes = [];
        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            $routes[$route->getName()] = $route->getMethods()[0] . ' ' . $route->getPattern();
        }

        $this->assertSame([
            'login' => 'GET /panel/login',
            'login.post' => 'POST /panel/login',
            'login.two-factor' => 'POST /panel/login/two-factor',
            'login.two-factor.challenge' => 'POST /panel/login/two-factor/challenge',
            'logout' => 'GET /panel/logout',
            'forgot-password' => 'GET /panel/forgot-password',
            'forgot-password.post' => 'POST /panel/forgot-password',
            'reset-password' => 'GET /panel/reset-password/{token:[0-9a-f]{64}}',
            'reset-password.post' => 'POST /panel/reset-password',
            'password-strength' => 'POST /panel/password-strength',
            'account' => 'GET /panel/account',
            'account.save' => 'POST /panel/account/save',
            'account.two-factor.setup' => 'POST /panel/account/two-factor/setup',
            'account.two-factor.enable' => 'POST /panel/account/two-factor/enable',
            'account.two-factor.disable' => 'POST /panel/account/two-factor/disable',
            'account.two-factor.recovery-codes' => 'POST /panel/account/two-factor/recovery-codes',
            'account.two-factor.turn-off' => 'POST /panel/account/two-factor/turn-off',
            'roles' => 'GET /panel/roles',
            'roles.save' => 'POST /panel/roles/save',
            'roles.delete' => 'POST /panel/roles/delete',
            'permissions' => 'GET /panel/permissions',
            'permissions.save' => 'POST /panel/permissions/save',
            'events' => 'GET /panel/events',
            'events.list' => 'POST /panel/events/list',
            'error-log' => 'GET /panel/error-log',
            'error-log.list' => 'POST /panel/error-log/list',
            'dashboard' => 'GET /panel',
        ], $routes);
        $this->assertSame('/panel', w_route('dashboard'));
        $this->assertSame('/panel/events', w_route('events'));
        $this->assertSame('', w_route('users'));
    }

    /**
     * Rivena's users, roles, permissions, events and system pages are only for the admins, and the pages an app
     * restricts (with their actions); an app's route isn't unless it has AdminMiddleware.
     */
    public function testAdminRoutesForAdmins(): void
    {
        $app = new App($this->root);
        $app->getRouteGroup('account')->add(AdminMiddleware::class);
        $app->group('/usr', function ($group) {
            $group->post('/reports', fn($request, $response) => $response->withStatus(204));
        })->add(CsrfMiddleware::class)->add(AuthMiddleware::class)->add(SessionMiddleware::class);

        // logged in as user 2, not an admin
        $this->logIn(2);
        $post = fn($path) => $app->handle(Request::from((new ServerRequestFactory())
            ->createServerRequest('POST', "https://example.com$path")
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-CSRF-Token', Session::getCsrfToken())))->getStatusCode();

        $this->assertSame(403, $post('/usr/users/list'));
        $this->assertSame(403, $post('/usr/roles/save'));
        $this->assertSame(403, $post('/usr/permissions/save'));
        $this->assertSame(403, $post('/usr/events/list'));
        $this->assertSame(403, $post('/usr/system/test-email'));
        $this->assertSame(403, $post('/usr/account/save'));
        $this->assertSame(403, $post('/usr/account/two-factor/setup'));
        $this->assertSame(204, $post('/usr/reports'));
    }

    /**
     * A page group's middleware runs after the login's, so it knows the user (here: the User role's login permission);
     * an unknown page name throws.
     */
    public function testRouteGroupMiddlewareKnowsTheUser(): void
    {
        $app = new App($this->root);
        $app->getRouteGroup('account')->add(new PermissionMiddleware('login'));
        $this->logIn(2);

        $this->assertSame(200, $this->request($app, 'GET', '/usr/account')->getStatusCode());

        $this->expectException(RuntimeException::class);
        $app->getRouteGroup('acount');
    }

    /**
     * run() adds the 404 page last, so it doesn't hide the app's routes with placeholders.
     */
    public function testNotFound(): void
    {
        $app = new App($this->root);
        $app->get('/usr/reports/{id}', fn($request, $response, $args) => $response->sendText("report {$args['id']}"));
        $this->logIn(2);
        $run = function ($path) use ($app) {
            ob_start();
            $app->run(Request::from((new ServerRequestFactory())->createServerRequest('GET', "https://example.com$path")));
            return ob_get_clean();
        };

        $this->assertSame('report 5', $run('/usr/reports/5'));
        $this->assertStringContainsString('<error code="404">Page not found</error>', $run('/usr/missing'));
    }

    /**
     * The container has Eloquent's connection, for queries without models.
     */
    public function testConnection(): void
    {
        $app = new App($this->root);

        $this->assertSame(Capsule::connection(), $app->getContainer()->get(Connection::class));
    }

    public function testAppKey(): void
    {
        new App($this->root);
        $this->assertSame(5, Crypt::decryptId(Crypt::encryptId(5)));
    }

    public function testAdminPages(): void
    {
        file_put_contents("$this->root/.env", "admins=1\n", FILE_APPEND);
        $app = new App($this->root);
        $request = fn($path) => $app->handle(Request::from((new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withHeader('Accept', 'application/json')));
        $app->group('', function ($group) {
            $group->post('/all', fn($request, $response) => $response->withStatus(204));
            $group->group('', function ($group) {
                $group->post('/usrins', fn($request, $response) => $response->withStatus(204));
            })->add(AdminMiddleware::class);
        });

        $this->logIn(2);
        $this->assertSame(204, $request('/all')->getStatusCode());
        $response = $request('/usrins');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('{"success":false,"error":"Access denied."}', (string)$response->getBody());

        $this->logIn(1);
        $this->assertSame(204, $request('/usrins')->getStatusCode());
    }

    /**
     * Changing the logged-in user's email or password needs the current password; the settings don't.
     */
    public function testAccountChangesNeedCurrentPassword(): void
    {
        $app = new App($this->root);
        $this->logIn(2);
        Auth::setPassword(User::find(2), 'old password');
        $save = function (array $values) use ($app) {
            $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com/usr/account/save')
                ->withHeader('Accept', 'application/json')->withHeader('Content-Type', 'application/json')
                ->withHeader('X-CSRF-Token', Session::getCsrfToken());
            $request->getBody()->write(json_encode($values + ['email' => '2@example.com', 'password' => '',
                'date_format' => 'Y-m-d', 'time_format' => 'H:i', 'timezone' => 'UTC']));
            return json_decode((string)$app->handle(Request::from($request))->getBody(), true);
        };

        $this->assertTrue($save(['time_format' => 'g:i A'])['success']);
        $this->assertSame(['current_password' => 'Wrong password.'],
            $save(['password' => 'lantern orbit 47'])['errors']);
        $this->assertSame(['current_password' => 'Wrong password.'],
            $save(['password' => 'lantern orbit 47', 'current_password' => 'wrong'])['errors']);
        $this->assertSame(['current_password' => 'Wrong password.'], $save(['email' => 'new@example.com'])['errors']);
        $this->assertTrue(password_verify('old password', User::find(2)->password));

        $this->assertTrue($save(['password' => 'lantern orbit 47', 'current_password' => 'old password'])['success']);
        $this->assertTrue(password_verify('lantern orbit 47', User::find(2)->password));
        // a password reset link sent to the old email stops working
        DB::table('role_permissions')->insert(['role_id' => 1, 'permission' => 'password_reset']);
        $token = Auth::createPasswordReset(User::find(2));
        $this->assertNotNull(Auth::passwordResetUser($token));
        $this->assertTrue($save(['email' => 'new@example.com', 'current_password' => 'lantern orbit 47'])['success']);
        $this->assertSame('new@example.com', User::find(2)->email);
        $this->assertNull(Auth::passwordResetUser($token));
    }

    public function testProduction(): void
    {
        file_put_contents("$this->root/.env", "app_env=production\napp_key=key\nsite_name=Site\n" .
            "site_url=https://example.com\ndb_host=localhost\ndb_database=db\n" .
            "db_username=user\ndb_password=pass\nrivena_t_key=secret\n");
        $app = new App($this->root);
        $app->get('/', fn($request, $response) => $response->withHeader('Content-Type', 'text/html'));

        $response = $this->request($app, 'GET', '/');
        $this->assertSame('', (string)$response->getBody());
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame(404, $this->request($app, 'GET', LiveReloadMiddleware::PATH)->getStatusCode());
    }
}

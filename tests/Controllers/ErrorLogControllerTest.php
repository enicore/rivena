<?php
namespace Tests\Controllers;

use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\ErrorLog;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Controllers\ErrorLogController;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;

/**
 * The admins' Error Log page, through the app's routes, in SQLite.
 */
#[CoversClass(ErrorLogController::class)]
class ErrorLogControllerTest extends TestCase
{
    private string $root;
    private App $app;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_project_' . uniqid();
        mkdir("$this->root/src", 0777, true);
        symlink(__DIR__ . '/../Fixtures/app/src/views', "$this->root/src/views");
        file_put_contents("$this->root/.env", "app_env=production\napp_key=key\nsite_name=Site\n" .
            "site_url=https://example.com\ndb_host=localhost\n" .
            "db_port=3306\ndb_database=db\ndb_username=user\ndb_password=pass\nadmins=1\n");
        $this->app = new App($this->root);
        // ErrorLog's, which new App() sets
        restore_error_handler();
        Database::create();
        Auth::logIn(User::forceCreate(['id' => 1, 'email' => 'admin@example.com', 'password' => 'x',
            'created_at' => 1]));
        User::forceCreate(['id' => 2, 'email' => 'user@example.com', 'password' => 'x', 'created_at' => 1]);
    }

    protected function tearDown(): void
    {
        unlink("$this->root/.env");
        unlink("$this->root/src/views");
        rmdir("$this->root/src");
        rmdir($this->root);
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
    }

    private function request(string $path, ?array $body = null): array
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($body === null ? 'GET' : 'POST', "https://example.com/usr$path")
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-CSRF-Token', Session::getCsrfToken());
        $request->getBody()->write(json_encode($body));

        return json_decode((string)$this->app->handle(Request::from($request))->getBody(), true);
    }

    /**
     * The errors and warnings, the last seen first, searched by the message and the path; for the admins only.
     */
    public function testPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/page';
        ErrorLog::add('warning', 'First');
        ErrorLog::add('warning', 'First');
        DB::table('error_log')->update(['last_at' => time() - 60]);
        ErrorLog::add('error', 'Second');
        unset($_SERVER['REQUEST_URI']);

        $rows = $this->request('/error-log')['data']['data']['table']['rows'];
        $this->assertSame([['error', 'Second', '/page', 1], ['warning', 'First', '/page', 2]],
            array_map(fn($row) => [$row['level'], $row['message'], $row['path'], $row['count']], $rows));

        $table = $this->request('/error-log/list', ['search' => 'first'])['data'];
        $this->assertSame(['First'], array_column($table['rows'], 'message'));

        Auth::logIn(User::find(2));
        $this->assertFalse($this->request('/error-log')['success']);
    }
}

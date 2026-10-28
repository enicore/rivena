<?php
namespace Tests\Controllers;

use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Controllers\PermissionsController;
use Enicore\Rivena\Controllers\RolesController;
use Enicore\Rivena\Controllers\UsersController;
use Enicore\Rivena\Models\Event;
use Enicore\Rivena\Models\Role;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;
use Tests\Support\PostsController;

/**
 * The Roles and Permissions pages and the users' roles, through the app's routes, in SQLite.
 */
#[CoversClass(PermissionsController::class)]
#[CoversClass(RolesController::class)]
#[CoversClass(UsersController::class)]
class PermissionsControllerTest extends TestCase
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
            "db_port=3306\ndb_database=db\ndb_username=user\ndb_password=pass\nadmins=1,3\n");
        $this->app = new App($this->root);
        // the permissions of the routes' controllers
        $this->app->get('/posts', [PostsController::class, 'page']);
        // ErrorLog's, which new App() sets
        restore_error_handler();
        Database::create();
        // 1 the admin, 2 a user with the Editor role
        Auth::logIn(User::forceCreate(['id' => 1, 'email' => 'admin@example.com', 'password' => 'x',
            'created_at' => 1]));
        User::forceCreate(['id' => 2, 'email' => 'user@example.com', 'password' => 'x', 'created_at' => 1]);
        Role::forceCreate(['id' => 2, 'name' => 'Editor']);
        DB::table('user_roles')->insert(['user_id' => 2, 'role_id' => 2]);
        DB::table('role_permissions')->insert(['role_id' => 2, 'permission' => 'posts.edit']);
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

    // returns the JSON answer; a body is posted
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

    private function grants(): array
    {
        return DB::table('role_permissions')->orderBy('role_id')->orderBy('permission')->get()
            ->map(fn($row) => "$row->role_id:$row->permission")->all();
    }

    public function testPage(): void
    {
        $data = $this->request('/permissions')['data']['data'];

        $this->assertSame([['name' => 'login', 'description' => 'Log in'],
            ['name' => 'password_reset', 'description' => 'Reset password by email'],
            ['name' => 'posts.edit', 'description' => 'Edit posts'],
            ['name' => 'posts.delete', 'description' => 'Delete posts']], $data['permissions']);
        $this->assertSame([['id' => 1, 'name' => 'User', 'permissions' => ['login']],
            ['id' => 2, 'name' => 'Editor', 'permissions' => ['posts.edit']]], $data['roles']);
    }

    /**
     * Saving replaces the roles' permissions, ignoring the unknown ones; the roles stay.
     */
    public function testSave(): void
    {
        $answer = $this->request('/permissions/save', ['roles' => [
            ['id' => 1, 'name' => 'Renamed', 'permissions' => ['login', 'posts.edit', 'unknown']],
            ['id' => 2, 'permissions' => []],
        ]]);

        $this->assertTrue($answer['success']);
        $this->assertSame(['1:login', '1:posts.edit'], $this->grants());
        $this->assertSame(['User', 'Editor'], Role::orderBy('id')->pluck('name')->all());
        $this->assertSame('permissions.updated', Event::value('action'));
        $this->assertSame([], $answer['data']['roles'][1]['permissions']);
    }

    public function testSaveErrors(): void
    {
        $save = fn($roles) => $this->request('/permissions/save', ['roles' => $roles])['error'] ?? null;

        $this->assertSame('Invalid request.', $save([['id' => 1, 'permissions' => []],
            ['id' => 9, 'permissions' => []]]));
        $this->assertSame('Invalid request.', $save([['id' => 1]]));
        $this->assertSame('Invalid request.', $save('x'));

        // nothing changed
        $this->assertSame(['1:login', '2:posts.edit'], $this->grants());
    }

    /**
     * The Roles page lists the roles (not role 1) with their number of users; adds, renames and deletes them.
     */
    public function testRoles(): void
    {
        $this->assertSame([['id' => 2, 'name' => 'Editor', 'users' => 1]],
            $this->request('/roles')['data']['data']['roles']);

        $answer = $this->request('/roles/save', ['name' => ' Author ']);
        $this->assertSame([['id' => 3, 'name' => 'Author', 'users' => 0],
            ['id' => 2, 'name' => 'Editor', 'users' => 1]], $answer['data']['roles']);
        $this->assertSame('role.created', Event::orderByDesc('id')->value('action'));

        $long = str_repeat('x', 101);
        foreach (['' => 'Enter a name.', 'admin' => 'A role has this name.', 'User' => 'A role has this name.',
                     'Editor' => 'A role has this name.', $long => 'The name can be at most 100 characters.']
                 as $name => $error) {
            $this->assertSame(['name' => $error], $this->request('/roles/save', ['name' => (string)$name])['errors']);
        }

        // renamed, to its own name in other letter case too
        $answer = $this->request('/roles/save', ['id' => 3, 'name' => 'Writer']);
        $this->assertSame('Writer', $answer['data']['roles'][1]['name']);
        $this->assertSame('role.renamed', Event::orderByDesc('id')->value('action'));
        $this->assertSame('Author -> Writer', Event::orderByDesc('id')->value('message'));
        $this->assertTrue($this->request('/roles/save', ['id' => 3, 'name' => 'writer'])['success']);
        // the same name: no event
        $events = Event::count();
        $this->assertTrue($this->request('/roles/save', ['id' => 3, 'name' => 'writer'])['success']);
        $this->assertSame($events, Event::count());
        $this->assertSame(['name' => 'A role has this name.'],
            $this->request('/roles/save', ['id' => 3, 'name' => 'Editor'])['errors']);
        foreach ([1, 9] as $id) {
            $this->assertSame('The role doesn\'t exist.',
                $this->request('/roles/save', ['id' => $id, 'name' => 'X'])['error']);
        }
        $this->assertTrue($this->request('/roles/save', ['id' => 3, 'name' => 'Author'])['success']);

        // its users lose it, and its permissions go
        $this->assertSame([['id' => 3, 'name' => 'Author', 'users' => 0]],
            $this->request('/roles/delete', ['id' => 2])['data']['roles']);
        $this->assertSame(0, DB::table('user_roles')->count());
        $this->assertSame(['1:login'], $this->grants());
        $this->assertSame('role.deleted', Event::orderByDesc('id')->value('action'));

        $this->assertSame('The role doesn\'t exist.', $this->request('/roles/delete', ['id' => 1])['error']);
        $this->assertSame(['User', 'Author'], Role::orderBy('id')->pluck('name')->all());
    }

    /**
     * An admin sets a user's roles (not role 1, nor unknown ones); the account page doesn't change them.
     */
    public function testUserRoles(): void
    {
        Role::forceCreate(['id' => 3, 'name' => 'Author']);
        $user = $this->request('/users/' . User::find(2)->public_id . '/edit')['data']['data'];
        $this->assertSame([1, 2], $user['user']['roles']);
        $this->assertSame([['id' => 1, 'name' => 'User'], ['id' => 3, 'name' => 'Author'],
            ['id' => 2, 'name' => 'Editor']], $user['roles']);

        // not for an admin
        $this->assertSame([], $this->request('/users/' . User::find(1)->public_id . '/edit')['data']['data']['roles']);
        $this->assertCount(3, $this->request('/users/add')['data']['data']['roles']);

        $values = ['email' => 'user@example.com', 'date_format' => 'Y-m-d', 'time_format' => 'H:i',
            'timezone' => 'Europe/Paris'];
        $this->assertTrue($this->request('/users/save', $values + ['id' => User::find(2)->public_id,
            'roles' => [1, 3, 9]])['success']);
        $this->assertSame([3], User::find(2)->roles->pluck('id')->all());

        $this->assertSame([], $this->request('/account')['data']['data']['roles']);
        $this->assertTrue($this->request('/account/save', ['email' => 'admin@example.com'] + $values +
            ['roles' => [3]])['success']);
        $this->assertSame([], User::find(1)->roles->pluck('id')->all());

        // the users table shows the roles, by name
        DB::table('user_roles')->insert(['user_id' => 2, 'role_id' => 2]);
        $this->assertSame('Author, Editor',
            $this->request('/users/list', ['search' => 'user@'])['data']['rows'][0]['roles']);
    }

    /**
     * Deleting a user deletes its rows in the other tables; the edit page has the delete button for other users only.
     */
    public function testDeleteUser(): void
    {
        $user = User::find(2);
        $user->settings->set(['timezone' => 'Europe/Paris']);
        $user->settings->save();
        DB::table('role_permissions')->insert(['role_id' => Role::USER, 'permission' => 'password_reset']);
        $this->assertNotNull(Auth::createPasswordReset($user));
        DB::table('user_remember_tokens')->insert(['selector' => 's', 'user_id' => 2, 'hash' => 'h',
            'session_version' => 0, 'expires_at' => 1]);
        $this->assertTrue($this->request('/users/' . $user->public_id . '/edit')['data']['data']
            ['canDisableOrDelete']);
        $own = $this->request('/users/' . User::find(1)->public_id . '/edit')['data']['data'];
        $this->assertFalse($own['canDisableOrDelete']);

        $this->assertTrue($this->request('/users/delete', ['id' => $user->public_id])['success']);
        foreach (['users' => 1, 'user_settings' => 0, 'user_roles' => 0, 'user_remember_tokens' => 0,
                     'password_resets' => 0] as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    /**
     * Another admin: changing its email or password needs the admin's own password; it can't be deleted.
     */
    public function testOtherAdmin(): void
    {
        Auth::setPassword(User::find(1), 'admin password');
        $other = User::forceCreate(['id' => 3, 'email' => 'other@example.com', 'password' => 'x', 'created_at' => 1]);
        $values = ['id' => $other->public_id, 'email' => 'other@example.com', 'date_format' => 'Y-m-d',
            'time_format' => 'H:i', 'timezone' => 'Europe/Paris', 'roles' => []];

        $page = $this->request("/users/$other->public_id/edit")['data']['data'];
        $this->assertSame([true, false, false],
            [$page['askPassword'], $page['own'], $page['canDisableOrDelete']]);
        $this->assertFalse($this->request('/users/' . User::find(2)->public_id . '/edit')['data']['data']
            ['askPassword']);

        foreach ([['email' => 'new@example.com'], ['password' => 'lantern orbit 47']] as $change) {
            $this->assertSame(['current_password' => 'Wrong password.'],
                $this->request('/users/save', $change + $values)['errors']);
            $this->assertSame(['current_password' => 'Wrong password.'],
                $this->request('/users/save', $change + ['current_password' => 'wrong'] + $values)['errors']);
        }
        $this->assertSame(['other@example.com', 'x'], [User::find(3)->email, User::find(3)->password]);
        // the settings don't need it, nor a user's email
        $this->assertTrue($this->request('/users/save', ['time_format' => 'g:i A'] + $values)['success']);
        $this->assertTrue($this->request('/users/save', ['id' => User::find(2)->public_id,
            'email' => 'user2@example.com'] + $values)['success']);

        $this->assertTrue($this->request('/users/save', ['email' => 'new@example.com',
            'current_password' => 'admin password'] + $values)['success']);
        $this->assertSame('new@example.com', User::find(3)->email);

        $this->assertSame('You can\'t delete an admin.', $this->request('/users/delete', ['id' => $other->public_id])
            ['error']);
        $this->assertNotNull(User::find(3));
    }

    /**
     * Disabling a user ends its sessions, remember cookies and password reset link, which enabling doesn't bring back.
     */
    public function testDisableEndsSessions(): void
    {
        DB::table('role_permissions')->insert(['role_id' => Role::USER, 'permission' => 'password_reset']);
        $user = User::find(2);
        $token = Auth::createPasswordReset($user);
        DB::table('user_remember_tokens')->insert(['selector' => 's', 'user_id' => 2, 'hash' => 'h',
            'session_version' => 0, 'expires_at' => time() + 60]);
        $version = $user->session_version;

        $this->assertTrue($this->request('/users/disable', ['id' => $user->public_id, 'disabled' => true])['success']);
        $this->assertTrue($this->request('/users/disable', ['id' => $user->public_id, 'disabled' => false])
            ['success']);

        $this->assertSame([false, $version + 1], [User::find(2)->disabled, User::find(2)->session_version]);
        $this->assertSame(0, DB::table('user_remember_tokens')->count());
        $this->assertNull(Auth::passwordResetUser($token));
        $this->assertSame(1, Auth::id());
    }

    /**
     * Invalid values are the client's errors (400), not the site's: an array or text for an ID, an array for the
     * account's tab, an email longer than the column.
     */
    public function testInvalidInput(): void
    {
        $this->assertSame(['success' => false, 'error' => 'Invalid value: id'],
            $this->request('/users/delete', ['id' => ['x']]));
        $this->assertSame(['success' => false, 'error' => 'Invalid number: id'],
            $this->request('/roles/delete', ['id' => 'abc']));
        $this->assertTrue($this->request('/account?tab[]=x')['success']);

        $email = str_repeat('a', 64) . '@' . str_repeat('b', 60) . '.' . str_repeat('b', 60) . '.' .
            str_repeat('b', 60) . '.com';
        $this->assertSame(['email' => 'Invalid email address.'], $this->request('/users/save', ['email' => $email,
            'password' => 'lantern orbit 47', 'date_format' => 'Y-m-d', 'time_format' => 'H:i',
            'timezone' => 'Europe/Paris', 'roles' => []])['errors']);
    }

    /**
     * The admin can't log in as a user who can't log in.
     */
    public function testLoginAsNeedsLogin(): void
    {
        DB::table('role_permissions')->where('role_id', Role::USER)->delete();

        $answer = $this->request('/users/login-as', ['id' => User::find(2)->public_id]);
        $this->assertSame('You can\'t log in as an admin, a disabled user or a user who can\'t log in.',
            $answer['error']);
        $this->assertSame(1, Auth::id());
    }
}

<?php
namespace Tests\Models;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;
use Tests\Support\PostsController;
use Tests\Support\TestConfig;

#[CoversClass(User::class)]
class UserTest extends TestCase
{
    protected function tearDown(): void
    {
        session_status() === PHP_SESSION_ACTIVE && session_destroy();
        $_SESSION = [];
    }

    public function testIsAdmin(): void
    {
        TestConfig::load(['admins' => ' 1, 25 ,']);

        $this->assertTrue(User::isAdmin(1));
        $this->assertTrue(User::isAdmin(25));
        $this->assertFalse(User::isAdmin(2));
        $this->assertFalse(User::isAdmin(0));

        // the logged-in user; not a session's user that Auth didn't accept in the request (e.g. an ended session)
        Database::create();
        $this->assertFalse(User::isAdmin());
        Auth::logIn(User::forceCreate(['id' => 25, 'email' => 'a@example.com', 'password' => 'x', 'created_at' => 1]));
        $this->assertTrue(User::isAdmin());
        Auth::logIn(User::forceCreate(['id' => 2, 'email' => 'b@example.com', 'password' => 'x', 'created_at' => 1]));
        $this->assertFalse(User::isAdmin());
        $_SESSION['auth_user_id'] = 25;
        $this->assertFalse(User::isAdmin());
    }

    public function testNoAdmins(): void
    {
        TestConfig::load(['admins' => '1']);
        TestConfig::load();

        $this->assertFalse(User::isAdmin(1));
    }

    /**
     * A user has the permissions of role 1 (every user's) and of its roles; an admin has all of them.
     */
    public function testCan(): void
    {
        TestConfig::load(['admins' => '1']);
        PostsController::route();
        Database::create();
        foreach ([1, 2, 3] as $id) {
            User::forceCreate(['id' => $id, 'email' => "$id@example.com", 'password' => 'x', 'created_at' => 1]);
        }
        DB::table('roles')->insert(['id' => 2, 'name' => 'Editor']);
        DB::table('role_permissions')->insert(['role_id' => 2, 'permission' => 'posts.edit']);
        DB::table('user_roles')->insert(['user_id' => 3, 'role_id' => 2]);

        $this->assertTrue(User::can('posts.delete', 1));
        $this->assertTrue(User::can('login', 2));
        $this->assertFalse(User::can('posts.edit', 2));
        $this->assertTrue(User::can('login', 3));
        $this->assertTrue(User::can('posts.edit', 3));
        $this->assertFalse(User::can('posts.delete', 3));

        // the logged-in user, nobody when logged out
        $this->assertFalse(User::can('login'));
        Auth::logIn(User::find(3));
        $this->assertTrue(User::can('posts.edit'));
    }

    public function testCanUnknownPermission(): void
    {
        TestConfig::load(['admins' => '1']);
        PostsController::route();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown permission: posts.publish');
        User::can('posts.publish', 1);
    }
}

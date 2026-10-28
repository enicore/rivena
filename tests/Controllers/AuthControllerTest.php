<?php
namespace Tests\Controllers;

use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Controllers\AuthController;
use Enicore\Rivena\Models\Event;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\Totp;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Authenticator;
use Tests\Support\Database;
use Tests\Support\SmtpServer;

/**
 * The login's event log cleanup and second factor, and the password resets, through the app's routes, in SQLite.
 */
#[CoversClass(AuthController::class)]
#[CoversClass(App::class)]
class AuthControllerTest extends TestCase
{
    private const string MESSAGE = 'If the email has an account, a link to reset its password is on its way.';
    private const string ANSWER = '{"success":true,"data":{"message":"' . self::MESSAGE . '"}}';

    private string $root;
    private SmtpServer $smtp;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_project_' . uniqid();
        mkdir("$this->root/src", 0777, true);
        symlink(__DIR__ . '/../Fixtures/app/src/views', "$this->root/src/views");
        $this->smtp = new SmtpServer();
        $this->env("admins=2\npassword_min_length=10\nsite_url=https://example.org/\n");
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

    // the .env with the required values, the SMTP server and the lines
    private function env(string $lines): void
    {
        file_put_contents("$this->root/.env", "app_env=production\napp_key=key\nsite_name=Site\n" .
            "site_url=https://example.com\ndb_host=localhost\n" .
            "db_port=3306\ndb_database=db\ndb_username=user\ndb_password=pass\nsmtp_host=127.0.0.1\nsmtp_port=" .
            Config::get('smtp_port') . "\nmail_from=site@example.com\n$lines");
    }

    // the app, in SQLite with the users: 1 a user, 2 an admin, 3 a disabled user; users can reset their passwords
    private function app(): App
    {
        $app = new App($this->root);
        // ErrorLog's, which new App() sets
        restore_error_handler();
        Database::create();
        User::forceCreate(['id' => 1, 'email' => 'user@example.com', 'password' => 'x', 'created_at' => 1]);
        User::forceCreate(['id' => 2, 'email' => 'admin@example.com', 'password' => 'x', 'created_at' => 1]);
        User::forceCreate(['id' => 3, 'email' => 'off@example.com', 'password' => 'x', 'disabled' => 1,
            'created_at' => 1]);
        DB::table('role_permissions')->insert(['role_id' => 1, 'permission' => 'password_reset']);

        return $app;
    }

    // sends the request like LoginController (with the session's CSRF token); a body is posted as JSON
    private function request(App $app, string $path, ?array $body = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($body === null ? 'GET' : 'POST', "https://example.com/usr$path")
            ->withHeader('Accept', $body === null ? 'text/html' : 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-CSRF-Token', Session::getCsrfToken());
        $request->getBody()->write(json_encode($body));

        return $app->handle(Request::from($request));
    }

    // posts the email (or another value) to the forgot password page in a new process (forgot_password.php), whose end
    // sends the email; returns its output and its error log
    private function forgotPassword(mixed $email): array
    {
        $log = tempnam(sys_get_temp_dir(), 'rivena');
        exec(escapeshellarg(PHP_BINARY) . ' -d error_log=' . escapeshellarg($log) . ' ' .
            escapeshellarg(__DIR__ . '/../Fixtures/forgot_password.php') . ' ' . escapeshellarg($this->root) . ' ' .
            escapeshellarg(json_encode($email)), $output);
        $result = [implode("\n", $output), file_get_contents($log)];
        unlink($log);

        return $result;
    }

    private function json(ResponseInterface $response): array
    {
        return json_decode((string)$response->getBody(), true);
    }

    public function testEventLogCleanup(): void
    {
        $app = $this->app();
        Auth::setPassword(User::find(1), 'long password');
        $months = fn($months) => ['created_at' => strtotime("-$months months"), 'action' => "$months"];
        Event::insert([$months(7), $months(5)]);

        $this->assertTrue($this->json($this->request($app, '/login', ['email' => 'user@example.com',
            'password' => 'long password']))['success']);
        $this->assertSame(['5', 'auth.login'], Event::orderBy('id')->pluck('action')->all());

        // configurable
        $this->env("event_log_retention_months=4\n");
        $app = $this->app();
        Auth::setPassword(User::find(1), 'long password');
        Event::insert([$months(5), $months(3)]);
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        $this->assertSame(['3', 'auth.login'], Event::orderBy('id')->pluck('action')->all());
    }

    public function testLoginPageCsrf(): void
    {
        $app = $this->app();

        // an ended session (e.g. the password changed elsewhere): a new one, for the form's token
        Session::set('auth_user_id', 1);
        Session::set('auth_version', 5);
        $html = (string)$this->request($app, '/login')->getBody();
        $this->assertStringContainsString('name="csrf" value="' . Session::getCsrfToken() . '"', $html);

        // the SPA's JSON pages have it too
        foreach (['/login', '/forgot-password', '/reset-password/' . str_repeat('0', 64)] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', "https://example.com/usr$path")
                ->withHeader('Accept', 'application/json');
            $page = $this->json($app->handle(Request::from($request)));
            $this->assertStringContainsString('name="csrf" value="' . Session::getCsrfToken() . '"',
                $page['data']['html'], $path);
        }
    }

    /**
     * Without the "login" permission, users can't log in, admins can; the answer is the wrong password's.
     */
    public function testLoginPermission(): void
    {
        $app = $this->app();
        Auth::setPassword(User::find(1), 'long password');
        Auth::setPassword(User::find(2), 'long password');
        DB::table('role_permissions')->delete();

        $response = $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Invalid email or password.'], $this->json($response));
        $this->assertNull(Auth::id());
        $this->assertSame('auth.login_not_allowed', Event::value('action'));

        $response = $this->request($app, '/login', ['email' => 'admin@example.com', 'password' => 'long password']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, Auth::id());
    }

    /**
     * A disabled user's right password gets the wrong one's answer (it would tell the password), the reason logged.
     */
    public function testLoginDisabled(): void
    {
        $app = $this->app();
        Auth::setPassword(User::find(3), 'long password');

        $wrong = $this->request($app, '/login', ['email' => 'off@example.com', 'password' => 'wrong password']);
        $right = $this->request($app, '/login', ['email' => 'off@example.com', 'password' => 'long password']);
        $this->assertSame([401, (string)$wrong->getBody()], [$right->getStatusCode(), (string)$right->getBody()]);
        $this->assertNull(Auth::id());
        $this->assertSame(['auth.login_failed', 'auth.login_disabled'], Event::orderBy('id')->pluck('action')->all());
    }

    /**
     * Without a role with the password_reset permission, no link and no page.
     */
    public function testPasswordResetsOff(): void
    {
        $app = $this->app();
        DB::table('role_permissions')->where('permission', 'password_reset')->delete();

        $this->assertStringNotContainsString('Forgot password', (string)$this->request($app, '/login')->getBody());
        $this->assertSame(404, $this->request($app, '/forgot-password')->getStatusCode());
        $this->assertSame(404, $this->request($app, '/forgot-password', ['email' => 'user@example.com'])
            ->getStatusCode());
        $this->assertSame(0, DB::table('password_resets')->count());
    }


    public function testSendPasswordReset(): void
    {
        $app = $this->app();
        $this->assertStringContainsString('Forgot password?', (string)$this->request($app, '/login')->getBody());
        $this->assertStringContainsString('Send link', (string)$this->request($app, '/forgot-password')->getBody());

        // the same answer, with its length, and no email: unknown emails, admins, disabled users, not an email (the
        // users who can't reset, or got a link in the last 5 minutes: Auth::createPasswordReset())
        foreach (['nobody@example.com', 'admin@example.com', 'off@example.com', ['a']] as $email) {
            [$output, $log] = $this->forgotPassword($email);
            $this->assertSame(strlen(self::ANSWER) . "\n" . self::ANSWER, $output);
            $this->assertSame('', $log);
        }
        $this->assertCount(0, $this->smtp->emails());
    }

    /**
     * The email is sent after the answer, when the request's process ends.
     */
    public function testSendPasswordResetEmail(): void
    {
        [$output, $log] = $this->forgotPassword('user@example.com');

        $this->assertSame(strlen(self::ANSWER) . "\n" . self::ANSWER, $output);
        $this->assertCount(1, $this->smtp->emails());
        $email = $this->smtp->emails()[0];
        $this->assertStringContainsString("To: user@example.com\r\n", $email);
        $this->assertStringContainsString("Subject: Reset your password\r\n", $email);
        $this->assertStringContainsString('your account at Site (user@example.com)', $email);
        $this->assertMatchesRegularExpression('#\r\nhttps://example\.org/usr/reset-password/[0-9a-f]{64}\r\n#',
            $email);
        $this->assertSame('', $log);
    }

    /**
     * A failed email is logged; the answer is the same.
     */
    public function testSendPasswordResetMailFailure(): void
    {
        $this->env("site_url=https://example.org\nsmtp_port=1\n");
        [$output, $log] = $this->forgotPassword('user@example.com');

        $this->assertSame(strlen(self::ANSWER) . "\n" . self::ANSWER, $output);
        $this->assertStringContainsString('Password reset email: SMTP Error', $log);
    }

    public function testResetPassword(): void
    {
        $app = $this->app();
        $token = Auth::createPasswordReset(User::find(1));
        $reset = fn($values) => $this->json($this->request($app, '/reset-password', $values));

        // with the account's email, for the password managers and the password's strength
        $page = (string)$this->request($app, "/reset-password/$token")->getBody();
        $this->assertStringContainsString("name=\"token\" value=\"$token\"", $page);
        $this->assertStringContainsString('value="user@example.com" autocomplete="username"', $page);
        $this->assertStringNotContainsString('This link is invalid or expired.', $page);
        $page = (string)$this->request($app, '/reset-password/' . str_repeat('0', 64))->getBody();
        $this->assertStringContainsString('This link is invalid or expired.', $page);

        $this->assertSame('This link is invalid or expired.',
            $reset(['token' => str_repeat('0', 64), 'password' => 'lantern orbit 47'])['error']);
        $this->assertSame('Use at least 10 characters.', $reset(['token' => $token, 'password' => 'short'])['error']);
        $this->assertSame('Too weak. This is similar to a commonly used password.',
            $reset(['token' => $token, 'password' => 'new password'])['error']);
        $this->assertSame('Invalid request.', $reset(['token' => $token])['error']);
        Auth::setPassword(User::find(1), 'lantern orbit 47');
        $token = Auth::createPasswordReset(User::find(1));
        $this->assertSame('Use a different password than the current one.',
            $reset(['token' => $token, 'password' => 'lantern orbit 47'])['error']);

        $this->assertSame(['success' => true, 'data' => ['message' => 'Your password is changed.']],
            $reset(['token' => $token, 'password' => 'jade orbit 47']));
        $this->assertTrue(password_verify('jade orbit 47', User::find(1)->password));
        $this->assertSame(['auth.password_reset', 1], [Event::first()->action, (int)Event::first()->user_id]);

        // once
        $this->assertSame('This link is invalid or expired.',
            $reset(['token' => $token, 'password' => 'orbit lantern 7'])['error']);
    }

    /**
     * The strength of a password as it's typed, for everyone (the reset page), with the form's email.
     */
    public function testPasswordStrength(): void
    {
        $app = $this->app();
        $strength = fn($values) => $this->json($this->request($app, '/password-strength', $values))['data'];

        $this->assertSame(['score' => 4, 'error' => null],
            $strength(['password' => 'lantern orbit 47', 'email' => 'user@example.com']));
        $this->assertSame(['score' => 0, 'error' => 'Too weak. Repeats like "abcabcabc" are only slightly harder to ' .
            'guess than "abc".'], $strength(['password' => 'abcabcabcabc', 'email' => '']));
        $this->assertLessThan(3, $strength(['password' => 'johnsmith1983', 'email' => 'johnsmith1983@example.com'])
            ['score']);
    }

    // user 1 with a password and TOTP (the secret of RFC 6238's tests); returns the recovery codes
    private function twoFactorUser(): array
    {
        time() % 30 === 29 && sleep(1);
        Auth::setPassword(User::find(1), 'long password');

        return TwoFactor::enable(1, 'totp', ['secret' => Totp::base32Encode('12345678901234567890')]);
    }

    private function totpCode(int $steps = 0): string
    {
        return Totp::code('12345678901234567890', intdiv(time(), 30) + $steps);
    }

    // the password step, then the code's
    private function logInTwoFactor(App $app, string $method, string $code, bool $remember = false): ResponseInterface
    {
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password',
            'remember' => $remember]);

        return $this->request($app, '/login/two-factor', ['method' => $method, 'code' => $code]);
    }

    /**
     * After the password, a user with two-factor authentication gets the methods, and isn't logged in until the code.
     */
    public function testLoginTwoFactor(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $response = $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);

        $this->assertSame(['success' => true, 'data' => ['twoFactor' => [
            ['id' => 'totp', 'name' => 'Authenticator app'], ['id' => 'recovery', 'name' => 'Recovery codes']]]],
            $this->json($response));
        $this->assertNull(Auth::id());
        $this->assertSame([], Event::pluck('action')->all());

        // a wrong code
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode(5)]);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Invalid code.', $this->json($response)['error']);
        $this->assertNull(Auth::id());
        $this->assertSame(['auth.two_factor_failed'], Event::pluck('action')->all());
        $this->assertSame(1, Event::value('user_id'));

        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);
        $this->assertSame(['success' => true, 'data' => ['redirect' => '/usr']], $this->json($response));
        $this->assertSame(1, Auth::id());
        $this->assertSame(['auth.two_factor_failed', 'auth.login'], Event::orderBy('id')->pluck('action')->all());
        $this->assertArrayNotHasKey(Auth::COOKIE, $_COOKIE);
    }

    /**
     * Without two-factor authentication, the password logs in.
     */
    public function testLoginWithoutTwoFactor(): void
    {
        $app = $this->app();
        Auth::setPassword(User::find(1), 'long password');

        $this->assertSame(['success' => true, 'data' => ['redirect' => '/usr']], $this->json($this->request($app,
            '/login', ['email' => 'user@example.com', 'password' => 'long password'])));
        $this->assertSame(1, Auth::id());
    }

    /**
     * "Remember me" waits for the code; its cookie logs in later without the code.
     */
    public function testLoginTwoFactorRemember(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $this->logInTwoFactor($app, 'totp', $this->totpCode(), true);

        $this->assertSame(1, Auth::id());
        $this->assertArrayHasKey(Auth::COOKIE, $_COOKIE);
        $this->assertSame('Remembered', Event::where('action', 'auth.login')->value('message'));

        $_SESSION = [];
        $this->assertTrue(Auth::check());
        $this->assertSame(1, Auth::id());
    }

    /**
     * A recovery code logs in to the account page's two-factor tab, which shows the codes left.
     */
    public function testLoginRecoveryCode(): void
    {
        $app = $this->app();
        $codes = $this->twoFactorUser();
        $response = $this->logInTwoFactor($app, 'recovery', strtolower($codes[2]));

        $this->assertSame(['success' => true, 'data' => ['redirect' => '/usr/account?tab=two-factor']],
            $this->json($response));
        $this->assertSame(1, Auth::id());
        $this->assertSame('Recovery code', Event::where('action', 'auth.login')->value('message'));
        $this->assertCount(9, TwoFactor::userMethods(1)['recovery']['codes']);

        // once
        Auth::logOut();
        $this->assertSame(401, $this->logInTwoFactor($app, 'recovery', $codes[2])->getStatusCode());
    }

    /**
     * Without the password step, or after it expired, the login starts again.
     */
    public function testLoginTwoFactorRestart(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue($this->json($response)['restart']);

        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        $_SESSION['auth_two_factor']['time'] -= Auth::TWO_FACTOR_DURATION + 1;
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);
        $this->assertTrue($this->json($response)['restart']);
        $this->assertNull(Auth::id());
    }

    /**
     * A password change between the password and the code ends the login.
     */
    public function testLoginTwoFactorAfterPasswordChange(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        $pending = $_SESSION['auth_two_factor'];
        Auth::setPassword(User::find(1), 'another long password');
        $_SESSION['auth_two_factor'] = $pending;
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertTrue($this->json($response)['restart']);
        $this->assertNull(Auth::id());
    }

    /**
     * Too many wrong codes are throttled like wrong passwords.
     */
    public function testLoginTwoFactorThrottled(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);

        for ($i = 0; $i < 5; $i++) {
            $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode(5)]);
        }

        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('auth.login_throttled', Event::orderByDesc('id')->value('action'));
        $this->assertNull(Auth::id());
    }

    /**
     * A user disabled, or without the login permission, between the password and the code isn't logged in.
     */
    public function testLoginTwoFactorRefused(): void
    {
        $app = $this->app();
        $this->twoFactorUser();
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        User::where('id', 1)->update(['disabled' => 1]);
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode()]);

        // back to the password
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['success' => false, 'error' => 'Invalid email or password.', 'restart' => true],
            $this->json($response));
        $this->assertNull(Auth::id());

        User::where('id', 1)->update(['disabled' => 0]);
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        DB::table('role_permissions')->where('permission', 'login')->delete();
        $response = $this->request($app, '/login/two-factor', ['method' => 'totp', 'code' => $this->totpCode(1)]);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertNull(Auth::id());
    }

    /**
     * A security key: the login's challenge (in the session), then the key's signature as the code.
     */
    public function testLoginSecurityKey(): void
    {
        $this->env("admins=2\nsite_url=https://example.org/\ntwo_factor_methods=webauthn,totp\n");
        $app = $this->app();
        $this->twoFactorUser();
        $key = new Authenticator();
        $user = User::find(1);
        $setup = TwoFactor::method('webauthn')->setUp($user, null);
        TwoFactor::enable(1, 'webauthn', TwoFactor::method('webauthn')->verify($setup['data'],
            $key->create($setup['page']['options'], '', 'https://example.org')));

        // no login waits
        $this->assertTrue($this->json($this->request($app, '/login/two-factor/challenge',
            ['method' => 'webauthn']))['restart']);

        $answer = $this->json($this->request($app, '/login', ['email' => 'user@example.com',
            'password' => 'long password']));
        $this->assertSame(['webauthn', 'totp', 'recovery'], array_column($answer['data']['twoFactor'], 'id'));

        // TOTP has no challenge
        $this->assertSame(400, $this->request($app, '/login/two-factor/challenge', ['method' => 'totp'])
            ->getStatusCode());

        $options = $this->json($this->request($app, '/login/two-factor/challenge', ['method' => 'webauthn']))
            ['data']['options'];

        // the challenge is the session's: another login's (someone else with the password) doesn't replace it
        $session = $_SESSION;
        $_SESSION = [];
        $this->request($app, '/login', ['email' => 'user@example.com', 'password' => 'long password']);
        $this->request($app, '/login/two-factor/challenge', ['method' => 'webauthn']);
        $_SESSION = $session;
        $this->assertArrayNotHasKey('challenge', TwoFactor::userMethods(1)['webauthn']);

        $response = $this->request($app, '/login/two-factor', ['method' => 'webauthn',
            'code' => $key->get($options, 'https://example.org')]);

        $this->assertSame(['success' => true, 'data' => ['redirect' => '/usr']], $this->json($response));
        $this->assertSame(1, Auth::id());
    }
}

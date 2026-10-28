<?php
namespace Tests\Controllers;

use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Controllers\TwoFactorController;
use Enicore\Rivena\Controllers\UsersController;
use Enicore\Rivena\Middleware\AuthMiddleware;
use Enicore\Rivena\Models\Event;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\RecoveryCodes;
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
 * The user page's two-factor authentication tab, the admins' reset, and two_factor_required, through the app's routes,
 * in SQLite.
 */
#[CoversClass(TwoFactorController::class)]
#[CoversClass(UsersController::class)]
#[CoversClass(AuthMiddleware::class)]
class TwoFactorControllerTest extends TestCase
{
    private string $root;
    private App $app;
    private SmtpServer $smtp;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_project_' . uniqid();
        mkdir("$this->root/src", 0777, true);
        symlink(__DIR__ . '/../Fixtures/app/src/views', "$this->root/src/views");
        $this->smtp = new SmtpServer();
        $this->env('');
        time() % 30 === 29 && sleep(1);
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

    // the app with the .env's lines and the SMTP server, in SQLite: 1 the admin, 2 a user, both with the password
    // "long password"; the user logs in; TOTP only, unless the lines set two_factor_methods
    private function env(string $lines, int $userId = 2): void
    {
        file_put_contents("$this->root/.env", "app_env=production\napp_key=key\nsite_name=Site\n" .
            "site_url=https://example.com\ndb_host=localhost\n" .
            "db_port=3306\ndb_database=db\ndb_username=user\ndb_password=pass\nadmins=1\nsmtp_host=127.0.0.1\n" .
            "smtp_port=" . Config::get('smtp_port') . "\nmail_from=site@example.com\ntwo_factor_methods=totp\n$lines");
        $this->app = new App($this->root);
        // ErrorLog's, which new App() sets
        restore_error_handler();
        Database::create();

        foreach ([1 => 'admin@example.com', 2 => 'user@example.com'] as $id => $email) {
            Auth::setPassword(User::forceCreate(['id' => $id, 'email' => $email, 'password' => 'x', 'created_at' => 1]),
                'long password');
        }

        Auth::logIn(User::find($userId));
    }

    private function request(string $path, ?array $body = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest($body === null ? 'GET' : 'POST', "https://example.com/usr$path")
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-CSRF-Token', Session::getCsrfToken());
        $request->getBody()->write(json_encode($body));

        return $this->app->handle(Request::from($request));
    }

    private function json(string $path, ?array $body = null): array
    {
        return json_decode((string)$this->request($path, $body)->getBody(), true);
    }

    // posts to the account's two-factor action with the right password
    private function action(string $action, array $body = []): array
    {
        return $this->json("/account/two-factor/$action", $body + ['password' => 'long password']);
    }

    // the logged-in user sets up TOTP; returns enable()'s answer and the secret
    private function setUpTotp(): array
    {
        $secret = str_replace(' ', '', $this->action('setup', ['method' => 'totp'])['data']['secret']);
        $answer = $this->action('enable', ['code' => $this->code($secret)]);

        return [$answer, $secret];
    }

    private function code(string $secret, int $steps = 0): string
    {
        return Totp::code(Totp::base32Decode($secret), intdiv(time(), 30) + $steps);
    }

    private function state(bool $enabled, ?int $recoveryCodes, bool $required = false): array
    {
        return ['methods' => [['id' => 'totp', 'name' => 'Authenticator app',
            'description' => 'Codes from an app like Google Authenticator, Microsoft Authenticator or Aegis.',
            'enabled' => $enabled, 'keys' => null]], 'recoveryCodes' => $recoveryCodes, 'required' => $required];
    }

    /**
     * The account page has the two-factor tab, opened by ?tab=two-factor.
     */
    public function testAccountPage(): void
    {
        $data = $this->json('/account')['data']['data'];
        $this->assertSame($this->state(false, null), $data['twoFactor']);
        $this->assertSame('account', $data['tab']);
        $this->assertTrue($data['own']);

        $this->assertSame('two-factor', $this->json('/account?tab=two-factor')['data']['data']['tab']);
        $this->assertSame('account', $this->json('/account?tab=other')['data']['data']['tab']);

        // without methods, no tab
        $this->env("two_factor_methods=\n");
        $this->assertNull($this->json('/account')['data']['data']['twoFactor']);
    }

    /**
     * An admin sees another user's state, and a new user has none.
     */
    public function testUserPage(): void
    {
        $this->env('', 1);
        TwoFactor::enable(2, 'totp', ['secret' => 'AAAA']);
        $data = $this->json('/users/' . User::find(2)->public_id . '/edit')['data']['data'];

        $this->assertSame($this->state(true, RecoveryCodes::COUNT), $data['twoFactor']);
        $this->assertFalse($data['own']);
        $this->assertNull($this->json('/users/add')['data']['data']['twoFactor']);
    }

    /**
     * The setup shows the QR code and the secret, and saves the method when the code is right; the first method brings
     * the recovery codes.
     */
    public function testSetUp(): void
    {
        $setup = $this->action('setup', ['method' => 'totp'])['data'];
        $this->assertSame(['qr', 'secret'], array_keys($setup));
        $this->assertStringStartsWith('<svg', $setup['qr']);
        $this->assertFalse(TwoFactor::enabled(2));

        // a wrong code
        $secret = str_replace(' ', '', $setup['secret']);
        $answer = $this->action('enable', ['code' => $this->code($secret, 5)]);
        $this->assertSame(['code' => 'Wrong code. Check the code and try again.'], $answer['errors']);
        $this->assertFalse(TwoFactor::enabled(2));

        $answer = $this->action('enable', ['code' => $this->code($secret)]);
        $this->assertSame($this->state(true, RecoveryCodes::COUNT), $answer['data']['twoFactor']);
        $this->assertSame(TwoFactor::userMethods(2)['recovery']['codes'], $answer['data']['codes']);
        $this->assertSame($secret, TwoFactor::userMethods(2)['totp']['secret']);
        $this->assertSame([['account.two_factor_enabled', 'Authenticator app', 2]],
            Event::get()->map(fn($event) => [$event->action, $event->message, $event->user_id])->all());

        // the setup's code doesn't log in again
        $this->assertFalse(TwoFactor::verify(2, 'totp', $this->code($secret)));

        // once
        $this->assertSame('The setup expired. Start it again.',
            $this->action('enable', ['code' => $this->code($secret, 1)])['error']);
    }

    public function testSetUpErrors(): void
    {
        // the password
        $answer = $this->action('setup', ['method' => 'totp', 'password' => 'wrong']);
        $this->assertSame(['password' => 'Wrong password.'], $answer['errors']);
        $this->assertSame(1, DB::table('login_attempts')->count());
        $answer = $this->json('/account/two-factor/setup', ['method' => 'totp']);
        $this->assertSame(['password' => 'Wrong password.'], $answer['errors']);

        // unknown, recovery, already set up
        foreach (['unknown', 'recovery', ''] as $method) {
            $this->assertSame('This method can\'t be set up.', $this->action('setup', ['method' => $method])['error']);
        }

        $this->setUpTotp();
        $this->assertSame('This method can\'t be set up.', $this->action('setup', ['method' => 'totp'])['error']);

        // enable without a setup, or too late
        $this->assertSame('The setup expired. Start it again.', $this->action('enable', ['code' => '123456'])['error']);
        TwoFactor::reset(2);
        $this->action('setup', ['method' => 'totp']);
        $_SESSION['two_factor_setup']['time'] -= 901;
        $this->assertSame('The setup expired. Start it again.', $this->action('enable', ['code' => '123456'])['error']);
    }

    /**
     * Too many wrong passwords are throttled like logins.
     */
    public function testPasswordThrottled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->action('setup', ['method' => 'totp', 'password' => 'wrong']);
        }

        $this->assertSame(['password' => 'Too many login attempts. Try again later.'],
            $this->action('setup', ['method' => 'totp'])['errors']);
    }

    /**
     * Removing the last method takes the recovery codes with it.
     */
    public function testDisable(): void
    {
        $this->setUpTotp();
        $this->assertSame(['password' => 'Wrong password.'],
            $this->action('disable', ['method' => 'totp', 'password' => 'wrong'])['errors']);
        $this->assertSame('This method isn\'t set up.', $this->action('disable', ['method' => 'recovery'])['error']);
        $this->assertSame('This method isn\'t set up.', $this->action('disable', ['method' => 'unknown'])['error']);

        $answer = $this->action('disable', ['method' => 'totp']);
        $this->assertSame($this->state(false, null), $answer['data']['twoFactor']);
        $this->assertSame(0, DB::table('user_two_factor')->count());
        $this->assertSame('account.two_factor_disabled', Event::orderByDesc('id')->value('action'));
        $this->assertSame('This method isn\'t set up.', $this->action('disable', ['method' => 'totp'])['error']);
    }

    public function testTurnOff(): void
    {
        $this->setUpTotp();
        $this->assertSame(['password' => 'Wrong password.'],
            $this->action('turn-off', ['password' => 'wrong'])['errors']);

        $this->assertSame($this->state(false, null), $this->action('turn-off')['data']['twoFactor']);
        $this->assertSame(0, DB::table('user_two_factor')->count());
        $this->assertSame('account.two_factor_turned_off', Event::orderByDesc('id')->value('action'));
    }

    /**
     * The recovery codes are shown with the password; renewed, the old ones stop working.
     */
    public function testRecoveryCodes(): void
    {
        $this->assertSame('Two-factor authentication is off.', $this->action('recovery-codes')['error']);
        $codes = $this->setUpTotp()[0]['data']['codes'];

        $this->assertSame(['password' => 'Wrong password.'],
            $this->action('recovery-codes', ['password' => 'wrong'])['errors']);
        $this->assertSame($codes, $this->action('recovery-codes')['data']['codes']);
        TwoFactor::verify(2, 'recovery', $codes[0]);
        $answer = $this->action('recovery-codes')['data'];
        $this->assertSame(array_slice($codes, 1), $answer['codes']);
        $this->assertSame(RecoveryCodes::COUNT - 1, $answer['twoFactor']['recoveryCodes']);

        $answer = $this->action('recovery-codes', ['renew' => true])['data'];
        $this->assertNotSame($codes, $answer['codes']);
        $this->assertSame(RecoveryCodes::COUNT, $answer['twoFactor']['recoveryCodes']);
        $this->assertFalse(TwoFactor::verify(2, 'recovery', $codes[1]));
        $this->assertSame('account.two_factor_recovery_renewed', Event::orderByDesc('id')->value('action'));
    }

    /**
     * An admin turns another user's two-factor authentication off, which ends the user's sessions; the users can't.
     */
    public function testReset(): void
    {
        TwoFactor::enable(1, 'totp', ['secret' => 'AAAA']);
        $this->setUpTotp();
        $this->assertSame(403, $this->request('/users/two-factor-reset', ['id' => User::find(1)->public_id])
            ->getStatusCode());
        $this->assertTrue(TwoFactor::enabled(1));

        $this->env('', 1);
        TwoFactor::enable(2, 'totp', ['secret' => 'AAAA']);

        // with the admin's password
        $answer = $this->json('/users/two-factor-reset', ['id' => User::find(2)->public_id, 'password' => 'wrong']);
        $this->assertSame(['password' => 'Wrong password.'], $answer['errors']);
        $this->assertTrue(TwoFactor::enabled(2));

        $version = User::find(2)->session_version;
        $answer = $this->json('/users/two-factor-reset', ['id' => User::find(2)->public_id,
            'password' => 'long password']);

        $this->assertSame($this->state(false, null), $answer['data']['twoFactor']);
        $this->assertFalse(TwoFactor::enabled(2));
        $this->assertSame($version + 1, User::find(2)->session_version);
        $this->assertSame(['user.two_factor_reset', 'user@example.com', 1],
            [Event::value('action'), Event::value('message'), Event::value('user_id')]);
        $this->assertSame(404, $this->request('/users/two-factor-reset', ['id' => 'unknown',
            'password' => 'long password'])->getStatusCode());

        // already off: nothing changes
        $answer = $this->json('/users/two-factor-reset', ['id' => User::find(2)->public_id,
            'password' => 'long password']);
        $this->assertSame('Two-factor authentication is already off.', $answer['error']);
        $this->assertSame($version + 1, User::find(2)->session_version);
        $this->assertSame(1, Event::where('action', 'user.two_factor_reset')->count());
    }

    /**
     * Each change emails the user, unless two_factor_email_notifications is off; seeing the recovery codes doesn't.
     */
    public function testChangeEmails(): void
    {
        $this->setUpTotp();
        $this->action('recovery-codes');
        $this->action('recovery-codes', ['renew' => true]);
        $this->action('disable', ['method' => 'totp']);
        $this->setUpTotp();
        $this->action('turn-off');
        $this->env('', 1);
        TwoFactor::enable(2, 'totp', ['secret' => 'AAAA']);
        $this->json('/users/two-factor-reset', ['id' => User::find(2)->public_id, 'password' => 'long password']);

        $emails = $this->smtp->emails();
        $this->assertCount(6, $emails);
        $this->assertStringContainsString("To: user@example.com\r\n", $emails[0]);
        $this->assertStringContainsString("Subject: Two-factor authentication changed\r\n", $emails[0]);
        $this->assertStringContainsString('On your account at Site (user@example.com), two-factor authentication was ' .
            'turned on (Authenticator app).', $emails[0]);
        $this->assertStringContainsString('new recovery codes were made', $emails[1]);
        $this->assertStringContainsString('a two-factor method was removed (Authenticator app)', $emails[2]);
        $this->assertStringContainsString('two-factor authentication was turned off.', $emails[4]);
        $this->assertStringContainsString('an administrator turned two-factor authentication off', $emails[5]);

        $this->env("two_factor_email_notifications=false\n");
        $this->setUpTotp();
        $this->action('turn-off');
        $this->assertCount(6, $this->smtp->emails());
    }

    /**
     * With two_factor_required, a user without it only gets the account page and its actions; the last method can't
     * be removed, nor turned off.
     */
    public function testRequired(): void
    {
        $this->env("two_factor_required=true\n");

        // pages redirect, JSON requests get a 403 with the URL
        $response = $this->app->handle((new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/usr/users'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/usr/account?tab=two-factor', $response->getHeaderLine('Location'));
        $response = $this->request('/users');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('/usr/account?tab=two-factor', json_decode((string)$response->getBody(), true)['redirect']);

        $this->assertTrue($this->json('/account')['data']['data']['twoFactor']['required']);
        $this->setUpTotp();

        // set up: the other pages (the users page is the admins')
        $this->assertSame(['success' => false, 'error' => 'Access denied.'], $this->json('/users'));

        $this->assertSame('Two-factor authentication is required: set up another method first.',
            $this->action('disable', ['method' => 'totp'])['error']);
        $this->assertSame('Two-factor authentication is required.', $this->action('turn-off')['error']);
        $this->assertTrue(TwoFactor::enabled(2));
    }

    /**
     * The admins need it too when it's required.
     */
    public function testRequiredForAdmins(): void
    {
        $this->env("two_factor_required=true\n", 1);

        $this->assertSame(403, $this->request('/users')->getStatusCode());
        TwoFactor::enable(1, 'totp', ['secret' => 'AAAA']);
        $this->assertSame(200, $this->request('/users')->getStatusCode());
    }

    /**
     * Turning it on ends the user's other sessions and remember cookies, not the current session; another method
     * doesn't.
     */
    public function testEnableEndsOtherSessions(): void
    {
        DB::table('user_remember_tokens')->insert(['selector' => 's', 'user_id' => 2, 'hash' => 'x',
            'session_version' => 0, 'expires_at' => time() + 60]);
        $version = User::find(2)->session_version;
        $this->setUpTotp();

        $this->assertSame($version + 1, User::find(2)->session_version);
        $this->assertSame(0, DB::table('user_remember_tokens')->count());
        $this->assertSame(200, $this->request('/account')->getStatusCode());
        $this->assertSame(2, Auth::id());

        $this->env("site_url=https://example.com\ntwo_factor_methods=totp,webauthn\n");
        $this->setUpTotp();
        $version = User::find(2)->session_version;
        $this->addKey(new Authenticator());
        $this->assertSame($version, User::find(2)->session_version);
    }

    /**
     * A setup is the user's who started it.
     */
    public function testSetUpIsTheUsers(): void
    {
        $setup = $this->action('setup', ['method' => 'totp'])['data'];
        $_SESSION['two_factor_setup']['user_id'] = 1;
        $answer = $this->action('enable', ['code' => $this->code(str_replace(' ', '', $setup['secret']))]);

        $this->assertSame('The setup expired. Start it again.', $answer['error']);
        $this->assertFalse(TwoFactor::enabled(2));
        $this->assertFalse(TwoFactor::enabled(1));
    }

    /**
     * A method no longer in the config is still listed for its users, who can remove it, but not set it up again.
     */
    public function testMethodNoLongerConfigured(): void
    {
        $this->setUpTotp();
        $this->env("two_factor_methods=\n");
        TwoFactor::enable(2, 'totp', ['secret' => 'AAAA']);

        $this->assertSame($this->state(true, RecoveryCodes::COUNT),
            $this->json('/account')['data']['data']['twoFactor']);
        $this->assertSame('This method can\'t be set up.', $this->action('setup', ['method' => 'totp'])['error']);
        $this->assertSame(['methods' => [], 'recoveryCodes' => null, 'required' => false],
            $this->action('disable', ['method' => 'totp'])['data']['twoFactor']);
    }

    /**
     * With two_factor_required, only Rivena's account routes are allowed without it, not the app's named like them.
     */
    public function testRequiredOnlyRivenasAccountRoutes(): void
    {
        $this->env("two_factor_required=true\n");
        $this->app->get('/usr/account/billing', fn($request, $response) => $response)->setName('account.billing')
            ->add(AuthMiddleware::class);

        $this->assertSame(403, $this->request('/account/billing')->getStatusCode());
        $this->assertSame(200, $this->request('/account')->getStatusCode());
    }

    // with WebAuthn: sets up the key; returns enable()'s answer
    private function addKey(Authenticator $key, string $name = ''): array
    {
        $options = $this->action('setup', ['method' => 'webauthn'])['data']['options'];

        return $this->action('enable', ['code' => $key->create($options, $name)]);
    }

    /**
     * Security keys: set up one at a time, listed, removed one at a time; the last one takes the method.
     */
    public function testWebAuthn(): void
    {
        $this->env("site_url=https://example.com\ntwo_factor_methods=webauthn,totp\n");
        $first = new Authenticator();
        $second = new Authenticator();

        $this->assertSame([], $this->json('/account')['data']['data']['twoFactor']['methods'][0]['keys']);
        $this->assertCount(RecoveryCodes::COUNT, $this->addKey($first, 'First')['data']['codes']);
        $answer = $this->addKey($second);
        $this->assertNull($answer['data']['codes']);
        $this->assertSame([['id' => Authenticator::encode($first->id), 'name' => 'First'],
            ['id' => Authenticator::encode($second->id), 'name' => 'Security key 2']],
            $answer['data']['twoFactor']['methods'][0]['keys']);

        // the same key again: no change
        $this->assertCount(2, $this->addKey($first)['data']['twoFactor']['methods'][0]['keys']);

        $this->assertSame('This key isn\'t set up.', $this->action('disable', ['method' => 'webauthn',
            'key' => 'unknown'])['error']);
        $answer = $this->action('disable', ['method' => 'webauthn', 'key' => Authenticator::encode($first->id)]);
        $this->assertSame([['id' => Authenticator::encode($second->id), 'name' => 'Security key 2']],
            $answer['data']['twoFactor']['methods'][0]['keys']);
        $this->assertSame(['account.two_factor_key_removed', 'Security key or passkey: First'],
            [Event::orderByDesc('id')->value('action'), Event::orderByDesc('id')->value('message')]);

        $answer = $this->action('disable', ['method' => 'webauthn', 'key' => Authenticator::encode($second->id)]);
        $this->assertFalse($answer['data']['twoFactor']['methods'][0]['enabled']);
        $this->assertSame(0, DB::table('user_two_factor')->count());
    }

    /**
     * A setup adds its key to the keys of when it's finished: a key removed meanwhile (or after a reset) doesn't come
     * back.
     */
    public function testWebAuthnChangedDuringSetup(): void
    {
        $this->env("site_url=https://example.com\ntwo_factor_methods=webauthn\n");
        $first = new Authenticator();
        $this->addKey($first, 'First');
        $options = $this->action('setup', ['method' => 'webauthn'])['data']['options'];
        TwoFactor::reset(2);

        $second = new Authenticator();
        $answer = $this->action('enable', ['code' => $second->create($options, 'Second')]);
        $this->assertSame([['id' => Authenticator::encode($second->id), 'name' => 'Second']],
            $answer['data']['twoFactor']['methods'][0]['keys']);
        // new recovery codes, as the first method
        $this->assertCount(RecoveryCodes::COUNT, $answer['data']['codes']);
    }

    /**
     * With two_factor_required, the last key can't be removed; one of several can.
     */
    public function testWebAuthnRequired(): void
    {
        $this->env("site_url=https://example.com\ntwo_factor_methods=webauthn\ntwo_factor_required=true\n");
        $first = new Authenticator();
        $this->addKey($first);
        $this->addKey(new Authenticator());

        $this->assertTrue($this->action('disable', ['method' => 'webauthn',
            'key' => Authenticator::encode($first->id)])['success']);
        $keys = TwoFactor::method('webauthn')->keys(TwoFactor::userMethods(2)['webauthn']);
        $this->assertSame('Two-factor authentication is required: set up another method first.',
            $this->action('disable', ['method' => 'webauthn', 'key' => array_key_first($keys)])['error']);
    }
}

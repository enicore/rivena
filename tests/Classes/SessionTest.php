<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Session;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Rivena\Classes\Session::class)]
class SessionTest extends TestCase
{
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        session_id('');
    }

    public function testStart(): void
    {
        Session::start();
        $_SESSION['key'] = 'value';

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $params = session_get_cookie_params();
        $this->assertTrue($params['secure']);
        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame(0, $params['lifetime']);
        $this->assertSame('/', $params['path']);
        // only from this host (a subdomain can't set it)
        $this->assertSame('__Host-session', session_name());
        $this->assertSame('', $params['domain']);

        // an active session isn't replaced
        Session::start();
        $this->assertSame(['key' => 'value'], $_SESSION);
    }

    public function testValues(): void
    {
        $this->assertFalse(Session::has('key'));
        $this->assertSame('default', Session::get('key', 'default'));

        Session::set('key', 'value');
        Session::set('null', null);
        $this->assertTrue(Session::has('key'));
        $this->assertSame('value', Session::get('key'));
        $this->assertTrue(Session::has('null'));

        Session::remove('key');
        $this->assertFalse(Session::has('key'));
    }

    public function testRegenerate(): void
    {
        Session::set('key', 'value');
        $id = session_id();
        Session::regenerate();

        $this->assertNotSame($id, session_id());
        $this->assertSame('value', Session::get('key'));
    }

    public function testDestroy(): void
    {
        Session::set('key', 'value');
        Session::destroy();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame([], $_SESSION);
    }

    public function testGetCsrfToken(): void
    {
        $token = Session::getCsrfToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Session::getCsrfToken());
        $this->assertSame($token, Session::get('csrf'));
    }

    public function testRejectsUnknownSessionId(): void
    {
        // strict mode: a session id the server didn't create (session fixation) is replaced
        session_id('attackerchosenid123456');
        Session::start();

        $this->assertNotSame('attackerchosenid123456', session_id());
    }
}

<?php
namespace Tests\TwoFactor;

use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\WebAuthn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Authenticator;
use Tests\Support\TestConfig;

/**
 * Security keys, with a software authenticator (Tests\Support\Authenticator).
 */
#[CoversClass(WebAuthn::class)]
class WebAuthnTest extends TestCase
{
    private WebAuthn $method;
    private User $user;

    protected function setUp(): void
    {
        TestConfig::load(['site_url' => 'https://example.com/', 'site_name' => 'Site']);
        $this->method = new WebAuthn();
        $this->user = new User();
        $this->user->id = 7;
        $this->user->email = 'a@example.com';
    }

    // registers the authenticator's key like TwoFactor::enable(); returns the data with it
    private function register(Authenticator $key, ?array $data = null, string $name = ''): ?array
    {
        $setup = $this->method->setUp($this->user, $data);
        $new = $this->method->verify($setup['data'], $key->create($setup['page']['options'], $name));

        return $new === null ? null : $this->method->add($data ?? [], $new);
    }

    // signs a login's challenge (kept in the session, added to the data like TwoFactor::verify()); returns the data
    // after verify()
    private function logIn(Authenticator $key, array $data, string $origin = 'https://example.com'): ?array
    {
        $challenge = $this->method->challenge($data);

        return $this->method->verify($challenge['data'] + $data, $key->get($challenge['page']['options'], $origin));
    }

    public function testSetUp(): void
    {
        $setup = $this->method->setUp($this->user, null);
        $options = $setup['page']['options']['publicKey'];

        // the relying party is site_url's host
        $this->assertSame(['name' => 'Site', 'id' => 'example.com'], $options['rp']);
        $this->assertSame('a@example.com', $options['user']['name']);
        $this->assertSame(['new_challenge' => base64_decode(strtr($options['challenge'], '-_', '+/'))],
            $setup['data']);
        $this->assertSame([], $options['excludeCredentials'] ?? []);
    }

    /**
     * A new key is added with its name; a key the user has already is excluded, and not added again.
     */
    public function testRegister(): void
    {
        $first = new Authenticator();
        $data = $this->register($first, null, '  My key  ');
        $id = Authenticator::encode($first->id);

        $this->assertSame([$id], array_keys($data['keys']));
        $this->assertSame('My key', $data['keys'][$id]['name']);
        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $data['keys'][$id]['pub']);
        $this->assertSame(['keys'], array_keys($data));
        $this->assertSame([$id => 'My key'], $this->method->keys($data));

        $second = new Authenticator();
        $data = $this->register($second, $data);
        $this->assertSame([$id => 'My key', Authenticator::encode($second->id) => 'Security key 2'],
            $this->method->keys($data));

        $setup = $this->method->setUp($this->user, $data);
        $this->assertSame([$id, Authenticator::encode($second->id)],
            array_column($setup['page']['options']['publicKey']['excludeCredentials'], 'id'));
        $this->assertSame($data, $this->register($first, $data));
    }

    /**
     * The setup's data has only the new key: add() adds it to the keys of when it's saved.
     */
    public function testAdd(): void
    {
        $first = new Authenticator();
        $data = $this->register($first);
        $setup = $this->method->setUp($this->user, $data);
        $second = new Authenticator();
        $new = $this->method->verify($setup['data'], $second->create($setup['page']['options']));

        $this->assertSame([Authenticator::encode($second->id)], array_keys($new['keys']));
        // the first key removed meanwhile
        $this->assertSame([Authenticator::encode($second->id) => 'Security key 1'],
            $this->method->keys($this->method->add([], $new)));
        $data['keys'][Authenticator::encode($first->id)]['counter'] = 9;
        $this->assertSame(9, $this->method->add($data, $new)['keys'][Authenticator::encode($first->id)]['counter']);
    }

    public function testRegisterErrors(): void
    {
        $key = new Authenticator();
        $setup = $this->method->setUp($this->user, null);

        // another challenge, origin, garbage
        $other = $this->method->setUp($this->user, null);
        $this->assertNull($this->method->verify($setup['data'], $key->create($other['page']['options'])));
        $this->assertNull($this->method->verify($setup['data'], $key->create($setup['page']['options'], '',
            'https://evil.com')));
        $this->assertNull($this->method->verify($setup['data'], $key->create($setup['page']['options'], '',
            'https://www.example.com')));
        $this->assertNull($this->method->verify($setup['data'], 'not json'));
        $this->assertNull($this->method->verify($setup['data'], '{"clientDataJSON": "x", "attestationObject": []}'));
        $this->assertNull($this->method->verify($setup['data'], '{}'));
        $this->assertNotNull($this->method->verify($setup['data'], $key->create($setup['page']['options'])));
    }

    /**
     * A login signs the challenge with one of the keys; the challenge works once, and the counter is kept.
     */
    public function testLogIn(): void
    {
        $key = new Authenticator();
        $data = $this->register($key);
        $challenge = $this->method->challenge($data);
        $id = Authenticator::encode($key->id);

        $this->assertSame([$id], array_column($challenge['page']['options']['publicKey']['allowCredentials'], 'id'));
        $this->assertSame('example.com', $challenge['page']['options']['publicKey']['rpId']);

        $this->assertSame(['challenge', 'challenge_time'], array_keys($challenge['data']));
        $answer = $key->get($challenge['page']['options']);
        $after = $this->method->verify($challenge['data'] + $data, $answer);
        $this->assertSame(1, $after['keys'][$id]['counter']);
        $this->assertArrayNotHasKey('challenge', $after);

        // once
        $this->assertNull($this->method->verify($after, $answer));
        $this->assertSame(2, $this->logIn($key, $after)['keys'][$id]['counter']);
    }

    public function testLogInErrors(): void
    {
        $key = new Authenticator();
        $data = $this->register($key);

        // another key, origin, an expired challenge, without a challenge
        $this->assertNull($this->logIn(new Authenticator(), $data));
        $this->assertNull($this->logIn($key, $data, 'https://evil.com'));
        $challenge = $this->method->challenge($data);
        $challenge['data']['challenge_time'] -= 301;
        $this->assertNull($this->method->verify($challenge['data'] + $data,
            $key->get($challenge['page']['options'])));
        $this->assertNull($this->method->verify($data, $key->get($challenge['page']['options'])));
        $this->assertNull($this->method->verify($this->method->challenge($data)['data'] + $data, '{"id": []}'));

        // only site_url's origin, not its subdomains' (the library accepts them) nor http
        $this->assertNull($this->logIn($key, $data, 'https://www.example.com'));
        $this->assertNull($this->logIn($key, $data, 'http://example.com'));
        $this->assertNotNull($this->logIn($key, $data));
    }

    /**
     * A registration's answer for a login's challenge doesn't log in (nor add the key).
     */
    public function testRegistrationAnswerDoesntLogIn(): void
    {
        $data = $this->register(new Authenticator());
        $challenge = $this->method->challenge($data);
        $options = ['publicKey' => ['challenge' => $challenge['page']['options']['publicKey']['challenge'],
            'rp' => ['id' => 'example.com']]];

        $this->assertNull($this->method->verify($challenge['data'] + $data,
            (new Authenticator())->create($options)));
    }

    /**
     * A counter that doesn't increase means a copied key; keys whose counter stays at 0 work.
     */
    public function testCounter(): void
    {
        $key = new Authenticator();
        $data = $this->logIn($key, $this->register($key));
        $id = Authenticator::encode($key->id);
        $data['keys'][$id]['counter'] = 5;
        $this->assertNull($this->logIn($key, $data));

        $zero = new Authenticator(false);
        $data = $this->register($zero);
        $data = $this->logIn($zero, $data);
        $this->assertSame(0, $data['keys'][Authenticator::encode($zero->id)]['counter']);
        $this->assertNotNull($this->logIn($zero, $data));
    }

    public function testRemoveKey(): void
    {
        $first = new Authenticator();
        $data = $this->register(new Authenticator(), $this->register($first));
        $data = $this->method->removeKey($data, Authenticator::encode($first->id));

        $this->assertCount(1, $this->method->keys($data));
        $this->assertNull($this->logIn($first, $data));
        $this->assertSame($data, $this->method->removeKey($data, 'unknown'));
    }
}

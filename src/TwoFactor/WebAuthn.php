<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\TwoFactor;

use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Crypt;
use Enicore\Rivena\Models\User;
use ReportUri\Passkeys\WebAuthn as Server;
use ReportUri\Passkeys\WebAuthnException;

/**
 * Security keys and passkeys (WebAuthn, with report-uri/passkeys-php): the browser signs a challenge with a key the
 * user registered. A user can have several keys. The relying party is site_url's host: the keys work only there and on
 * its subdomains, so changing it makes them stop working (the users log in with their other methods or a recovery
 * code, and set them up again).
 *
 * The data: keys (id => name, pub (PEM), counter). The challenges are kept in the session, never saved: a login's
 * (challenge, challenge_time, used once) and a setup's (new_challenge, with the new key only, add() adds it to the
 * keys). The browser's answers have to come from site_url's origin (the library also accepts its subdomains').
 *
 * @package Enicore\Rivena
 */
final class WebAuthn extends Method
{
    // the seconds the browser waits for the key, and a login's challenge works
    private const int TIMEOUT = 60;
    private const int CHALLENGE_DURATION = 300;

    private string $host;
    // scheme://host[:port]
    private string $origin;

    /**
     * Creates the method for site_url's host.
     */
    public function __construct()
    {
        $url = parse_url(Config::get('site_url'));
        $this->host = $url['host'];
        $this->origin = "https://$this->host" . (isset($url['port']) ? ":$url[port]" : '');
    }

    /**
     * Returns the method's name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Security key or passkey';
    }

    /**
     * Returns what the method is.
     *
     * @return string
     */
    public function description(): string
    {
        return 'A security key, or a passkey on your phone or computer (fingerprint, face or screen lock).';
    }

    /**
     * Returns the new key's challenge as the data, and the browser's options to create the key (not one the user has
     * already).
     *
     * @param User $user
     * @param array|null $data
     * @return array
     */
    public function setUp(User $user, ?array $data): array
    {
        $keys = $data['keys'] ?? [];
        $server = $this->server();
        $args = $server->getCreateArgs(hash('sha256', (string)$user->id, true), $user->email, $user->email,
            self::TIMEOUT, false, 'discouraged', null, array_map(self::decode(...), array_keys($keys)));

        return ['data' => ['new_challenge' => $server->getChallenge()->getBinaryString()],
            'page' => ['options' => json_decode(json_encode($args), true)]];
    }

    /**
     * Returns the login's challenge, and the browser's options to sign it with one of the user's keys.
     *
     * @param array $data
     * @return array|null
     */
    public function challenge(array $data): ?array
    {
        $server = $this->server();
        $args = $server->getGetArgs(array_map(self::decode(...), array_keys($data['keys'])), self::TIMEOUT,
            true, true, true, true, true, 'discouraged');
        $challenge = ['challenge' => $server->getChallenge()->getBinaryString(), 'challenge_time' => time()];

        return ['data' => $challenge, 'page' => ['options' => json_decode(json_encode($args), true)]];
    }

    /**
     * Checks the browser's answer (JSON), from site_url's origin: during a setup, the new key (with the answer's name),
     * and during a login, the signature of the challenge (used then) by one of the keys. Returns null when it's
     * wrong.
     *
     * @param array $data
     * @param string $input
     * @return array|null
     */
    public function verify(array $data, string $input): ?array
    {
        $answer = json_decode($input, true);

        $client = is_array($answer) ? json_decode(self::decode($answer['clientDataJSON'] ?? ''), true) : null;

        if (($client['origin'] ?? null) !== $this->origin) {
            return null;
        }

        try {
            return isset($data['new_challenge']) ? $this->verifyNewKey($data, $answer) :
                $this->verifyLogin($data, $answer);
        } catch (WebAuthnException) {
            return null;
        }
    }

    /**
     * Returns the keys, id => name.
     *
     * @param array $data
     * @return array|null
     */
    public function keys(array $data): ?array
    {
        return array_map(fn($key) => $key['name'], $data['keys'] ?? []);
    }

    /**
     * Returns the keys with the new key of a setup (verify()'s data), named "Security key <n>" if it has no name; the
     * keys stay the same if the user has it already. The data is empty for the user's first key.
     *
     * @param array $data
     * @param array $setup
     * @return array
     */
    public function add(array $data, array $setup): array
    {
        $data['keys'] ??= [];

        foreach ($setup['keys'] as $id => $key) {
            $key['name'] = $key['name'] ?: 'Security key ' . (count($data['keys']) + 1);
            $data['keys'] += [$id => $key];
        }

        return $data;
    }

    /**
     * Returns the data without the key.
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function removeKey(array $data, string $id): array
    {
        unset($data['keys'][$id]);

        return $data;
    }

    /**
     * Returns the data with the new key only (add() adds it to the user's), without the setup's challenge, or null if
     * the answer isn't for the challenge.
     *
     * @param array $data
     * @param array $answer
     * @return array|null
     * @throws WebAuthnException
     */
    private function verifyNewKey(array $data, array $answer): ?array
    {
        $created = $this->server()->processCreate(self::decode($answer['clientDataJSON'] ?? ''),
            self::decode($answer['attestationObject'] ?? ''), $data['new_challenge']);
        $name = mb_substr(trim((string)($answer['name'] ?? '')), 0, 50);

        return ['keys' => [Crypt::binaryToText($created->credentialId) => ['name' => $name,
            'pub' => $created->credentialPublicKey, 'counter' => (int)$created->signatureCounter]]];
    }

    /**
     * Returns the data without the login's challenge, with the key's new signature counter, or null if the answer isn't
     * a signature of the challenge (before it expired) by one of the keys. A counter that doesn't increase means a
     * copied key (the keys that don't count stay at 0).
     *
     * @param array $data
     * @param array $answer
     * @return array|null
     * @throws WebAuthnException
     */
    private function verifyLogin(array $data, array $answer): ?array
    {
        $id = $answer['id'] ?? null;
        $key = is_string($id) ? $data['keys'][$id] ?? null : null;

        if (!$key || !isset($data['challenge']) || $data['challenge_time'] < time() - self::CHALLENGE_DURATION) {
            return null;
        }

        $server = $this->server();
        $server->processGet(self::decode($answer['clientDataJSON'] ?? ''),
            self::decode($answer['authenticatorData'] ?? ''), self::decode($answer['signature'] ?? ''), $key['pub'],
            $data['challenge'], $key['counter'] ?: null);

        unset($data['challenge'], $data['challenge_time']);
        $data['keys'][$id]['counter'] = max($key['counter'], (int)$server->getSignatureCounter());

        return $data;
    }

    /**
     * Returns a new server for site_url's host, with the binary values in base64url in the browser's options (each
     * keeps its first challenge).
     *
     * @return Server
     */
    private function server(): Server
    {
        return new Server((string)Config::get('site_name'), $this->host, true);
    }

    /**
     * Returns the bytes in base64url (without padding), as the browser's options and answers have them.
     *
     * @param string $bytes
     * @return string
     */
    /**
     * Returns the bytes of a base64url string ("" if it isn't one).
     *
     * @param mixed $text
     * @return string
     */
    private static function decode(mixed $text): string
    {
        return is_string($text) ? (string)base64_decode(strtr($text, '-_', '+/'), true) : '';
    }
}

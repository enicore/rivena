<?php
namespace Tests\Support;

/**
 * A software WebAuthn authenticator (an EC P-256 key, attestation "none") for the tests: answers the browser's options
 * like navigator.credentials.create() and get() with webauthn.js, as JSON for the server.
 */
final class Authenticator
{
    public readonly string $id;
    private \OpenSSLAsymmetricKey $key;
    private int $counter = 0;

    /**
     * @param bool $counts whether the signature counter increases (some keys keep it at 0)
     */
    public function __construct(private readonly bool $counts = true)
    {
        $this->id = random_bytes(16);
        $this->key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    }

    // the registration's answer for the options (WebAuthn::setUp()'s), with the key's name
    public function create(array $options, string $name = '', string $origin = 'https://example.com'): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];
        // the coordinates have 32 bytes; OpenSSL leaves out the leading zeros
        $cose = self::cbor([1 => 2, 3 => -7, -1 => 1, -2 => ['bytes' => str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)],
            -3 => ['bytes' => str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)]]);
        $authData = hash('sha256', $options['publicKey']['rp']['id'], true) . chr(0x41) . pack('N', $this->counter) .
            str_repeat("\0", 16) . pack('n', strlen($this->id)) . $this->id . $cose;
        $clientData = $this->clientData('webauthn.create', $options['publicKey']['challenge'], $origin);

        return json_encode(['name' => $name, 'clientDataJSON' => self::encode($clientData),
            'attestationObject' => self::encode(self::cbor(['fmt' => 'none', 'attStmt' => [],
                'authData' => ['bytes' => $authData]]))]);
    }

    // the login's answer for the options (WebAuthn::challenge()'s): the challenge signed
    public function get(array $options, string $origin = 'https://example.com'): string
    {
        $this->counts && $this->counter++;
        $authData = hash('sha256', $options['publicKey']['rpId'], true) . chr(0x01) . pack('N', $this->counter);
        $clientData = $this->clientData('webauthn.get', $options['publicKey']['challenge'], $origin);
        openssl_sign($authData . hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return json_encode(['id' => self::encode($this->id), 'clientDataJSON' => self::encode($clientData),
            'authenticatorData' => self::encode($authData), 'signature' => self::encode($signature)]);
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $origin, 'crossOrigin' => false],
            JSON_UNESCAPED_SLASHES);
    }

    // CBOR of integers, strings, byte strings (['bytes' => ...]) and maps
    private static function cbor(mixed $value): string
    {
        $head = function (int $type, int $length): string {
            $type <<= 5;
            return match (true) {
                $length < 24 => chr($type | $length),
                $length < 256 => chr($type | 24) . chr($length),
                default => chr($type | 25) . pack('n', $length),
            };
        };

        if (is_int($value)) {
            return $value >= 0 ? $head(0, $value) : $head(1, -1 - $value);
        }

        if (is_string($value)) {
            return $head(3, strlen($value)) . $value;
        }

        if (isset($value['bytes'])) {
            return $head(2, strlen($value['bytes'])) . $value['bytes'];
        }

        $map = $head(5, count($value));

        foreach ($value as $key => $item) {
            $map .= self::cbor($key) . self::cbor($item);
        }

        return $map;
    }
}

<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Models\User;

/**
 * The codes of an authenticator app (TOTP, RFC 6238): 6 digits, a new one every 30 seconds, from a secret the app gets
 * from a QR code. The code before and after the current one work too (clocks differ), each once.
 *
 * @package Enicore\Rivena
 */
final class Totp extends Method
{
    private const string BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Returns the method's name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Authenticator app';
    }

    /**
     * Returns what the method is.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Codes from an app like Google Authenticator, Microsoft Authenticator or Aegis.';
    }

    /**
     * Returns a new secret (the data), and its QR code (SVG) and text for the app (the page).
     *
     * @param User $user
     * @param array|null $data
     * @return array
     */
    public function setUp(User $user, ?array $data): array
    {
        $secret = self::base32Encode(random_bytes(20));
        $issuer = Config::get('site_name');
        $uri = 'otpauth://totp/' . rawurlencode("$issuer:$user->email") . '?' .
            http_build_query(['secret' => $secret, 'issuer' => $issuer], '', '&', PHP_QUERY_RFC3986);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 0), new SvgImageBackEnd())))->writeString($uri);

        return ['data' => ['secret' => $secret], 'page' => [
            'qr' => preg_replace('/^<\?xml.*?\?>\s*/', '', $svg),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]];
    }

    /**
     * Returns the data with the code's time step when the code is right (an earlier or the same step doesn't work
     * again), or null.
     *
     * @param array $data
     * @param string $input
     * @return array|null
     */
    public function verify(array $data, string $input): ?array
    {
        $code = preg_replace('/\s+/', '', $input);
        $key = self::base32Decode($data['secret']);

        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $now = intdiv(time(), 30);

        for ($step = $now - 1; $step <= $now + 1; $step++) {
            if ($step > ($data['last_step'] ?? 0) && hash_equals(self::code($key, $step), $code)) {
                return ['last_step' => $step] + $data;
            }
        }

        return null;
    }

    /**
     * Returns the 6-digit code of the key (binary) for the time step (the Unix time / 30).
     *
     * @param string $key
     * @param int $step
     * @return string
     */
    public static function code(string $key, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $value = unpack('N', substr($hash, ord($hash[19]) & 0x0F, 4))[1] & 0x7FFFFFFF;

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the bytes in base32 (RFC 4648, without padding), as the apps read the secret.
     *
     * @param string $bytes
     * @return string
     */
    public static function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';

        foreach (str_split($bits, 5) as $chunk) {
            $result .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $result;
    }

    /**
     * Returns the bytes of a base32 string, or "" if it has other characters.
     *
     * @param string $text
     * @return string
     */
    public static function base32Decode(string $text): string
    {
        $bits = '';

        foreach (str_split($text) as $char) {
            if (($value = strpos(self::BASE32, $char)) === false) {
                return '';
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $result = '';

        // the last bits that don't make a byte are padding
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $result .= chr(bindec($byte));
            }
        }

        return $result;
    }
}

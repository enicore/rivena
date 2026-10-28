<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Random\RandomException;
use RuntimeException;

/**
 * Encryption and random strings: encrypt() and decrypt() for any value, encryptId() and decryptId() for IDs, random
 * passwords and tokens, short number encodings. The key is app_key.
 *
 * @package Enicore\Rivena
 */
class Crypt
{
    public const string BASE_36_CHARSET = "0123456789abcdefghijklmnopqrstuvwxyz";
    public const string BASE_62_CHARSET = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    public const string BASE_92_CHARSET = "!\"#$%&'()*+-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`".
        "abcdefghijklmnopqrstuvwxyz{}~"; // excludes characters that can be used as separators: | and ,

    /**
     * Returns the ID encrypted into a short URL-safe string, always the same for the same ID. For URLs, use public_id
     * instead.
     *
     * @param int $id
     * @return string
     */
    public static function encryptId(int $id): string
    {
        $string = openssl_encrypt(
            pack('J', $id) . str_repeat("\0", 8),
            'aes-256-ecb',
            self::key(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        return self::binaryToText($string);
    }

    /**
     * Returns the ID from a string of encryptId(), or null if it can't be decrypted.
     *
     * @param string $string
     * @return int|null
     */
    public static function decryptId(string $string): ?int
    {
        $block = openssl_decrypt(
            (string)self::textToBinary($string),
            'aes-256-ecb',
            self::key(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        if ($block === false || strlen($block) !== 16 || substr($block, 8) !== str_repeat("\0", 8)) {
            return null;
        }

        return unpack('J', $block)[1];
    }

    /**
     * Returns the value (any type serialize() takes) encrypted into a URL-safe string, with AES-256-GCM; values over
     * 100 bytes are compressed when that makes them smaller.
     *
     * @param mixed $data
     * @param string|null $key
     * @return string
     * @throws RandomException
     */
    public static function encrypt(mixed $data, ?string $key = null): string
    {
        // the first byte says if the rest is compressed
        $data = serialize($data);
        $compressed = strlen($data) > 100 ? gzcompress($data, 9) : false;
        $data = $compressed !== false && strlen($compressed) < strlen($data) ? "1$compressed" : "0$data";

        $iv = random_bytes(12);
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-gcm',
            self::key($key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return self::binaryToText($iv . $tag . $encrypted);
    }

    /**
     * Returns the value from a string of encrypt(), with its type.
     *
     * @param string $data
     * @param string|null $key
     * @return mixed
     * @throws RuntimeException
     */
    public static function decrypt(string $data, ?string $key = null): mixed
    {
        $data = (string)self::textToBinary($data);
        $data = strlen($data) < 28 ? false : openssl_decrypt(
            substr($data, 28),
            'aes-256-gcm',
            self::key($key),
            OPENSSL_RAW_DATA,
            substr($data, 0, 12),
            substr($data, 12, 16)
        );

        if ($data === false) {
            throw new RuntimeException("The string can't be decrypted.");
        }

        return unserialize($data[0] === '1' ? gzuncompress(substr($data, 1)) : substr($data, 1));
    }

    /**
     * Returns the 32-byte AES key made from the key, app_key by default.
     *
     * @param string|null $key
     * @return string
     */
    private static function key(?string $key = null): string
    {
        return hash('sha256', $key ?? (string)Config::get('app_key'), true);
    }

    /**
     * Returns a random password of letters, digits and symbols.
     *
     * @param int $length
     * @return string
     * @throws RandomException
     */
    public static function generatePassword(int $length = 24): string
    {
        return self::generateRandomToken(
            $length,
            "1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM!@#%^&*()_-+="
        );
    }

    /**
     * Returns a random string of the characters, lowercase letters and digits by default.
     *
     * @param int $length
     * @param string|null $characters
     * @return string
     * @throws RandomException
     */
    public static function generateRandomToken(int $length = 32, ?string $characters = null): string
    {
        $characters ??= "abcdefghijklmnopqrstuvwxyz1234567890";
        $result = "";

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $result;
    }

    /**
     * Returns the positive number as a short string of the charset's characters, or "" if it isn't one. BASE_36_CHARSET
     * and BASE_62_CHARSET are URL-safe; BASE_92_CHARSET gives the shortest strings.
     *
     * @param int $number
     * @param string $charset
     * @return string
     */
    public static function baseEncode(int $number, string $charset = self::BASE_36_CHARSET): string
    {
        if ($number < 1) {
            return "";
        }

        $base = strlen($charset);
        $result = "";
        while ($number > 0) {
            $result = $charset[$number % $base] . $result;
            $number = intdiv($number, $base);
        }
        return $result;
    }

    /**
     * Returns the number from a string of baseEncode(), or false if it isn't one (empty, other characters, or too large
     * for an int).
     *
     * @param string $encoded
     * @param string $charset
     * @return int|false
     */
    public static function baseDecode(string $encoded, string $charset = self::BASE_36_CHARSET): int|false
    {
        $base = strlen($charset);
        $length = strlen($encoded);
        $number = 0;

        for ($i = 0; $i < $length; $i++) {
            $digit = strpos($charset, $encoded[$i]);

            if ($digit === false || $number > intdiv(PHP_INT_MAX - $digit, $base)) {
                return false;
            }

            $number = $number * $base + $digit;
        }

        return $length ? $number : false;
    }

    /**
     * Returns the binary data from a string of binaryToText(), or false if it isn't one.
     *
     * @param string $data
     * @return string|false
     */
    public static function textToBinary(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Returns the binary data as a URL-safe string (base64 with -_ and no padding).
     *
     * @param string $data
     * @return string
     */
    public static function binaryToText(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

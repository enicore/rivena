<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Random\RandomException;
use RuntimeException;

/**
 * Reads and writes the session's values ($_SESSION), starting the session when needed.
 *
 * @package Enicore\Rivena
 */
class Session
{
    /**
     * Returns the session value, or the default if it's missing.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Returns true if the session has the key.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Sets the session value.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Removes the session value.
     *
     * @param string $key
     * @return void
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Ends the session and removes its data and its cookie.
     *
     * @return void
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        unset($params['lifetime']);
        setcookie(session_name(), '', ['expires' => 1] + $params);
        session_destroy();
    }

    /**
     * Gives the session a new ID (against session fixation).
     *
     * @return void
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Starts the session if it isn't started, with a secure, HTTP-only cookie.
     *
     * @return void
     * @throws RuntimeException
     */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        // until the browser is closed, https only (browsers allow it on http://localhost too), hidden from JavaScript
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // __Host-: only from this host, https and path / (a subdomain can't set it, e.g. to plant a session)
        session_name('__Host-session');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        // don't accept session ids the server didn't create (session fixation)
        ini_set('session.use_strict_mode', '1');

        if (!session_start()) {
            throw new RuntimeException("Failed to start the session.");
        }
    }

    /**
     * Returns the session's CSRF token, creating it on first use.
     *
     * @return string
     * @throws RandomException
     */
    public static function getCsrfToken(): string
    {
        self::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
}

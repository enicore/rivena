<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use RuntimeException;

/**
 * The site's settings: src/config.php (returns an array), overridden by .env (db_host=x, the same names). A .env value
 * gets the type of the config.php value it overrides; the others are strings.
 *
 * new App() loads them; read them anywhere with Config::get('db_host').
 *
 * @package Enicore\Rivena
 */
class Config
{
    private const array REQUIRED = [
        'app_key', 'site_name', 'site_url', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
    ];
    private static array $config = [];

    /**
     * Loads the config file (which sets $config['key']), then the .env file over it, and checks that the required
     * values are set, and the password limits in their ranges.
     *
     * @param string $file
     * @param string $envFile
     * @return void
     * @throws RuntimeException
     */
    public static function load(string $file, string $envFile): void
    {
        // in its own scope, so it sees only $config; with Rivena's defaults
        $config = is_file($file) ? (static function () use ($file): array {
            $config = [];
            require $file;
            return $config;
        })() : [];
        self::$config = $config + ['user_path' => '/usr',
                'event_log_retention_months' => 6, 'two_factor_methods' => ['totp', 'webauthn'],
                'two_factor_required' => false, 'two_factor_email_notifications' => true, 'password_min_length' => 12,
                'password_min_score' => 3, 'password_max_length' => 60, 'image_max_megapixels' => 25,
                'smtp_timeout' => 10];

        foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $key = trim($name);
            $value = trim($value);

            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[1] === '"' ? stripcslashes($matches[2]) : $matches[2];
            }

            self::$config[$key] = match (gettype(self::$config[$key] ?? null)) {
                'integer' => (int)$value,
                'double' => (float)$value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
                'array' => $value === '' ? [] : array_map('trim', explode(',', $value)),
                default => $value,
            };
        }

        $missing = array_filter(self::REQUIRED, fn($key) => (string)(self::$config[$key] ?? '') === '');

        if ($missing) {
            throw new RuntimeException('Missing config values: ' . implode(', ', $missing));
        }

        // the links in emails and WebAuthn's keys are for this address
        if (!filter_var(self::$config['site_url'], FILTER_VALIDATE_URL) || !str_starts_with(self::$config['site_url'],
                'https://')) {
            throw new RuntimeException('Config value site_url must be the site\'s https:// address');
        }

        foreach (['password_min_length' => [10, 30], 'password_min_score' => [1, 4], 'password_max_length' => [40, 80]]
                 as $key => [$min, $max]) {
            if (self::$config[$key] < $min || self::$config[$key] > $max) {
                throw new RuntimeException("Config value $key must be from $min to $max");
            }
        }
    }

    /**
     * Returns the config value, or the default if it isn't set.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }
}

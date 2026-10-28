<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\TwoFactor;

use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Crypt;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Two-factor authentication: the methods the users can set up (two_factor_methods in the config, in its order), the
 * users' methods (user_two_factor, their data encrypted with app_key), and the recovery codes, which come with the
 * first method and go with the last one. With two_factor_required, every user has to set up a method (AuthMiddleware).
 * A user's method still counts when it's no longer in two_factor_methods (only new setups are).
 *
 * @package Enicore\Rivena
 */
final class TwoFactor
{
    // the methods, id => class; two_factor_methods picks the ones the users can set up
    public const array METHODS = ['totp' => Totp::class, 'webauthn' => WebAuthn::class];
    public const string RECOVERY = 'recovery';

    /**
     * Returns the methods the users can set up, id => Method, in the config's order.
     *
     * @return Method[]
     */
    public static function methods(): array
    {
        $methods = [];

        foreach (Config::get('two_factor_methods') as $id) {
            if (isset(self::METHODS[$id])) {
                $methods[$id] = new (self::METHODS[$id])();
            }
        }

        return $methods;
    }

    /**
     * Returns the method (one of METHODS, in two_factor_methods or not, or the recovery codes), or null.
     *
     * @param string $id
     * @return Method|null
     */
    public static function method(string $id): ?Method
    {
        if ($id === self::RECOVERY) {
            return new RecoveryCodes();
        }

        return isset(self::METHODS[$id]) ? new (self::METHODS[$id])() : null;
    }

    /**
     * Returns true if every user has to set up two-factor authentication (and there's a method to set up).
     *
     * @return bool
     */
    public static function required(): bool
    {
        return Config::get('two_factor_required') && self::methods();
    }

    /**
     * Returns the user's methods, id => data: the ones of methods() in their order,
     * the others of METHODS, then the recovery codes; none if the user has only the recovery codes.
     *
     * @param int $userId
     * @return array
     */
    public static function userMethods(int $userId): array
    {
        $rows = DB::table('user_two_factor')->where('user_id', $userId)->pluck('data', 'method');
        $methods = [];

        foreach (array_unique([...array_keys(self::methods()), ...array_keys(self::METHODS), self::RECOVERY]) as $id) {
            if (isset($rows[$id])) {
                $methods[$id] = Crypt::decrypt($rows[$id]);
            }
        }

        return array_keys($methods) === [self::RECOVERY] ? [] : $methods;
    }

    /**
     * Returns true if the user has two-factor authentication: a method of METHODS (the recovery codes alone don't
     * count).
     *
     * @param int $userId
     * @return bool
     */
    public static function enabled(int $userId): bool
    {
        return DB::table('user_two_factor')->where('user_id', $userId)
            ->whereIn('method', array_keys(self::METHODS))->exists();
    }

    /**
     * Saves the user's method with its data (setUp()'s, verified), added to the method's current data, if any
     * (Method::add(), read when saving, so changes during the setup stay); the user's first method gets the recovery
     * codes too, which are returned then (null otherwise).
     *
     * @param int $userId
     * @param string $id
     * @param array $data
     * @return array|null
     */
    public static function enable(int $userId, string $id, array $data): ?array
    {
        return DB::transaction(function () use ($userId, $id, $data) {
            $row = DB::table('user_two_factor')->where('user_id', $userId)->where('method', $id)->lockForUpdate()
                ->value('data');
            $codes = isset(self::userMethods($userId)[self::RECOVERY]) ? null : self::renewRecoveryCodes($userId);
            self::save($userId, $id, self::method($id)->add($row === null ? [] : Crypt::decrypt($row), $data));

            return $codes;
        });
    }

    /**
     * Removes the user's method; with the last one, the recovery codes go too.
     *
     * @param int $userId
     * @param string $id
     * @return void
     */
    public static function disable(int $userId, string $id): void
    {
        DB::table('user_two_factor')->where('user_id', $userId)->where('method', $id)->delete();

        if (!self::enabled($userId)) {
            self::reset($userId);
        }
    }

    /**
     * Gives the user new recovery codes (the old ones stop working) and returns them.
     *
     * @param int $userId
     * @return array
     */
    public static function renewRecoveryCodes(int $userId): array
    {
        $codes = RecoveryCodes::generate();
        self::save($userId, self::RECOVERY, ['codes' => $codes]);

        return $codes;
    }

    /**
     * Returns the start of a login with the user's method (Method::challenge(): the challenge for verify(), and what
     * the browser needs, WebAuthn's options), or null for the methods without (TOTP's code is typed).
     *
     * @param int $userId
     * @param string $id
     * @return array|null
     */
    public static function challenge(int $userId, string $id): ?array
    {
        $method = self::method($id);
        $data = self::userMethods($userId)[$id] ?? null;

        return $method && $data ? $method->challenge($data) : null;
    }

    /**
     * Removes one of the keys of the user's method (Method::keys()); without keys left, the method goes, like with
     * disable().
     *
     * @param int $userId
     * @param string $id
     * @param string $key
     * @return void
     */
    public static function removeKey(int $userId, string $id, string $key): void
    {
        $method = self::method($id);
        $data = $method->removeKey(self::userMethods($userId)[$id], $key);

        if ($method->keys($data)) {
            self::save($userId, $id, $data);
        } else {
            self::disable($userId, $id);
        }
    }

    /**
     * Removes all the user's methods (two-factor authentication is off).
     *
     * @param int $userId
     * @return void
     */
    public static function reset(int $userId): void
    {
        DB::table('user_two_factor')->where('user_id', $userId)->delete();
    }

    /**
     * Returns true if the input is right for the user's method (with the login's challenge, challenge()'s), and saves
     * the method's changed data (a code works once); the row is locked meanwhile, so parallel requests can't use a
     * code twice.
     *
     * @param int $userId
     * @param string $id
     * @param string $input
     * @param array $challenge
     * @return bool
     */
    public static function verify(int $userId, string $id, string $input, array $challenge = []): bool
    {
        $method = self::method($id);

        return $method && DB::transaction(function () use ($userId, $id, $input, $challenge, $method) {
            $row = DB::table('user_two_factor')->where('user_id', $userId)->where('method', $id)->lockForUpdate()
                ->value('data');
            $data = $row === null ? null : $method->verify($challenge + Crypt::decrypt($row), $input);

            if ($data !== null) {
                self::save($userId, $id, $data);
            }

            return $data !== null;
        });
    }

    /**
     * Saves the user's method with the data, encrypted.
     *
     * @param int $userId
     * @param string $id
     * @param array $data
     * @return void
     */
    private static function save(int $userId, string $id, array $data): void
    {
        DB::table('user_two_factor')->upsert(['user_id' => $userId, 'method' => $id, 'data' => Crypt::encrypt($data),
            'created_at' => time()], ['user_id', 'method'], ['data']);
    }
}

<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Enicore\Rivena\Exceptions\LoginException;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Illuminate\Database\Capsule\Manager as DB;
use Random\RandomException;
use ZxcvbnPhp\Zxcvbn;

/**
 * The logins: checks the passwords (PHP's password_hash(), Argon2id), throttles the failed attempts, keeps the
 * logged-in user in the session, and remembers the user in a cookie ("remember me"). checkPassword() rates the new
 * passwords (zxcvbn).
 *
 * A session is valid while its user exists, isn't disabled and has the same session_version: setPassword() increments
 * it, which logs the user out of the other sessions. The remember cookie is "selector:validator"; the database has the
 * validator's SHA-256 only, and each use replaces the token. A wrong validator for a selector means a stolen cookie,
 * and deletes the user's tokens.
 *
 * Auth::attempt($email, $password) returns the user or throws LoginException, then Auth::logIn($user) starts the
 * user's session; AuthMiddleware calls Auth::check() on each request. A user with two-factor authentication waits in
 * the session after the password (startTwoFactor()) until attemptTwoFactor() checks the code. Auth::id() is the user
 * only after check() (or logIn()) in the request, so a route without AuthMiddleware doesn't get an ended session's.
 *
 * A password reset link has a random token; the database has its SHA-256 only. It works for an hour, until the user's
 * password changes.
 *
 * @package Enicore\Rivena
 */
final class Auth
{
    // __Host-: only from this host (a subdomain can't plant a token)
    public const string COOKIE = '__Host-remember';
    public const int REMEMBER_DURATION = 30 * 24 * 60 * 60;
    // failed logins in THROTTLE_DURATION seconds for an email from an IP address: no limit for an email from
    // everywhere, so nobody can lock others out, nor for an IP address, so a shared one doesn't lock its users out
    public const int MAX_FAILED_LOGINS = 5;
    public const int THROTTLE_DURATION = 600;
    // the seconds to enter the second factor after the password
    public const int TWO_FACTOR_DURATION = 300;
    // a hash of a random password, checked for unknown emails, so they take as long as a wrong password (Argon2id,
    // PHP's default costs)
    private const string DUMMY_HASH =
        '$argon2id$v=19$m=65536,t=4,p=1$WnNSOGJCNTQuRzJWODh0Yg$I0Did1mlD7K4/GKFwgTBafRlXIZ65mI9yjZfFis0Ibs';

    // the session's user that check() or logIn() accepted in this request
    private static ?int $userId = null;

    /**
     * Returns the logged-in user's ID, or null: only when check() or logIn() accepted the session's user in this
     * request. Doesn't start the session.
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        $id = $_SESSION['auth_user_id'] ?? null;

        return $id !== null && $id === self::$userId ? $id : null;
    }

    /**
     * Returns true if a user is logged in: the session's user is valid (or the session is ended), or the remember
     * cookie logs the user in.
     *
     * @return bool
     * @throws RandomException
     */
    public static function check(): bool
    {
        Session::start();
        $id = Session::get('auth_user_id');

        if ($id === null) {
            return self::logInWithCookie();
        }

        $user = User::find($id);

        if ($user && !$user->disabled && $user->session_version === Session::get('auth_version')) {
            self::$userId = $user->id;
            // an admin may have changed it
            Session::set('auth_email', $user->email);
            return true;
        }

        self::logOut();
        return false;
    }

    /**
     * Returns the user with the email and the password, or throws LoginException: for a wrong email or password (a
     * failed attempt), or when the email has too many failed attempts from the IP address in the last 10 minutes.
     *
     * @param string $email
     * @param string $password
     * @return User
     * @throws LoginException
     */
    public static function attempt(string $email, string $password): User
    {
        $attempt = self::countAttempt($email);

        $user = User::where('email', $email)->first();

        if (!password_verify($password, $user->password ?? self::DUMMY_HASH) || !$user) {
            throw new LoginException('Invalid email or password.', 401);
        }

        DB::table('login_attempts')->where('id', $attempt)->delete();

        if (password_needs_rehash($user->password, PASSWORD_ARGON2ID)) {
            $user->password = password_hash($password, PASSWORD_ARGON2ID);
            $user->save();
        }

        return $user;
    }

    /**
     * Keeps the user, whose password was right, in the session until the second factor (attemptTwoFactor()), for
     * TWO_FACTOR_DURATION seconds, with the user's session_version, so a password change meanwhile ends it.
     *
     * @param User $user
     * @param bool $remember
     * @return void
     */
    public static function startTwoFactor(User $user, bool $remember): void
    {
        Session::set('auth_two_factor', ['user_id' => $user->id, 'version' => (int)$user->session_version,
            'remember' => $remember, 'time' => time()]);
    }

    /**
     * Returns the user waiting for the second factor (startTwoFactor()), or null if none, too late, or the user's
     * sessions ended meanwhile (a new session_version, e.g. after a password change).
     *
     * @return User|null
     */
    public static function twoFactorUser(): ?User
    {
        $pending = Session::get('auth_two_factor');

        if (!is_array($pending) || $pending['time'] < time() - self::TWO_FACTOR_DURATION) {
            return null;
        }

        $user = User::find($pending['user_id']);

        return $user && $user->session_version === $pending['version'] ? $user : null;
    }

    /**
     * Returns [the user, remember] when the input is right for the waiting user's method (who doesn't wait any more
     * then), or throws LoginException: no user waits or too late (401), a wrong input (401), too many failed attempts
     * (429, counted with the failed passwords).
     *
     * @param string $method
     * @param string $input
     * @return array
     * @throws LoginException
     */
    public static function attemptTwoFactor(string $method, string $input): array
    {
        if (!$user = self::twoFactorUser()) {
            Session::remove('auth_two_factor');
            throw new LoginException('The login expired. Log in again.', 401);
        }

        $attempt = self::countAttempt($user->email);

        $challenge = Session::get('auth_two_factor')['challenges'][$method] ?? [];

        if (!TwoFactor::verify($user->id, $method, $input, $challenge)) {
            throw new LoginException('Invalid code.', 401);
        }

        DB::table('login_attempts')->where('id', $attempt)->delete();
        $remember = (bool)Session::get('auth_two_factor')['remember'];
        Session::remove('auth_two_factor');

        return [$user, $remember];
    }

    /**
     * Keeps the login's challenge of the method (TwoFactor::challenge()) for the user waiting for the second factor;
     * in the session, so another login of the user doesn't replace it.
     *
     * @param string $method
     * @param array $challenge
     * @return void
     */
    public static function setTwoFactorChallenge(string $method, array $challenge): void
    {
        $_SESSION['auth_two_factor']['challenges'][$method] = $challenge;
    }

    /**
     * Logs the user in, in a new session ID (with a new CSRF token), and with remember, sets the remember cookie. The
     * previous user's remember cookie is removed (e.g. an admin's who logs in as a user).
     *
     * @param User $user
     * @param bool $remember
     * @return void
     * @throws RandomException
     */
    public static function logIn(User $user, bool $remember = false): void
    {
        self::forgetCookie();
        Session::regenerate();
        // another user's (e.g. an admin's who logs in as a user)
        Session::remove('auth_two_factor');
        Session::remove('two_factor_setup');
        // a token someone planted with the session before the login doesn't work after it
        Session::remove('csrf');
        Session::set('auth_user_id', $user->id);
        self::$userId = $user->id;
        Session::set('auth_email', $user->email);
        // a new user has the database's default (0) only when it's read again
        Session::set('auth_version', (int)$user->session_version);

        $user->last_login_at = time();
        $user->save();

        if ($remember) {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            DB::table('user_remember_tokens')->where('expires_at', '<', time())->delete();
            DB::table('user_remember_tokens')->insert(['selector' => $selector, 'user_id' => $user->id,
                'hash' => hash('sha256', $validator), 'session_version' => (int)$user->session_version,
                'expires_at' => time() + self::REMEMBER_DURATION]);
            self::setCookie("$selector:$validator", time() + self::REMEMBER_DURATION);
        }
    }

    /**
     * Logs the user out: deletes the remember cookie and its token, and ends the session.
     *
     * @return void
     */
    public static function logOut(): void
    {
        self::$userId = null;
        self::forgetCookie();
        Session::destroy();
    }

    /**
     * Rates a new password and returns [its score (zxcvbn's, 0-4), the reason it can't be used or null]: its length
     * (password_min_length, password_max_length), its score (password_min_score; the email and the site's name count
     * as common words), or the hash's password (the current one).
     *
     * @param string $password
     * @param string $email
     * @param string $hash
     * @return array
     */
    public static function checkPassword(string $password, string $email, string $hash = ''): array
    {
        $length = mb_strlen($password);
        $minLength = Config::get('password_min_length');
        $maxLength = Config::get('password_max_length');

        // before zxcvbn, which gets slow on long passwords
        if ($length > $maxLength) {
            return [0, "Use at most $maxLength characters."];
        }

        $result = (new Zxcvbn())->passwordStrength($password, [$email, ...explode('@', $email),
            Config::get('site_name')]);
        $feedback = $result['feedback'];
        $hint = $feedback['warning'] ? "{$feedback['warning']}." : $feedback['suggestions'][0] ?? '';

        return [$result['score'], match (true) {
            $length < $minLength => "Use at least $minLength characters.",
            $result['score'] < Config::get('password_min_score') => rtrim("Too weak. $hint"),
            $hash !== '' && password_verify($password, $hash) => 'Use a different password than the current one.',
            default => null,
        }];
    }

    /**
     * Sets the user's password and saves the user (a new one too). The user's other sessions end, and the remember
     * cookies and the password reset links stop working; the current session stays if it's the user's, with a new ID.
     *
     * @param User $user
     * @param string $password
     * @return void
     */
    public static function setPassword(User $user, string $password): void
    {
        $user->password = password_hash($password, PASSWORD_ARGON2ID);
        self::endOtherSessions($user);
        DB::table('password_resets')->where('user_id', $user->id)->delete();
    }

    /**
     * Ends the user's other sessions and makes the remember cookies stop working (e.g. after a new password, or
     * two-factor authentication turned on); the current session stays if it's the user's, with a new ID. Saves the
     * user (a new one too).
     *
     * @param User $user
     * @return void
     */
    public static function endOtherSessions(User $user): void
    {
        $user->session_version = (int)$user->session_version + 1;
        $user->save();
        DB::table('user_remember_tokens')->where('user_id', $user->id)->delete();

        if ($user->id === self::id()) {
            Session::regenerate();
            Session::set('auth_version', $user->session_version);
        }
    }

    /**
     * Returns a new password reset token for the user, or null if the user can't reset the password
     * (canResetPassword()) or got one in the last 5 minutes (so nobody can fill the user's inbox). The user's older
     * tokens stop working.
     *
     * @param User $user
     * @return string|null
     * @throws RandomException
     */
    public static function createPasswordReset(User $user): ?string
    {
        if (!self::canResetPassword($user)) {
            return null;
        }

        DB::table('password_resets')->where('created_at', '<', time() - 3600)->delete();
        DB::table('password_resets')->where('user_id', $user->id)->where('created_at', '<=', time() - 300)->delete();

        // a user has one token (the primary key), so parallel requests can't get more
        $token = bin2hex(random_bytes(32));
        $added = DB::table('password_resets')->insertOrIgnore(['user_id' => $user->id,
            'hash' => hash('sha256', $token), 'created_at' => time()]);

        return $added ? $token : null;
    }

    /**
     * Returns the user of the password reset token, or null if the token is unknown or older than an hour, or the user
     * can't reset the password any more (canResetPassword()).
     *
     * @param string $token
     * @return User|null
     */
    public static function passwordResetUser(string $token): ?User
    {
        $userId = DB::table('password_resets')->where('hash', hash('sha256', $token))
            ->where('created_at', '>=', time() - 3600)->value('user_id');
        $user = User::find($userId);

        return $user && self::canResetPassword($user) ? $user : null;
    }

    /**
     * Returns true if the user can reset the password: not disabled, not an admin, with the "login" and
     * "password_reset" permissions.
     *
     * @param User $user
     * @return bool
     */
    private static function canResetPassword(User $user): bool
    {
        return !$user->disabled && !User::isAdmin($user->id) && User::can('login', $user->id)
            && User::can('password_reset', $user->id);
    }

    /**
     * Counts a login attempt (a password, or a second factor's code) for the email from the IP address, and returns its
     * ID (the caller deletes it if the attempt is right); throws LoginException (429) when the email has too many
     * failed ones from the IP address in the last 10 minutes. Counted before the check, so parallel requests can't all
     * pass the limit.
     *
     * @param string $email
     * @return int
     * @throws LoginException
     */
    private static function countAttempt(string $email): int
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        DB::table('login_attempts')->where('created_at', '<', time() - self::THROTTLE_DURATION)->delete();
        $attempt = DB::table('login_attempts')->insertGetId(['created_at' => time(), 'ip' => $ip, 'email' => $email]);

        if (DB::table('login_attempts')->where('ip', $ip)->where('email', $email)->count() > self::MAX_FAILED_LOGINS) {
            throw new LoginException('Too many login attempts. Try again later.', 429);
        }

        return $attempt;
    }

    /**
     * Logs the user of the remember cookie in, with a new token, and returns true; a missing, expired or wrong cookie
     * is deleted. A token made before the user's session_version changed doesn't work (a cookie login racing a
     * password change).
     *
     * @return bool
     * @throws RandomException
     */
    private static function logInWithCookie(): bool
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return false;
        }

        [$selector, $validator] = self::cookieToken();
        $token = DB::table('user_remember_tokens')->where('selector', $selector)->first();

        $valid = $token && hash_equals($token->hash, hash('sha256', $validator));

        if ($token && !$valid) {
            // someone has the selector but not the validator: a stolen or forged cookie
            DB::table('user_remember_tokens')->where('user_id', $token->user_id)->delete();
        }

        $user = $valid && $token->expires_at >= time() ? User::find($token->user_id) : null;
        self::forgetCookie();

        if (!$user || $user->disabled || $user->session_version !== (int)$token->session_version) {
            return false;
        }

        self::logIn($user, true);
        return true;
    }

    /**
     * Deletes the remember cookie and its token.
     *
     * @return void
     */
    private static function forgetCookie(): void
    {
        if (isset($_COOKIE[self::COOKIE])) {
            DB::table('user_remember_tokens')->where('selector', self::cookieToken()[0])->delete();
            self::setCookie('', 1);
        }
    }

    /**
     * Returns the remember cookie's [selector, validator], or empty strings for a malformed cookie (the selector's
     * column is ASCII, other characters would make the query fail).
     *
     * @return array
     */
    private static function cookieToken(): array
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;

        return is_string($cookie) && preg_match('/^([0-9a-f]{24}):([0-9a-f]{64})$/', $cookie, $match) ?
            [$match[1], $match[2]] : ['', ''];
    }

    /**
     * Sets the remember cookie (https only, hidden from JavaScript), or deletes it with an empty value; $_COOKIE
     * changes too, for the rest of the request.
     *
     * @param string $value
     * @param int $expires
     * @return void
     */
    private static function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, ['expires' => $expires, 'path' => '/', 'secure' => true, 'httponly' => true,
            'samesite' => 'Lax']);

        if ($value === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $value;
        }
    }
}

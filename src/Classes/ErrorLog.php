<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Illuminate\Database\Capsule\Manager as DB;
use Throwable;

/**
 * The site's errors and warnings, for the Error Log page: PHP's warnings, notices and deprecations, the exceptions and
 * the fatal errors go to the error_log table (a message seen again counts there, kept DAYS days after it's last seen).
 * The errors are also emailed to error_notification_email (config), at most one email an hour, so nobody has to watch
 * the page. new App() registers it for PHP's warnings and fatal errors; SlimErrorController and new App() add the
 * exceptions. PHP logs them as before.
 *
 * @package Enicore\Rivena
 */
final class ErrorLog
{
    private const int DAYS = 30;
    private static bool $adding = false;

    /**
     * Adds PHP's warnings, notices and deprecations (but not the ones hidden with @), and the fatal errors at the end
     * of the request.
     *
     * @return void
     */
    public static function register(): void
    {
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (error_reporting() & $level) {
                self::add($level === E_USER_ERROR ? 'error' : 'warning', "$message in $file:$line");
            }

            return false;
        });

        register_shutdown_function(function () {
            $error = error_get_last();

            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::add('error', "{$error['message']} in {$error['file']}:{$error['line']}");
            }
        });
    }

    /**
     * Adds the error or warning ("error", "warning") with the request's path; an error is emailed too. A failure is
     * only logged, so adding an error doesn't cause another one.
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    public static function add(string $level, string $message): void
    {
        // the errors while adding one aren't added
        if (self::$adding) {
            return;
        }

        self::$adding = true;
        // the path only: the query and the tokens (e.g. a password reset link's) are secrets, the host the client's
        $path = mb_substr(preg_replace('/[0-9a-f]{32,}/', '...', strtok($_SERVER['REQUEST_URI'] ?? '', '?')), 0, 1000);

        // the column is a TEXT (64 KB) in utf8mb4: invalid UTF-8 or a longer message would fail the insert
        $message = mb_strcut(mb_scrub($message), 0, 65535);

        try {
            DB::table('error_log')->where('last_at', '<', time() - self::DAYS * 86400)->delete();
            DB::table('error_log')->upsert(['hash' => sha1("$level $message"), 'level' => $level,
                'message' => $message, 'path' => $path, 'count' => 1, 'first_at' => time(), 'last_at' => time()],
                ['hash'], ['path', 'last_at', 'count' => DB::raw('`count` + 1')]);
        } catch (Throwable $e) {
            error_log("ErrorLog: {$e->getMessage()}");
        }

        $level === 'error' && self::email($message, $path);
        self::$adding = false;
    }

    /**
     * Emails the error with the path and the time, unless the site emailed one in the last hour.
     *
     * @param string $error
     * @param string $path
     * @return void
     */
    private static function email(string $error, string $path): void
    {
        // the app key is the site's
        $file = sys_get_temp_dir() . '/rivena_error_mail_' . md5((string)Config::get('app_key'));

        // not in development (the developer sees them); touched first, so the errors while sending aren't sent; without
        // the file, no limit, so no email
        if (Config::get('app_env') === 'dev' || !Config::get('error_notification_email') ||
            (is_file($file) && filemtime($file) > time() - 3600) || !touch($file)) {
            return;
        }

        try {
            Mailer::send(Config::get('error_notification_email'), 'Error on ' . Config::get('site_name'), "$error\n\n" .
                "Path: $path\nTime: " . date('Y-m-d H:i:s T') . "\n\n" .
                "The errors of the next hour aren't emailed, see the Error Log page.");
        } catch (Throwable $e) {
            error_log("ErrorLog: {$e->getMessage()}");
        }
    }
}

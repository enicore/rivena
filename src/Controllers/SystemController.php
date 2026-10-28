<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Disk;
use Enicore\Rivena\Classes\Mailer;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Classes\Text;
use Enicore\Rivena\Models\Event;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * The admin System Info page: the versions, PHP's settings, the database, the disk, the memory and the mail.
 *
 * @package Enicore\Rivena
 */
class SystemController extends AdminController
{
    /**
     * Route that sends the System Info page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        $db = Capsule::connection();
        $dbSize = $db->scalar('SELECT SUM(data_length + index_length) FROM information_schema.tables ' .
            'WHERE table_schema = DATABASE()');
        $diskFree = disk_free_space(getcwd());
        $diskTotal = disk_total_space(getcwd());

        // the server's memory in bytes, from /proc/meminfo on Linux (none elsewhere); free is what's available to use
        $memory = ['MemTotal' => 0, 'MemAvailable' => 0];
        foreach (@file('/proc/meminfo') ?: [] as $line) {
            if (preg_match('/^(MemTotal|MemAvailable):\s+(\d+) kB/', $line, $match)) {
                $memory[$match[1]] = (int)$match[2] * 1024;
            }
        }

        return $this->sendPage($request, $response, 'admin/system', [
            'sections' => [
                'Application' => [
                    'Site name' => Config::get('site_name'),
                    'Environment' => Config::get('app_env') ?: 'production',
                    'Users' => User::count(),
                    'Event log entries' => Event::count(),
                ],
                'PHP' => [
                    'Version' => PHP_VERSION,
                    'Web server' => $_SERVER['SERVER_SOFTWARE'] ?? '-',
                    'Operating system' => php_uname('s') . ' ' . php_uname('r'),
                    'Time zone' => date_default_timezone_get(),
                    'Memory limit' => ini_get('memory_limit'),
                    'Max upload size' => Text::sizeToString(Disk::getMaxFileUploadSize()),
                    'Max execution time' => ini_get('max_execution_time') . ' s',
                    'OPcache' => extension_loaded('Zend OPcache') && ini_get('opcache.enable') ? 'On' : 'Off',
                ],
                'Database' => [
                    'Server' => $db->scalar('SELECT VERSION()'),
                    'Name' => Config::get('db_database'),
                    'Size' => Text::sizeToString((int)$dbSize),
                ],
                'Disk' => [
                    'Free' => Text::sizeToString((int)$diskFree),
                    'Total' => Text::sizeToString((int)$diskTotal),
                    'Used' => $diskTotal ? round(100 - $diskFree / $diskTotal * 100) . ' %' : '-',
                ],
                'Memory' => [
                    'Free' => $memory['MemTotal'] ? Text::sizeToString($memory['MemAvailable']) : '-',
                    'Total' => $memory['MemTotal'] ? Text::sizeToString($memory['MemTotal']) : '-',
                    'Used' => $memory['MemTotal'] ?
                        round(100 - $memory['MemAvailable'] / $memory['MemTotal'] * 100) . ' %' : '-',
                ],
            ],
            'mail' => [
                'Server' => Config::get('smtp_host') ? Config::get('smtp_host') . ':' . Config::get('smtp_port', 587) :
                    'Not configured',
                'Login' => Config::get('smtp_username') ?: 'None',
                'From' => Config::get('mail_from') ?: '-',
            ],
            'mailConfigured' => (bool)Config::get('smtp_host'),
        ], 'System Info');
    }

    /**
     * Route that sends a test email to the logged-in user.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function testEmail(Request $request, Response $response): Response
    {
        $email = $this->currentUser()->email;

        try {
            Mailer::send($email, 'Test email', 'This is a test email from ' . Config::get('site_name') . '.');
        } catch (MailException $e) {
            return $response->sendError($e->getMessage());
        }

        return $response->sendSuccess(['email' => $email]);
    }
}

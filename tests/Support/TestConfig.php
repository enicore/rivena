<?php
namespace Tests\Support;

use Enicore\Rivena\Classes\Config;

/**
 * Loads config values, from a temporary config file, with the required ones.
 */
final class TestConfig
{
    public static function load(array $values = []): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rivena_config');
        $values += ['app_key' => 'key', 'site_name' => 'Site', 'site_url' => 'https://example.com',
            'db_host' => 'localhost', 'db_port' => 3306, 'db_database' => 'db', 'db_username' => 'user',
            'db_password' => 'pass'];
        file_put_contents($file, '<?php $config = ' . var_export($values, true) . ';');
        Config::load($file, '');
        unlink($file);
    }
}

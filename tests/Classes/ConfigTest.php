<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    // the values Config requires
    private const REQUIRED = "['app_key' => 'k', 'site_name' => 's', 'site_url' => 'https://example.com', " .
        "'db_host' => 'h', 'db_port' => 1, 'db_database' => 'd', 'db_username' => 'u', 'db_password' => 'p']";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rivena_config_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->dir/{,.}*", GLOB_BRACE | GLOB_NOSORT) ?: []);
        rmdir($this->dir);
    }

    public function testFileAndEnv(): void
    {
        file_put_contents("$this->dir/config.php", "<?php \$config = " . self::REQUIRED . " + ['name' => 'file', 'port' => 3306, 'rate' => 0.5, " .
            "'debug' => false, 'kept' => 'file'];");
        file_put_contents("$this->dir/.env", "# a comment\n\nname=env\nport= 3307 \nrate=1.5\ndebug=true\n" .
            "single='a \\t #b'\ndouble=\"a \\\"b\\\" \\\\c\"\nempty=\nequals=a=b\nnot a value\n");
        Config::load("$this->dir/config.php", "$this->dir/.env");

        // the .env values override the file's, with the file's types
        $this->assertSame(['env', 3307, 1.5, true, 'file'], [Config::get('name'), Config::get('port'),
            Config::get('rate'), Config::get('debug'), Config::get('kept')]);
        $this->assertSame(['a \\t #b', 'a "b" \\c', '', 'a=b'], [Config::get('single'), Config::get('double'),
            Config::get('empty'), Config::get('equals')]);
        $this->assertNull(Config::get('not a value'));
        $this->assertSame('default', Config::get('missing', 'default'));
    }

    public function testEnvOnly(): void
    {
        file_put_contents("$this->dir/.env", "app_key=k\nsite_name=s\nsite_url=https://example.com\ndb_host=h\n" .
            "db_port=1\ndb_database=d\ndb_username=u\ndb_password=p\n");
        Config::load("$this->dir/missing.php", "$this->dir/.env");
        $this->assertSame(['h', '1'], [Config::get('db_host'), Config::get('db_port')]);
    }

    public function testRequired(): void
    {
        file_put_contents("$this->dir/config.php", "<?php \$config = ['app_key' => 'k', 'site_name' => 's', 'db_host' => ''];");
        file_put_contents("$this->dir/.env", "db_port=1\ndb_database=d\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing config values: site_url, db_host, db_username, db_password');
        Config::load("$this->dir/config.php", "$this->dir/.env");
    }

    /**
     * Without a database (use_database=false), the db_* values aren't needed.
     */
    public function testWithoutDatabase(): void
    {
        file_put_contents("$this->dir/.env", "app_key=k\nsite_name=s\nsite_url=https://example.com\nuse_database=false\n");
        Config::load("$this->dir/missing.php", "$this->dir/.env");
        $this->assertFalse(Config::get('use_database'));
    }

    /**
     * Rivena's defaults, which give their types to the .env values.
     */
    public function testDefaults(): void
    {
        file_put_contents("$this->dir/config.php", '<?php $config = ' . self::REQUIRED . ';');
        Config::load("$this->dir/config.php", '');
        $this->assertSame(['/usr', 6], [Config::get('user_path'), Config::get('event_log_retention_months')]);

        $this->assertSame([['totp', 'webauthn'], false],
            [Config::get('two_factor_methods'), Config::get('two_factor_required')]);

        file_put_contents("$this->dir/.env", "user_path=/panel\nevent_log_retention_months=12\n" .
            "two_factor_methods=totp\ntwo_factor_required=true\n");
        Config::load("$this->dir/config.php", "$this->dir/.env");
        $this->assertSame(['/panel', 12], [Config::get('user_path'), Config::get('event_log_retention_months')]);
        $this->assertSame([['totp'], true],
            [Config::get('two_factor_methods'), Config::get('two_factor_required')]);

        // an empty list
        file_put_contents("$this->dir/.env", "two_factor_methods=\n");
        Config::load("$this->dir/config.php", "$this->dir/.env");
        $this->assertSame([], Config::get('two_factor_methods'));
    }

    /**
     * The password limits must be in their ranges (12, 3 and 60 by default).
     */
    public function testPasswordLimits(): void
    {
        file_put_contents("$this->dir/config.php", '<?php $config = ' . self::REQUIRED . ';');
        Config::load("$this->dir/config.php", '');
        $this->assertSame([12, 3, 60], [Config::get('password_min_length'), Config::get('password_min_score'),
            Config::get('password_max_length')]);

        file_put_contents("$this->dir/.env", "password_max_length=81
");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config value password_max_length must be from 40 to 80');
        Config::load("$this->dir/config.php", "$this->dir/.env");
    }

    /**
     * site_url is the site's https:// address; WebAuthn is on by default.
     */
    public function testSiteUrl(): void
    {
        file_put_contents("$this->dir/config.php", '<?php $config = ' . self::REQUIRED . ';');
        Config::load("$this->dir/config.php", '');
        $this->assertSame(['totp', 'webauthn'], Config::get('two_factor_methods'));

        foreach (['http://example.com', 'example.com', 'https://'] as $url) {
            file_put_contents("$this->dir/.env", "site_url=$url\n");
            try {
                Config::load("$this->dir/config.php", "$this->dir/.env");
                $this->fail($url);
            } catch (RuntimeException $e) {
                $this->assertSame('Config value site_url must be the site\'s https:// address', $e->getMessage());
            }
        }
    }
}

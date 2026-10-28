<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Console;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(Console::class)]
class ConsoleTest extends TestCase
{
    private const string SUCCESS = "\n┌──────────────────┐\n│ SETUP SUCCESSFUL │\n└──────────────────┘\n";

    private string $root;
    private ?string $database = null;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rivena_console_' . uniqid();
        mkdir($this->root);
        file_put_contents("$this->root/composer.json", '{"require": {"enicore/rivena": "^1.0"}, "scripts": ' .
            '{"post-install-cmd": "other"}}');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        $this->database && $this->mysql()?->exec("DROP DATABASE IF EXISTS `$this->database`");
    }

    // runs the command in another process, with the answers on its STDIN: [exit code, output, errors]
    private function rivena(string $command, string $answers = ''): array
    {
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) .
            '; exit(new Enicore\Rivena\Classes\Console(' . var_export($this->root, true) . ')->run(' .
            var_export([$command], true) . '));';
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        fwrite($pipes[0], $answers);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);

        return [proc_close($process), $output, $errors];
    }

    // the dev environment's MySQL server (mysql:3306), or null
    private function mysql(): ?PDO
    {
        try {
            return new PDO('mysql:host=mysql', 'agent', 'agent', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Without the site URL or a database, init fails and creates nothing; an existing .env gets what it can.
     */
    public function testInitFailsWithoutUrlOrDatabase(): void
    {
        $composer = file_get_contents("$this->root/composer.json");
        $files = fn() => array_values(array_diff(scandir($this->root), ['.', '..']));

        [$code, $output, $errors] = $this->rivena('init');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Site URL: ', $output);
        $this->assertSame("\nInit needs the site URL and the database. Run init again.\n", $errors);

        [$code, $output] = $this->rivena('init', "example.com\n");
        $this->assertSame(1, $code);
        $this->assertStringContainsString('DB host [mysql]: ', $output);
        $this->assertSame(['composer.json'], $files());
        $this->assertSame($composer, file_get_contents("$this->root/composer.json"));

        // an existing .env without the database gets the site URL and app_key, and init still fails
        file_put_contents("$this->root/.env", "mine=1\nsite_url=\napp_key=\n");
        [$code, $output] = $this->rivena('init', "example.com\n");

        $env = file_get_contents("$this->root/.env");
        $this->assertSame(1, $code);
        $this->assertStringStartsWith("mine=1\n", $env);
        $this->assertMatchesRegularExpression('#^site_url=https://example\.com$#m', $env);
        $this->assertMatchesRegularExpression('/^app_key=[0-9a-f]{64}$/m', $env);
        $this->assertStringContainsString('Missing config values: db_host', $output);
        $this->assertSame(['.env', 'composer.json'], $files());
    }

    /**
     * The site URL is asked until it's one; the database until it connects, with all of name, username and password.
     */
    public function testInitAnswers(): void
    {
        [$code, $output] = $this->rivena('init', "not an address\nhttp://example.com/\n127.0.0.1\n1\ndb\nuser\n\n" .
            "\n\n\n\nsecret\n");

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/\n┌─+┐\n│ RIVENA SETUP │\n└─+┘\n\n^Site URL: Enter the site\'s URL\.\n' .
            'Site URL: DB host \[mysql\]: DB port \[3306\]: DB name: DB username: ' .
            'DB password: Enter the database name, username and password\.\n.*DB password: Can\'t connect: .*' .
            '\nDB host \[127\.0\.0\.1\]: \n$/msu', $output);
        $this->assertFileDoesNotExist("$this->root/.env");
    }

    /**
     * .env's values are set only when they're empty or missing, quoted when they need it.
     */
    public function testSetEnv(): void
    {
        $password = 'p\'a #s"\\x';
        $setEnv = new ReflectionMethod(Console::class, 'setEnv');
        $console = new Console($this->root);
        $env = file_get_contents(__DIR__ . '/../../init/env');
        $values = ['db_host' => '127.0.0.1', 'db_port' => '1', 'db_database' => 'db', 'db_username' => 'user',
            'db_password' => $password, 'site_url' => 'https://example.com', 'app_key' => 'k', 'mine' => 'new'];
        foreach ($values as $name => $value) {
            $env = $setEnv->invoke($console, $env, $name, $value);
        }
        // a value that's set stays
        $env = $setEnv->invoke($console, $env, 'db_host', 'other');
        file_put_contents("$this->root/.env", $env);

        Config::load(__DIR__ . '/../../init/src/config.php', "$this->root/.env");
        $this->assertSame(['127.0.0.1', '1', 'db', 'user', $password, 'new'], [Config::get('db_host'),
            Config::get('db_port'), Config::get('db_database'), Config::get('db_username'), Config::get('db_password'),
            Config::get('mine')]);
    }

    public function testInitDatabase(): void
    {
        if (!$mysql = $this->mysql()) {
            $this->markTestSkipped('No MySQL server (mysql:3306)');
        }

        $this->database = 'agent_rivena_console_' . uniqid();
        // Rivena in the project's vendor, for the styles
        mkdir("$this->root/vendor/enicore", 0777, true);
        symlink(dirname(__DIR__, 2), "$this->root/vendor/enicore/rivena");

        // a wrong database password first; then the admin: a wrong email, a short and a weak password first
        [$code, $output] = $this->rivena('init', "example.com\nmysql\n\n$this->database\nagent\nwrong\n\n\n\n\n" .
            "agent\nadmin\nboss@example.com\nshort\nlong password\nlantern orbit 47\n");

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Can't connect: ", $output);
        $this->assertStringContainsString("Enter an email address.\n", $output);
        $this->assertStringContainsString("Use at least 12 characters.\n", $output);
        $this->assertStringContainsString("Too weak.", $output);
        $this->assertStringEndsWith(self::SUCCESS, $output);

        $env = file_get_contents("$this->root/.env");
        $this->assertMatchesRegularExpression('/^app_key=[0-9a-f]{64}$/m', $env);
        $this->assertMatchesRegularExpression('/^admins=1$/m', $env);
        $this->assertMatchesRegularExpression('/^db_host=mysql$/m', $env);
        $this->assertMatchesRegularExpression("/^db_database=$this->database\$/m", $env);
        // only its owner can read it
        $this->assertSame(0600, fileperms("$this->root/.env") & 0777);
        $this->assertFileExists("$this->root/.gitignore");
        $this->assertFileExists("$this->root/public/index.php");
        $this->assertFileExists("$this->root/src/Controllers/HomeController.php");
        $this->assertFileExists("$this->root/src/views/home.php");
        // Rivena's views that projects change
        $views = ['layout', 'admin/dashboard', 'admin/partials/head', 'admin/partials/navbar', 'admin/partials/sidebar'];
        foreach ($views as $view) {
            $this->assertFileEquals(dirname(__DIR__, 2) . "/src/views/$view.php", "$this->root/src/views/$view.php");
        }
        $this->assertFileExists("$this->root/public/fonts/ubuntu-sans-latin.woff2");
        $this->assertSame([
            'require' => ['enicore/rivena' => '^1.0'],
            'scripts' => ['post-install-cmd' => ['other', 'rivena publish'], 'post-update-cmd' => ['rivena publish']],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], json_decode(file_get_contents("$this->root/composer.json"), true));

        $mysql->exec("USE `$this->database`");
        $tables = $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('events', $tables);
        $this->assertContains('user_remember_tokens', $tables);
        $this->assertContains('login_attempts', $tables);
        $this->assertSame(['InnoDB'], $mysql->query("SELECT DISTINCT engine FROM information_schema.tables
            WHERE table_schema = '$this->database'")->fetchAll(PDO::FETCH_COLUMN));
        $user = $mysql->query('SELECT id, email, password, disabled, created_at, public_id FROM users')
            ->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $user);
        $this->assertSame('boss@example.com', $user[0]['email']);
        $this->assertTrue(password_verify('lantern orbit 47', $user[0]['password']));
        $this->assertSame(0, (int)$user[0]['disabled']);
        $this->assertEqualsWithDelta(time(), (int)$user[0]['created_at'], 10);
        $this->assertNull($user[0]['public_id']); // set on first use

        // again, with .env: no questions, nothing added; an empty admins stays empty
        file_put_contents("$this->root/.env", preg_replace('/^admins=1$/m', 'admins=', $env));
        [$code, $output] = $this->rivena('init');

        $this->assertSame(0, $code);
        $this->assertSame("\n┌──────────────┐\n│ RIVENA SETUP │\n└──────────────┘\n\n" . self::SUCCESS, $output);
        $this->assertMatchesRegularExpression('/^admins=$/m', file_get_contents("$this->root/.env"));
        $this->assertSame(1, (int)$mysql->query('SELECT COUNT(*) FROM users')->fetchColumn());
        // the scripts aren't added again
        $this->assertSame(['other', 'rivena publish'],
            json_decode(file_get_contents("$this->root/composer.json"), true)['scripts']['post-install-cmd']);
    }

    /**
     * Without answers (docker exec without -i) the admin gets the defaults: admin@ the site's host and the random
     * password its prompt shows.
     */
    public function testInitAdminDefaults(): void
    {
        if (!$mysql = $this->mysql()) {
            $this->markTestSkipped('No MySQL server (mysql:3306)');
        }

        $this->database = 'agent_rivena_console_' . uniqid();
        [$code, $output] = $this->rivena('init', "www.example.com\nmysql\n\n$this->database\nagent\nagent\n");

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Admin email [admin@www.example.com]: ", $output);
        $this->assertSame(1, preg_match('/Admin password \[([a-zA-Z0-9]{20})\]: /', $output, $match));
        $this->assertStringEndsWith(self::SUCCESS, $output);
        $this->assertMatchesRegularExpression('/^admins=1$/m', file_get_contents("$this->root/.env"));
        $mysql->exec("USE `$this->database`");
        $this->assertSame('admin@www.example.com', $mysql->query('SELECT email FROM users')->fetchColumn());
        $this->assertTrue(password_verify($match[1], $mysql->query('SELECT password FROM users')->fetchColumn()));
    }

    /**
     * A new database gets Rivena's tables, then the app's migrations; an existing one the new migrations. A failed one
     * isn't recorded.
     */
    public function testMigrate(): void
    {
        if (!$mysql = $this->mysql()) {
            $this->markTestSkipped('No MySQL server (mysql:3306)');
        }

        $this->database = 'agent_rivena_console_' . uniqid();
        mkdir("$this->root/src");
        copy(__DIR__ . '/../../init/src/config.php', "$this->root/src/config.php");
        file_put_contents("$this->root/.env", "app_key=k\nsite_url=https://example.com\ndb_host=mysql\n" .
            "db_port=3306\ndb_database=$this->database\ndb_username=agent\ndb_password=agent\n");
        mkdir("$this->root/sql/migrations", 0777, true);
        file_put_contents("$this->root/sql/migrations/001-app.sql", "CREATE TABLE app (id int);\n");
        $tables = fn() => $mysql->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $migrations = fn() => $mysql->query("SELECT name FROM rivena_migrations WHERE name LIKE 'app/%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([0, "Ran the migrations: app/001-app.sql\n", ''], $this->rivena('migrate'));
        $mysql->exec("USE `$this->database`");
        $this->assertContains('users', $tables());
        $this->assertContains('app', $tables());
        $this->assertSame(['app/001-app.sql'], $migrations());

        file_put_contents("$this->root/sql/migrations/002-app.sql", "-- a comment\nINSERT INTO app VALUES (2);\n");
        $this->assertSame([0, "Ran the migrations: app/002-app.sql\n", ''], $this->rivena('migrate'));
        $this->assertSame(2, (int)$mysql->query('SELECT id FROM app')->fetchColumn());
        $this->assertSame([0, "The database is up to date\n", ''], $this->rivena('migrate'));

        file_put_contents("$this->root/sql/migrations/003-bad.sql", "CREATE TABLE third (id int);\nNOT SQL;\n");
        [$code, , $errors] = $this->rivena('migrate');
        $this->assertSame(1, $code);
        $this->assertStringStartsWith('The migration app/003-bad.sql failed: SQLSTATE[42000]', $errors);
        $this->assertSame(['app/001-app.sql', 'app/002-app.sql'], $migrations());
        unlink("$this->root/sql/migrations/003-bad.sql");

        // an existing database without the migrations' table runs them all
        $mysql->exec('DROP TABLE rivena_migrations, app');
        $this->assertSame([0, "Ran the migrations: app/001-app.sql, app/002-app.sql\n", ''], $this->rivena('migrate'));
        $this->assertSame(['app/001-app.sql', 'app/002-app.sql'], $migrations());
    }

    /**
     * bin/rivena works in the project of Composer's vendor/bin/rivena, whose autoloader path is
     * vendor/bin/../autoload.php.
     */
    public function testBinFindsTheProject(): void
    {
        mkdir("$this->root/vendor/bin", 0777, true);
        file_put_contents("$this->root/vendor/autoload.php",
            '<?php return require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';');
        $code = '$_composer_autoload_path = ' . var_export("$this->root/vendor/bin/../autoload.php", true) .
            '; $argv = ["rivena", "publish"]; require ' . var_export(dirname(__DIR__, 2) . '/bin/rivena', true) . ';';
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code), $output, $code);

        $this->assertSame(0, $code);
        $this->assertFileExists("$this->root/public/fonts/ubuntu-sans-latin.woff2");
    }

    public function testPublish(): void
    {
        mkdir("$this->root/public/fonts", 0777, true);
        file_put_contents("$this->root/public/fonts/ubuntu-sans-latin.woff2", 'old');

        $this->assertSame([0, '', ''], $this->rivena('publish'));
        $this->assertFileEquals(__DIR__ . '/../../dist/fonts/ubuntu-sans-latin.woff2',
            "$this->root/public/fonts/ubuntu-sans-latin.woff2");
    }

    public function testUsage(): void
    {
        $this->assertSame([1, '', "Usage: vendor/bin/rivena init | migrate | publish | build\n"], $this->rivena(''));
    }
}

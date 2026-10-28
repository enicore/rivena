<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use FilesystemIterator;
use PDO;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * The vendor/bin/rivena commands: init (sets up a new project), migrate (updates the database of .env), publish (copies
 * the fonts to public/fonts; composer install and update run it) and build (the styles and scripts, see Assets).
 *
 * @package Enicore\Rivena
 */
final class Console
{
    /**
     * Creates the console for the project in the directory.
     *
     * @param string $root
     */
    public function __construct(private readonly string $root)
    {
    }

    /**
     * Runs the command and returns the exit code; errors go to STDERR.
     *
     * @param array $args
     * @return int
     */
    public function run(array $args): int
    {
        try {
            switch ($args[0] ?? '') {
                case 'init':
                    $this->init();
                    break;
                case 'migrate':
                    $ran = $this->migrate($this->connectEnv());
                    echo $ran ? 'Ran the migrations: ' . implode(', ', $ran) . "\n" : "The database is up to date\n";
                    break;
                case 'publish':
                    $this->copy(dirname(__DIR__, 2) . '/assets/dist/fonts', 'public/fonts', true);
                    break;
                case 'build':
                    new Assets($this->root)->build(true);
                    break;
                default:
                    throw new RuntimeException('Usage: vendor/bin/rivena init | migrate | publish | build');
            }
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }

        return 0;
    }

    /**
     * Sets up a new project. First asks for the site URL (unless .env has it) and, without .env, the database, and
     * fails without them before creating anything (an existing .env gets what it can). Then copies the project's files
     * (never replacing one), sets up composer.json, the styles and scripts, .env and the database, and asks for the
     * first admin. Prints only the questions and the errors.
     *
     * @return void
     * @throws Throwable
     */
    private function init(): void
    {
        echo "\n┌──────────────┐\n│ RIVENA SETUP │\n└──────────────┘\n\n";

        $rivena = dirname(__DIR__, 2);
        $file = "$this->root/.env";
        $exists = is_file($file);
        $env = file_get_contents($exists ? $file : "$rivena/init/env");
        $pdo = null;

        // the site URL as https://host[/path]: "example.com" and "http://example.com/" are https://example.com
        while (!preg_match('/^site_url=\S/m', $env) && ($answer = $this->ask('Site URL')) !== null) {
            $url = 'https://' . rtrim(preg_replace('#^https?://#i', '', $answer), '/');

            if (filter_var($url, FILTER_VALIDATE_URL) && !str_contains($url, '?') && !str_contains($url, '#')) {
                $env = $this->setEnv($env, 'site_url', $url);
            } else {
                echo "Enter the site's URL.\n";
            }
        }

        $env = $this->setEnv($env, 'app_key', bin2hex(random_bytes(32)));
        $hasUrl = preg_match('/^site_url=\S/m', $env) === 1;

        // an existing .env gets what it can, then init uses its database
        if ($exists) {
            file_put_contents($file, $env);

            try {
                $pdo = $hasUrl ? $this->connectEnv() : null;
            } catch (Throwable $e) {
                echo "Can't use the database of .env: {$e->getMessage()}\n";
            }
        }

        // a new .env's database, asked until it connects; all are required (no passwordless database accounts)
        $db = ['host' => 'mysql', 'port' => '3306', 'database' => '', 'username' => '', 'password' => ''];
        $questions = ['host' => 'DB host', 'port' => 'DB port', 'database' => 'DB name', 'username' => 'DB username',
            'password' => 'DB password'];

        while (!$exists && $hasUrl && !$pdo) {
            foreach ($db as $key => $value) {
                // the password is hidden and has no default, which would show it when asking again
                $answer = $this->ask($questions[$key], $key === 'password' ? '' : $value, $key === 'password');

                if ($answer === null) {
                    break 2;
                }

                $db[$key] = $answer;
            }

            if ($db['database'] === '' || $db['username'] === '' || $db['password'] === '') {
                echo "Enter the database name, username and password.\n";
                continue;
            }

            try {
                $pdo = $this->connect($db);
            } catch (PDOException $e) {
                echo "Can't connect: {$e->getMessage()}\n";
            }
        }

        if (!$pdo) {
            throw new RuntimeException("\nInit needs the site URL and the database. Run init again.");
        }

        // the project's files, and the views projects change (the others stay in Rivena and can be replaced later);
        // gitignore is named differently in Rivena, so it doesn't affect Rivena's repo
        $this->copy("$rivena/init/src", 'src');
        $this->copy("$rivena/init/public", 'public');
        $this->copy("$rivena/init/gitignore", '.gitignore');
        $this->copy("$rivena/src/views/layout.php", 'src/views/layout.php');
        $this->copy("$rivena/src/views/admin/dashboard.php", 'src/views/admin/dashboard.php');
        $this->copy("$rivena/src/views/admin/partials/head.php", 'src/views/admin/partials/head.php');
        $this->copy("$rivena/src/views/admin/partials/navbar.php", 'src/views/admin/partials/navbar.php');
        $this->copy("$rivena/src/views/admin/partials/sidebar.php", 'src/views/admin/partials/sidebar.php');
        $this->copy("$rivena/assets/dist/fonts", 'public/fonts');

        // the App namespace, and the fonts copied again on composer install and update
        $composer = json_decode(file_get_contents("$this->root/composer.json"), true);
        $composer['autoload']['psr-4']['App\\'] ??= 'src/';

        foreach (['post-install-cmd', 'post-update-cmd'] as $event) {
            $scripts = (array)($composer['scripts'][$event] ?? []);
            in_array('rivena publish', $scripts, true) || $scripts[] = 'rivena publish';
            $composer['scripts'][$event] = $scripts;
        }

        file_put_contents("$this->root/composer.json",
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        // Composer sets COMPOSER_BINARY for its scripts; otherwise it's on the PATH
        $composerBinary = escapeshellarg(getenv('COMPOSER_BINARY') ?: 'composer');
        exec("$composerBinary dump-autoload --quiet --working-dir=" . escapeshellarg($this->root) . ' 2>&1', $output,
            $code);

        if ($code !== 0) {
            echo "Couldn't run composer, run `composer dump-autoload`\n";
        }

        try {
            new Assets($this->root)->build(true);
        } catch (RuntimeException $e) {
            echo $e->getMessage() . " Build the styles and scripts later: vendor/bin/rivena build\n";
        }

        if (!$exists) {
            foreach ($db as $key => $value) {
                $env = $this->setEnv($env, "db_$key", $value);
            }

            file_put_contents($file, $env);
            // it has the app key and the database password, so other users of the server can't read it
            chmod($file, 0600);
        }

        $this->migrate($pdo);

        // the first admin, if there are no users; without answers (no terminal, e.g. docker exec without -i) the
        // defaults, as a site always has an admin
        if (!$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
            // the password rules
            Config::load("$this->root/src/config.php", $file);
            $defaultEmail = 'admin@' . parse_url(Config::get('site_url'), PHP_URL_HOST);
            $defaultPassword = Crypt::generateRandomToken(20,
                'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789');

            while (($email = $this->ask('Admin email', $defaultEmail)) !== null &&
                !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo "Enter an email address.\n";
            }

            $email ??= $defaultEmail;

            // shown while it's typed, like the default, so it can be noted down
            while (($password = $this->ask('Admin password', $defaultPassword)) !== null &&
                $error = Auth::checkPassword($password, $email)[1]) {
                echo "$error\n";
            }

            $password ??= $defaultPassword;

            $pdo->prepare('INSERT INTO users (email, password, created_at) VALUES (?, ?, ?)')
                ->execute([$email, password_hash($password, PASSWORD_ARGON2ID), time()]);
            file_put_contents($file, $this->setEnv(file_get_contents($file), 'admins', $pdo->lastInsertId()));
        }

        echo "\n┌──────────────────┐\n│ SETUP SUCCESSFUL │\n└──────────────────┘\n";
    }

    /**
     * Creates Rivena's tables in a new database (without users), then runs the migrations it doesn't have, in name
     * order: Rivena's (sql/migrations/*.sql), then the app's (the project's sql/migrations/*.sql, recorded as
     * "app/<file>"). A new database has Rivena's migrations' changes already, so they're only recorded; the app's
     * always run (the app's first migration creates its tables). Returns the migrations that ran.
     *
     * @param PDO $pdo
     * @return array
     * @throws RuntimeException
     */
    private function migrate(PDO $pdo): array
    {
        $sql = dirname(__DIR__, 2) . '/sql';

        // a file's statements, without the comments
        $statements = function (string $file): array {
            $text = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
            return array_filter(array_map('trim', preg_split('/;\s*$/m', $text)));
        };

        $new = !in_array('users', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true);

        // an existing database without migrations gets their table
        foreach ($statements("$sql/app.sql") as $statement) {
            if ($new || str_contains($statement, '`rivena_migrations`')) {
                $pdo->exec($statement);
            }
        }

        // the name each is recorded with => [its file, whether it runs]
        $migrations = [];
        foreach (glob("$sql/migrations/*.sql") as $file) {
            $migrations[basename($file)] = [$file, !$new];
        }
        foreach (glob("$this->root/sql/migrations/*.sql") as $file) {
            $migrations['app/' . basename($file)] = [$file, true];
        }

        $done = $pdo->query('SELECT name FROM rivena_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $ran = [];

        foreach (array_diff_key($migrations, array_flip($done)) as $name => [$file, $run]) {
            if ($run) {
                try {
                    foreach ($statements($file) as $statement) {
                        $pdo->exec($statement);
                    }
                } catch (PDOException $e) {
                    throw new RuntimeException("The migration $name failed: {$e->getMessage()}");
                }

                $ran[] = $name;
            }

            $pdo->prepare('INSERT INTO rivena_migrations (name, created_at) VALUES (?, ?)')->execute([$name, time()]);
        }

        return $ran;
    }

    /**
     * Returns a connection to the database of .env; before init copies it, a new project's config is init's.
     *
     * @return PDO
     * @throws RuntimeException
     * @throws PDOException
     */
    private function connectEnv(): PDO
    {
        $config = "$this->root/src/config.php";
        Config::load(is_file($config) ? $config : dirname(__DIR__, 2) . '/init/src/config.php', "$this->root/.env");

        return $this->connect(['host' => Config::get('db_host'), 'port' => Config::get('db_port'),
            'database' => Config::get('db_database'), 'username' => Config::get('db_username'),
            'password' => Config::get('db_password')]);
    }

    /**
     * Returns a connection to the database, creating the database if it doesn't exist.
     *
     * @param array $db
     * @return PDO
     * @throws PDOException
     */
    private function connect(array $db): PDO
    {
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $name = '`' . str_replace('`', '``', $db['database']) . '`';

        try {
            return new PDO("$dsn;dbname={$db['database']}", $db['username'], $db['password'], $options);
        } catch (PDOException $e) {
            // 1049: unknown database
            if (($e->errorInfo[1] ?? null) !== 1049) {
                throw $e;
            }
        }

        $pdo = new PDO($dsn, $db['username'], $db['password'], $options);
        $pdo->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE $name");

        return $pdo;
    }

    /**
     * Asks the question and returns the answer, the default for an empty one, or null if the input ran out. A hidden
     * answer isn't shown while it's typed (in a terminal).
     *
     * @param string $question
     * @param string $default
     * @param bool $hidden
     * @return string|null
     */
    private function ask(string $question, string $default = '', bool $hidden = false): ?string
    {
        // hidden before the question, so an answer typed or pasted right away isn't shown either
        $hide = $hidden && stream_isatty(STDIN);
        $hide && exec('stty -echo');
        echo $question . ($default !== '' ? " [$default]" : '') . ': ';
        $answer = fgets(STDIN);
        $hide && exec('stty echo');

        // the new line that wasn't shown
        if ($hide || $answer === false) {
            echo "\n";
        }

        if ($answer === false) {
            return null;
        }

        return trim($answer) === '' ? $default : trim($answer);
    }

    /**
     * Returns .env's text with the value set if it's empty or missing, quoted when it has other characters than
     * letters, digits and ._@:/+- (e.g. a database password).
     *
     * @param string $env
     * @param string $name
     * @param string $value
     * @return string
     */
    private function setEnv(string $env, string $name, string $value): string
    {
        if (!preg_match('/^[\w.@:\/+-]*$/', $value)) {
            $value = str_contains($value, "'") ? '"' . addcslashes($value, '"\\') . '"' : "'$value'";
        }

        if (preg_match("/^$name=[ \\t]*$/m", $env)) {
            return preg_replace_callback("/^$name=[ \\t]*$/m", fn() => "$name=$value", $env);
        }

        return preg_match("/^$name=/m", $env) ? $env : rtrim($env, "\n") . "\n$name=$value\n";
    }

    /**
     * Copies the file, or the directory's files, into the project; an existing file is kept unless replace is true.
     *
     * @param string $from
     * @param string $to
     * @param bool $replace
     * @return void
     */
    private function copy(string $from, string $to, bool $replace = false): void
    {
        $files = is_dir($from) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from,
            FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo($from)];

        foreach ($files as $file) {
            $target = "$this->root/$to" . substr($file->getPathname(), strlen($from));

            if ($replace || !file_exists($target)) {
                is_dir(dirname($target)) || mkdir(dirname($target), 0775, true);
                copy($file->getPathname(), $target);
            }
        }
    }
}

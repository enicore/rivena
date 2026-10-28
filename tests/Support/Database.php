<?php
namespace Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * An in-memory SQLite database for Eloquent, with the tables of sql/app.sql that Rivena's classes use.
 */
final class Database
{
    public static function create(): Capsule
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $pdo = $capsule->getConnection()->getPdo();

        $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at INTEGER NOT NULL,
            user_id INTEGER, user_email TEXT, ip TEXT, country TEXT NOT NULL DEFAULT "", action TEXT NOT NULL,
            message TEXT NOT NULL DEFAULT "",
            user_agent TEXT NOT NULL DEFAULT "")');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE,
            email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, disabled INTEGER NOT NULL DEFAULT 0,
            session_version INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, last_login_at INTEGER)');
        $pdo->exec('CREATE TABLE user_remember_tokens (selector TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE, hash TEXT NOT NULL,
            session_version INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE password_resets (user_id INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
            hash TEXT NOT NULL UNIQUE, created_at INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE user_settings (user_id INTEGER PRIMARY KEY REFERENCES users (id) ON DELETE CASCADE,
            settings TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at INTEGER NOT NULL,
            ip TEXT NOT NULL, email TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE)');
        $pdo->exec('CREATE TABLE user_roles (user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            role_id INTEGER NOT NULL REFERENCES roles (id) ON DELETE CASCADE, PRIMARY KEY (user_id, role_id))');
        $pdo->exec('CREATE TABLE role_permissions (role_id INTEGER NOT NULL REFERENCES roles (id) ON DELETE CASCADE,
            permission TEXT NOT NULL, PRIMARY KEY (role_id, permission))');
        $pdo->exec('CREATE TABLE user_two_factor (user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            method TEXT NOT NULL, data TEXT NOT NULL, created_at INTEGER NOT NULL, PRIMARY KEY (user_id, method))');
        $pdo->exec('CREATE TABLE error_log (id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT NOT NULL UNIQUE,
            level TEXT NOT NULL, message TEXT NOT NULL, path TEXT NOT NULL, count INTEGER NOT NULL,
            first_at INTEGER NOT NULL, last_at INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO roles (id, name) VALUES (1, 'User')");
        $pdo->exec("INSERT INTO role_permissions (role_id, permission) VALUES (1, 'login')");
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, size INTEGER NOT NULL)');

        return $capsule;
    }
}

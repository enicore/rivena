<?php
require __DIR__ . '/../vendor/autoload.php';

// sessions are started by the tests in the CLI: keep their files out of the system's session directory
$sessionDir = sys_get_temp_dir() . '/rivena_test_sessions';
is_dir($sessionDir) || mkdir($sessionDir);
ini_set('session.save_path', $sessionDir);

// the public directory of Web::versionedUrl() and Web::assetsVersion()
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/Fixtures/index.php';

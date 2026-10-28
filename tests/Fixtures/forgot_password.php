<?php
// AuthControllerTest: posts the email (argv[2], JSON) to the forgot password page of the project (argv[1]), in SQLite
// with the users: 1 can reset its password, 2 an admin (admins), 3 disabled; prints the answer, then the email is sent
// when the process ends
use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\Database;

require __DIR__ . '/../../vendor/autoload.php';

$app = new App($argv[1]);
Database::create();
User::forceCreate(['id' => 1, 'email' => 'user@example.com', 'password' => 'x', 'created_at' => 1]);
User::forceCreate(['id' => 2, 'email' => 'admin@example.com', 'password' => 'x', 'created_at' => 1]);
User::forceCreate(['id' => 3, 'email' => 'off@example.com', 'password' => 'x', 'disabled' => 1, 'created_at' => 1]);
DB::table('role_permissions')->insert(['role_id' => 1, 'permission' => 'password_reset']);

$request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com/usr/forgot-password')
    ->withHeader('Accept', 'application/json')
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('X-CSRF-Token', Session::getCsrfToken());
$request->getBody()->write(json_encode(['email' => json_decode($argv[2])]));
$response = $app->handle(Request::from($request));
echo $response->getHeaderLine('Content-Length') . "\n" . $response->getBody();

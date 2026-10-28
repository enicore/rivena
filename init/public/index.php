<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Controllers\DashboardController;
use Enicore\Rivena\Middleware\AuthMiddleware;
use Enicore\Rivena\Middleware\CsrfMiddleware;
use Enicore\Rivena\Middleware\SessionMiddleware;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

// the site, with Rivena's pages at user_path (/usr); App::addRivenaRoutes() says how to change them and add the app's
$app = new App(dirname(__DIR__));

$app->get('/', [HomeController::class, 'page']);

// the pages for the logged-in users (Slim runs the last added middleware first)
$app->group(Config::get('user_path'), function (RouteCollectorProxy $group) {
    $group->get('', [DashboardController::class, 'page'])->setName('dashboard');
})->add(CsrfMiddleware::class)->add(AuthMiddleware::class)->add(SessionMiddleware::class);

$app->run();

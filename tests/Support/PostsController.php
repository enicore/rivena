<?php
namespace Tests\Support;

use Enicore\Rivena\App;
use Enicore\Rivena\Controllers\Controller;
use Slim\Factory\AppFactory;

/**
 * A site's controller with permissions.
 */
class PostsController extends Controller
{
    public const array PERMISSIONS = ['posts.edit' => 'Edit posts', 'posts.delete' => 'Delete posts'];

    // makes App's routes a route to this controller, for Role::permissions()
    public static function route(): void
    {
        $app = AppFactory::create();
        $app->get('/posts', [self::class, 'page']);
        $app->get('/other', 'Tests\Support\PageController:home');
        App::$routes = $app->getRouteCollector();
    }
}

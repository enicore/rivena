<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena;

use DI\ContainerBuilder;
use Enicore\Rivena\Classes\Assets;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\ErrorLog;
use Enicore\Rivena\Classes\HttpFactory;
use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Controllers\AdminController;
use Enicore\Rivena\Controllers\AuthController;
use Enicore\Rivena\Controllers\ErrorLogController;
use Enicore\Rivena\Controllers\EventController;
use Enicore\Rivena\Controllers\PermissionsController;
use Enicore\Rivena\Controllers\RolesController;
use Enicore\Rivena\Controllers\SlimErrorController;
use Enicore\Rivena\Controllers\SystemController;
use Enicore\Rivena\Controllers\TwoFactorController;
use Enicore\Rivena\Controllers\UsersController;
use Enicore\Rivena\Middleware\AdminMiddleware;
use Enicore\Rivena\Middleware\AssetsMiddleware;
use Enicore\Rivena\Middleware\AuthMiddleware;
use Enicore\Rivena\Middleware\CsrfMiddleware;
use Enicore\Rivena\Middleware\CspMiddleware;
use Enicore\Rivena\Middleware\LiveReloadMiddleware;
use Enicore\Rivena\Middleware\SecurityHeadersMiddleware;
use Enicore\Rivena\Middleware\SessionMiddleware;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App as SlimApp;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Interfaces\RouteGroupInterface;
use Slim\Routing\RouteCollectorProxy;

/**
 * The site: Slim's app with the config, Eloquent, the container, the middleware and Rivena's pages set up. In
 * public/index.php:
 *
 * $app = new App(dirname(__DIR__));
 * $app->get('/', ...);           // the app's routes
 * $app->run();
 *
 * The options: services (PHP-DI definitions), config_path, env_path, csp (see CspMiddleware).
 *
 * @package Enicore\Rivena
 */
class App extends SlimApp
{
    /**
     * The app's routes, for w_route() in the views.
     */
    public static RouteCollectorInterface $routes;

    /**
     * The groups of Rivena's pages' routes, by page name (getRouteGroup()).
     *
     * @var RouteGroupInterface[]
     */
    private array $routeGroups = [];

    /**
     * Sets up the site in $root and adds Rivena's pages.
     *
     * @param string $root
     * @param array $options
     */
    public function __construct(string $root, array $options = [])
    {
        ErrorLog::register();

        // a configuration error stops the app with a 500 error; the details go to the server's error log only, as
        // Rivena's needs the config and the database
        try {
            Config::load($options['config_path'] ?? "$root/src/config.php", $options['env_path'] ?? "$root/.env");

            // make Eloquent available in all models; it connects on the first query
            $capsule = new Capsule();
            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => Config::get('db_host'),
                'port' => Config::get('db_port'),
                'database' => Config::get('db_database'),
                'username' => Config::get('db_username'),
                'password' => Config::get('db_password'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            // classes that need configuration are created here, the others are autowired
            $builder = new ContainerBuilder();
            $builder->addDefinitions([
                View::class => fn() => new View(["$root/src/views", __DIR__ . '/views']),
                PDO::class => fn() => $capsule->getConnection()->getPdo(),
                // Eloquent's database connection, for queries without models
                Connection::class => fn() => $capsule->getConnection(),
            ]);
            $builder->addDefinitions($options['services'] ?? []);
            $container = $builder->build();
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            exit("Configuration error (see the server's error log).");
        }

        // use our own Request and Response
        $factory = new HttpFactory();
        parent::__construct($factory, $container);
        ServerRequestCreatorFactory::setServerRequestCreator($factory);
        self::$routes = $this->getRouteCollector();

        // slim runs middleware last first: add headers after error middleware, so error pages get them
        $this->addBodyParsingMiddleware();
        $this->addRoutingMiddleware();
        $this->addErrorMiddleware(Config::get('app_env') === 'dev', true, true)
            ->setDefaultErrorHandler(SlimErrorController::class);
        $this->add(new CspMiddleware($options['csp'] ?? null));
        $this->add(new SecurityHeadersMiddleware());

        // dev only: the asset building and the live reload; added last, run first: live reload sees the new files
        if (Config::get('app_env') === 'dev') {
            $this->add(new LiveReloadMiddleware(["$root/src", "$root/public"]));
            $this->add(new AssetsMiddleware(new Assets($root)));
        }

        $this->addRivenaRoutes();
    }

    /**
     * Adds Rivena's pages at user_path (/usr by default), each route named: the login, logout, password resets and
     * password strength for everyone, the account for the logged-in users, the users, roles, permissions, events, error
     * log and system pages for the admins. The dashboard is the app's (init/public/index.php). AuthMiddleware lets in
     * only the users with the "login" permission. Each page's routes are in a group of their own (getRouteGroup()),
     * inside the group with the page's middleware.
     *
     * The app changes them in public/index.php:
     * - remove: $app->removeRoutes('events') removes the page with its actions ("events", "events.list")
     * - replace: remove it, then add the app's route with the same name, so urlFor() and the menu links find it
     * - restrict: $app->getRouteGroup('account')->add(AdminMiddleware::class), the page with its actions, or to a
     *   permission with ->add(new PermissionMiddleware('account.edit')) (a permission in a controller's PERMISSIONS)
     * - add: the app's routes in its own group with the middleware of the group they belong in (Slim runs the last
     *   added first), e.g. an admins' page:
     *   $app->group('/usr', function (RouteCollectorProxy $group) {
     *       $group->get('/reports', [ReportsController::class, 'page'])->setName('reports');
     *   })->add(AdminMiddleware::class)->add(CsrfMiddleware::class)->add(AuthMiddleware::class)
     *       ->add(SessionMiddleware::class);
     *
     * @return void
     */
    private function addRivenaRoutes(): void
    {
        $path = Config::get('user_path');

        // Slim runs the closures bound to the container, so the groups are collected here
        $groups = [];
        $page = function (RouteCollectorProxy $group, string $name, callable $routes) use (&$groups) {
            $groups[$name] = $group->group("/$name", $routes);
        };

        $this->group($path, function (RouteCollectorProxy $group) use ($page) {
            $page($group, 'login', function (RouteCollectorProxy $group) {
                $group->get('', [AuthController::class, 'loginPage'])->setName('login');
                $group->post('', [AuthController::class, 'login'])->setName('login.post')->add(CsrfMiddleware::class);
                $group->post('/two-factor', [AuthController::class, 'loginTwoFactor'])->setName('login.two-factor')
                    ->add(CsrfMiddleware::class);
                $group->post('/two-factor/challenge', [AuthController::class, 'loginTwoFactorChallenge'])
                    ->setName('login.two-factor.challenge')->add(CsrfMiddleware::class);
            });
            $page($group, 'logout', function (RouteCollectorProxy $group) {
                $group->get('', [AuthController::class, 'logout'])->setName('logout');
            });
            $page($group, 'forgot-password', function (RouteCollectorProxy $group) {
                $group->get('', [AuthController::class, 'forgotPasswordPage'])->setName('forgot-password');
                $group->post('', [AuthController::class, 'sendPasswordReset'])->setName('forgot-password.post')
                    ->add(CsrfMiddleware::class);
            });
            $page($group, 'reset-password', function (RouteCollectorProxy $group) {
                $group->get('/{token:[0-9a-f]{64}}', [AuthController::class, 'resetPasswordPage'])
                    ->setName('reset-password');
                $group->post('', [AuthController::class, 'resetPassword'])->setName('reset-password.post')
                    ->add(CsrfMiddleware::class);
            });
            $page($group, 'password-strength', function (RouteCollectorProxy $group) {
                $group->post('', [AuthController::class, 'passwordStrength'])->setName('password-strength')
                    ->add(CsrfMiddleware::class);
            });
        })->add(SessionMiddleware::class);

        $this->group($path, function (RouteCollectorProxy $group) use ($page) {
            $page($group, 'account', function (RouteCollectorProxy $group) {
                $group->get('', [UsersController::class, 'accountPage'])->setName('account');
                $group->post('/save', [UsersController::class, 'saveAccount'])->setName('account.save');
                $group->post('/two-factor/setup', [TwoFactorController::class, 'setUp'])
                    ->setName('account.two-factor.setup');
                $group->post('/two-factor/enable', [TwoFactorController::class, 'enable'])
                    ->setName('account.two-factor.enable');
                $group->post('/two-factor/disable', [TwoFactorController::class, 'disable'])
                    ->setName('account.two-factor.disable');
                $group->post('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
                    ->setName('account.two-factor.recovery-codes');
                $group->post('/two-factor/turn-off', [TwoFactorController::class, 'turnOff'])
                    ->setName('account.two-factor.turn-off');
            });
        })->add(CsrfMiddleware::class)->add(AuthMiddleware::class)->add(SessionMiddleware::class);

        $this->group($path, function (RouteCollectorProxy $group) use ($page) {
            $page($group, 'users', function (RouteCollectorProxy $group) {
                $group->get('', [UsersController::class, 'page'])->setName('users');
                $group->post('/list', [UsersController::class, 'list'])->setName('users.list');
                $group->get('/add', [UsersController::class, 'editPage'])->setName('users.add');
                $group->get('/{id:[a-z0-9]{16}}/edit', [UsersController::class, 'editPage'])->setName('users.edit');
                $group->post('/save', [UsersController::class, 'save'])->setName('users.save');
                $group->post('/disable', [UsersController::class, 'disable'])->setName('users.disable');
                $group->post('/delete', [UsersController::class, 'delete'])->setName('users.delete');
                $group->post('/login-as', [UsersController::class, 'loginAs'])->setName('users.login-as');
                $group->post('/two-factor-reset', [TwoFactorController::class, 'reset'])
                    ->setName('users.two-factor-reset');
            });
            $page($group, 'roles', function (RouteCollectorProxy $group) {
                $group->get('', [RolesController::class, 'page'])->setName('roles');
                $group->post('/save', [RolesController::class, 'save'])->setName('roles.save');
                $group->post('/delete', [RolesController::class, 'delete'])->setName('roles.delete');
            });
            $page($group, 'permissions', function (RouteCollectorProxy $group) {
                $group->get('', [PermissionsController::class, 'page'])->setName('permissions');
                $group->post('/save', [PermissionsController::class, 'save'])->setName('permissions.save');
            });
            $page($group, 'events', function (RouteCollectorProxy $group) {
                $group->get('', [EventController::class, 'page'])->setName('events');
                $group->post('/list', [EventController::class, 'list'])->setName('events.list');
            });
            $page($group, 'error-log', function (RouteCollectorProxy $group) {
                $group->get('', [ErrorLogController::class, 'page'])->setName('error-log');
                $group->post('/list', [ErrorLogController::class, 'list'])->setName('error-log.list');
            });
            $page($group, 'system', function (RouteCollectorProxy $group) {
                $group->get('', [SystemController::class, 'page'])->setName('system');
                $group->post('/test-email', [SystemController::class, 'testEmail'])->setName('system.test-email');
            });
        })->add(AdminMiddleware::class)->add(CsrfMiddleware::class)->add(AuthMiddleware::class)
            ->add(SessionMiddleware::class);

        $this->routeGroups = $groups;
    }

    /**
     * Returns the group of a Rivena page's routes (the page and its actions), e.g. to restrict them:
     * $app->getRouteGroup('account')->add(AdminMiddleware::class). Its middleware runs after the page's (the session,
     * the login), so it knows the user.
     *
     * @param string $name
     * @return RouteGroupInterface
     * @throws RuntimeException
     */
    public function getRouteGroup(string $name): RouteGroupInterface
    {
        // a typo must not leave the page unrestricted
        return $this->routeGroups[$name] ?? throw new RuntimeException("Rivena has no page named $name");
    }

    /**
     * Removes the named routes, each with the routes named after it with a dot: removeRoutes('users') removes "users",
     * "users.list"...
     *
     * @param string ...$names
     * @return void
     */
    public function removeRoutes(string ...$names): void
    {
        foreach ($this->getRouteCollector()->getRoutes() as $route) {
            $name = (string)$route->getName();

            if (array_any($names, fn($remove) => $name === $remove || str_starts_with($name, "$remove."))) {
                $this->getRouteCollector()->removeNamedRoute($name);
            }
        }
    }

    /**
     * Adds the user area's 404 page (the login page for the logged-out users), then runs the app. The 404 route is
     * added last, as it would hide the app's routes with placeholders added after it.
     *
     * @param ServerRequestInterface|null $request
     * @return void
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        $this->get(Config::get('user_path') . '/{path:.+}', [AdminController::class, 'notFoundRoute'])
            ->add(AuthMiddleware::class)->add(SessionMiddleware::class);

        parent::run($request);
    }
}

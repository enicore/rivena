<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Models;

use Enicore\Rivena\App;
use Enicore\Rivena\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A role, which gives its users permissions (the role_permissions table). Role 1 (USER) is every user's, built in;
 * the admins (admins in .env) have every permission without a role.
 *
 * @package Enicore\Rivena
 */
class Role extends Model
{
    public const int USER = 1;

    public $timestamps = false;

    /**
     * Returns the role's users (none for role 1, which every user has).
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Returns the permissions, name => description: Rivena's, then the PERMISSIONS of the routes' controllers.
     *
     * @return array
     */
    public static function permissions(): array
    {
        $permissions = ['login' => 'Log in', 'password_reset' => 'Reset password by email'];

        foreach (isset(App::$routes) ? App::$routes->getRoutes() : [] as $route) {
            $callable = $route->getCallable();
            // [Controller::class, 'method'] or "Controller:method"
            $class = is_array($callable) ? $callable[0] : (is_string($callable) ? explode(':', $callable)[0] : null);

            if (is_string($class) && is_a($class, Controller::class, true)) {
                $permissions += $class::PERMISSIONS;
            }
        }

        return $permissions;
    }
}

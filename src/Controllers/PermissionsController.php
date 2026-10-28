<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Exceptions\InputException;
use Enicore\Rivena\Models\Role;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * The admin Permissions page: the roles' permissions, as checkboxes (the Roles page adds and deletes the roles).
 *
 * @package Enicore\Rivena
 */
class PermissionsController extends AdminController
{
    /**
     * Route that sends the Permissions page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        $permissions = [];

        foreach (Role::permissions() as $name => $description) {
            $permissions[] = ['name' => $name, 'description' => $description];
        }

        return $this->sendPage($request, $response, 'admin/permissions',
            ['permissions' => $permissions, 'roles' => $this->roles()], 'Permissions');
    }

    /**
     * Route that saves the roles' permissions (the unknown ones are ignored); returns the roles as JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function save(Request $request, Response $response): Response
    {
        try {
            $roles = $request->getArray('roles');
        } catch (InputException) {
            return $response->sendError('Invalid request.', 400);
        }

        $existing = Role::pluck('id')->all();

        foreach ($roles as $role) {
            if (!in_array($role['id'] ?? null, $existing, true) || !is_array($role['permissions'] ?? null)) {
                return $response->sendError('Invalid request.', 400);
            }
        }

        DB::transaction(function () use ($roles) {
            foreach ($roles as $role) {
                $permissions = array_intersect(array_keys(Role::permissions()),
                    array_filter($role['permissions'], 'is_string'));
                DB::table('role_permissions')->where('role_id', $role['id'])->delete();
                DB::table('role_permissions')->insert(array_map(fn($permission) => ['role_id' => $role['id'],
                    'permission' => $permission], $permissions));
            }
        });

        $this->logEvent('permissions.updated');

        return $response->sendSuccess(['roles' => $this->roles()]);
    }

    /**
     * Returns the roles (role 1 first) with their permissions.
     *
     * @return array
     */
    protected function roles(): array
    {
        $grants = DB::table('role_permissions')->get()->groupBy('role_id');

        return Role::orderBy('id')->get()->map(fn(Role $role) => ['id' => $role->id, 'name' => $role->name,
            'permissions' => $grants->get($role->id)?->pluck('permission')->all() ?? []])->all();
    }
}

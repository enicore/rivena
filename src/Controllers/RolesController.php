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

/**
 * The admin Roles page: the roles with their number of users, add and rename (in a dialog), delete; the Permissions
 * page gives them permissions.
 *
 * @package Enicore\Rivena
 */
class RolesController extends AdminController
{
    /**
     * Route that sends the Roles page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'admin/roles', ['roles' => $this->roles()], 'Roles');
    }

    /**
     * Route that adds a role (without an ID) or renames one (not role 1); returns the roles as JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function save(Request $request, Response $response): Response
    {
        $id = $request->getInt('id', 0);
        $role = $id ? Role::find($id) : new Role();

        if (!$role || $role->id === Role::USER) {
            return $response->sendError('The role doesn\'t exist.', 404);
        }

        try {
            $name = $request->getString('name', allowEmpty: false);
        } catch (InputException) {
            return $response->sendFieldErrors(['name' => 'Enter a name.']);
        }

        if (mb_strlen($name) > 100) {
            return $response->sendFieldErrors(['name' => 'The name can be at most 100 characters.']);
        }

        // the built-in roles' names are taken
        if (in_array(mb_strtolower($name), ['admin', 'user'], true)
            || Role::where('name', $name)->where('id', '!=', $id)->exists()) {
            return $response->sendFieldErrors(['name' => 'A role has this name.']);
        }

        // unchanged: nothing to save or log
        if ($role->name !== $name) {
            $oldName = $role->name;
            $role->name = $name;
            $role->save();
            $this->logEvent($id ? 'role.renamed' : 'role.created', $id ? "$oldName -> $name" : $name);
        }

        return $response->sendSuccess(['roles' => $this->roles()]);
    }

    /**
     * Route that deletes a role (not role 1); its users lose it. Returns the roles as JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function delete(Request $request, Response $response): Response
    {
        $role = Role::find($request->getInt('id', 0));

        if (!$role || $role->id === Role::USER) {
            return $response->sendError('The role doesn\'t exist.', 404);
        }

        // the database deletes its users' roles and its permissions
        $role->delete();
        $this->logEvent('role.deleted', $role->name);

        return $response->sendSuccess(['roles' => $this->roles()]);
    }

    /**
     * Returns the roles (not role 1), by name, with their number of users.
     *
     * @return array
     */
    protected function roles(): array
    {
        return Role::where('id', '!=', Role::USER)->withCount('users')->orderBy('name')->get()
            ->map(fn(Role $role) => ['id' => $role->id, 'name' => $role->name, 'users' => $role->users_count])->all();
    }
}

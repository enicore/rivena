<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Classes\Timezones;
use Enicore\Rivena\Exceptions\InputException;
use Enicore\Rivena\Exceptions\LoginException;
use Enicore\Rivena\Models\Role;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\Models\UserSettings;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Illuminate\Database\Capsule\Manager as DB;
use Random\RandomException;

/**
 * The admin users pages (list, add, edit with the roles, disable, delete, log in as) and the logged-in user's account
 * page.
 *
 * @package Enicore\Rivena
 */
class UsersController extends AdminController
{
    /**
     * Route that sends the users page, with the table's page, sort and search from the URL.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function page(Request $request, Response $response): Response
    {
        return $this->sendPage($request, $response, 'admin/users', [
            // the table's page, sort and search from the URL (Table in table.js)
            'table' => $this->table($request->getQueryParams()),
            'currentId' => $this->currentUser()->public_id,
        ], 'Users');
    }

    /**
     * Route that returns a page of the users table as JSON.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        return $response->sendSuccess($this->table($request->all()));
    }

    /**
     * Route that sends the add user (/users/add) or edit user (/users/{id}/edit) page.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function editPage(Request $request, Response $response, array $args): Response
    {
        if (!isset($args['id'])) {
            return $this->sendUserPage($request, $response, new User(), 'Add user');
        }

        if (!$user = User::findByPublicId($args['id'])) {
            return $this->notFoundRoute($request, $response, $args);
        }

        return $this->sendUserPage($request, $response, $user, 'Edit user');
    }

    /**
     * Route that sends the logged-in user's account page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function accountPage(Request $request, Response $response): Response
    {
        $tab = $request->getQueryParams()['tab'] ?? '';
        return $this->sendUserPage($request, $response, $this->currentUser(), 'Edit account', true,
            is_string($tab) ? $tab : '');
    }

    /**
     * Route that saves the add or edit user page: a new user without an ID, or changes to one.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function save(Request $request, Response $response): Response
    {
        $id = $request->getString('id', default: '');
        $user = User::findByPublicId($id);

        if ($id !== '' && !$user) {
            return $response->sendError('The user doesn\'t exist.', 404);
        }

        return $this->saveUser($request, $response, $user);
    }

    /**
     * Route that saves the account page, always for the logged-in user.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function saveAccount(Request $request, Response $response): Response
    {
        return $this->saveUser($request, $response, $this->currentUser(), true);
    }

    /**
     * Adds the user (when null) or changes its email and its password if one is given; saves its settings, and its
     * roles if not the account page. Changing the email or password of the logged-in user or of an admin needs the
     * logged-in user's password (checked and throttled like a login).
     *
     * @param Request $request
     * @param Response $response
     * @param User|null $user
     * @param bool $account
     * @return Response
     */
    protected function saveUser(Request $request, Response $response, ?User $user, bool $account = false): Response
    {
        try {
            $roles = $account ? null : $request->getArray('roles');
            $email = $request->getString('email');
            $password = $request->getString('password', default: '');
            $currentPassword = $request->getString('current_password', default: '');
            $settings = [
                'date_format' => $request->getString('date_format'),
                'time_format' => $request->getString('time_format'),
                'timezone' => $request->getString('timezone'),
            ];
        } catch (InputException $e) {
            return $response->sendFieldErrors([$e->getKey() => $e->getMessage()]);
        }

        $errors = [];

        // 249: the users table's column
        if (strlen($email) > 249 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address.';
        } elseif (User::where('email', $email)->where('id', '!=', $user?->id ?? 0)->exists()) {
            $errors['email'] = 'Another user has this email.';
        }

        if (!$user || $password !== '') {
            $errors['password'] = Auth::checkPassword($password, $email, $user->password ?? '')[1];
        }

        if (!in_array($settings['date_format'], UserSettings::DATE_FORMATS, true)) {
            $errors['date_format'] = 'Invalid date format.';
        }

        if (!in_array($settings['time_format'], UserSettings::TIME_FORMATS, true)) {
            $errors['time_format'] = 'Invalid time format.';
        }

        if (!isset(Timezones::getTimezones()[$settings['timezone']])) {
            $errors['timezone'] = 'Invalid time zone.';
        }

        if ($errors = array_filter($errors)) {
            return $response->sendFieldErrors($errors);
        }

        $isCurrent = $user?->id === Auth::id();

        if ($user && ($isCurrent || User::isAdmin($user->id)) && ($email !== $user->email || $password !== '')) {
            try {
                Auth::attempt($this->currentUser()->email, $currentPassword);
            } catch (LoginException $e) {
                return $response->sendFieldErrors(['current_password' =>
                    $e->getCode() === 429 ? $e->getMessage() : 'Wrong password.']);
            }
        }

        if (!$user) {
            // a user; admins in .env makes it an admin
            $user = new User();
            $user->email = $email;
            $user->created_at = time();
            Auth::setPassword($user, $password);
            $this->logEvent('user.created', $email);
        } else {
            $changes = $user->email === $email ? [] : ["email: $user->email -> $email"];
            $user->email = $email;
            $user->save();

            // a link sent to the old address stops working
            if ($changes) {
                DB::table('password_resets')->where('user_id', $user->id)->delete();
            }

            // the session has the email too (the event log's)
            if ($isCurrent) {
                $_SESSION['auth_email'] = $email;
            }

            // logs the user out of the other sessions (the current one stays)
            if ($password !== '') {
                Auth::setPassword($user, $password);
                $changes[] = 'password';
            }

            // the entry's user is the one who made the change, so another user's changes need their email
            $changes = implode(', ', $changes);
            $this->logEvent($isCurrent ? 'account.updated' : 'user.updated',
                $isCurrent ? $changes : $email . ($changes ? " ($changes)" : ''));
        }

        $user->settings->set($settings);
        $user->settings->save();

        if ($roles !== null) {
            $user->roles()->sync(Role::where('id', '!=', Role::USER)->whereIn('id', $roles)->pluck('id'));
        }

        return $response->sendSuccess(['id' => $user->public_id]);
    }

    /**
     * Route that deletes a user (not the logged-in one, nor an admin).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function delete(Request $request, Response $response): Response
    {
        if (!$user = User::findByPublicId($request->getString('id', default: ''))) {
            return $response->sendError('The user doesn\'t exist.', 404);
        }

        if ($user->id === Auth::id()) {
            return $response->sendError('You can\'t delete yourself.', 400);
        }

        if (User::isAdmin($user->id)) {
            return $response->sendError('You can\'t delete an admin.', 400);
        }

        // the database deletes the user's rows in the other tables (settings, roles, remember tokens, reset links)
        $user->delete();
        $this->logEvent('user.deleted', $user->email);

        return $response->sendSuccess();
    }

    /**
     * Route that disables a user (not an admin), who can't log in then, and whose sessions, remember cookies and
     * password reset link end (an enabled user doesn't get them back), or enables it again.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function disable(Request $request, Response $response): Response
    {
        if (!$user = User::findByPublicId($request->getString('id', default: ''))) {
            return $response->sendError('The user doesn\'t exist.', 404);
        }

        if (User::isAdmin($user->id)) {
            return $response->sendError('You can\'t disable an admin.', 400);
        }

        $disabled = $request->getBool('disabled', false);
        $user->disabled = $disabled;

        if ($disabled) {
            Auth::endOtherSessions($user);
            DB::table('password_resets')->where('user_id', $user->id)->delete();
        }

        $user->save();
        $this->logEvent($disabled ? 'user.disabled' : 'user.enabled', $user->email);

        return $response->sendSuccess();
    }

    /**
     * Route that logs the admin in as a user (not an admin), in place of the admin.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function loginAs(Request $request, Response $response): Response
    {
        if (!$user = User::findByPublicId($request->getString('id', default: ''))) {
            return $response->sendError('The user doesn\'t exist.', 404);
        }

        if (User::isAdmin($user->id) || $user->disabled || !User::can('login', $user->id)) {
            return $response->sendError('You can\'t log in as an admin, a disabled user or a user who can\'t log in.',
                400);
        }

        // logged before, so the entry's user is the admin
        $this->logEvent('user.login_as', $user->email);
        Auth::logIn($user);

        return $response->sendSuccess();
    }

    /**
     * Returns a page of the users for the table.
     *
     * @param array $params
     * @return array
     */
    protected function table(array $params): array
    {
        $current = $this->currentUser();

        return $this->dataTable(User::with('roles'), $params, ['email', 'created_at', 'last_login_at'], ['email'],
            fn(User $user) => [
                'id' => $user->public_id,
                'email' => $user->email,
                'admin' => User::isAdmin($user->id),
                'roles' => $user->roles->pluck('name')->sort()->implode(', '),
                'disabled' => $user->disabled,
                'created_at' => $current->formatTime($user->created_at),
                'last_login_at' => $user->last_login_at ? $current->formatTime($user->last_login_at) : '',
            ]);
    }

    /**
     * Returns the response with the user page: the user, the date and time formats, the time zones and the roles (not
     * on the account page, nor for an admin, who has every permission), and the two-factor authentication; the tab is
     * the one open at first ("two-factor", or the account's).
     *
     * @param Request $request
     * @param Response $response
     * @param User $user
     * @param string $title
     * @param bool $account
     * @param string $tab
     * @return Response
     */
    protected function sendUserPage(Request $request, Response $response, User $user, string $title,
                                  bool $account = false, string $tab = ''): Response
    {
        // May 30, so the day and the month can be told apart
        $example = mktime(18, 20, 0, 5, 30);
        $examples = fn(array $formats) => array_map(fn($format) => ['value' => $format,
            'text' => date($format, $example)], $formats);
        $timezones = [];

        foreach (Timezones::getTimezones() as $value => $text) {
            $timezones[] = ['value' => $value, 'text' => $text];
        }

        return $this->sendPage($request, $response, 'admin/user', [
            'user' => [
                // null for a new user
                'id' => $user->public_id,
                'email' => $user->email ?? '',
                'password' => '',
                'date_format' => $user->settings->get('date_format'),
                'time_format' => $user->settings->get('time_format'),
                'timezone' => $user->settings->get('timezone'),
                'disabled' => $user->exists && $user->disabled,
                // with role 1, which every user has (shown, not saved)
                'roles' => [Role::USER, ...$user->roles->pluck('id')->all()],
            ],
            // role 1 first
            'roles' => $account || ($user->exists && User::isAdmin($user->id)) ? [] :
                Role::orderByRaw('id != ?', [Role::USER])->orderBy('name')->get(['id', 'name'])->toArray(),
            'dateFormats' => $examples(UserSettings::DATE_FORMATS),
            'timeFormats' => $examples(UserSettings::TIME_FORMATS),
            'timezones' => $timezones,
            'account' => $account,
            // the logged-in user's page
            'own' => $user->exists && $user->id === Auth::id(),
            // changing one's own or an admin's email or password needs the logged-in user's password (in a dialog)
            'askPassword' => $user->exists && ($user->id === Auth::id() || User::isAdmin($user->id)),
            // the disable and delete buttons: other users than admins (the logged-in one is an admin)
            'canDisableOrDelete' => !$account && $user->exists && !User::isAdmin($user->id),
            // the two-factor authentication tab, of an existing user, when there are methods to set up or the user has
            // one
            'twoFactor' => $user->exists && (TwoFactor::methods() || TwoFactor::enabled($user->id)) ?
                TwoFactorController::state($user) : null,
            'tab' => $tab === 'two-factor' ? 'two-factor' : 'account',
        ], $title);
    }
}

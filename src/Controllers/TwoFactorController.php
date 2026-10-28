<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Mailer;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Exceptions\InputException;
use Enicore\Rivena\Exceptions\LoginException;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\TwoFactor;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * The two-factor authentication tab of the user page (TwoFactor): the logged-in user sets up and removes the methods,
 * turns it off (unless it's required) and sees or renews the recovery codes, each with the current password (checked
 * and throttled like a login); an admin turns another user's off, with the admin's password. Turning it on ends the
 * user's other sessions. The changes go to the event log, and to the user by email (two_factor_email_notifications),
 * so the user notices someone else's.
 *
 * @package Enicore\Rivena
 */
class TwoFactorController extends AdminController
{
    // the seconds to finish a method's setup
    private const int SETUP_DURATION = 900;

    /**
     * Returns the user's two-factor authentication for the user page: the methods with "enabled" and their keys (for
     * the methods with several, null for the others), the number of recovery codes left (null without them), and
     * whether it's required.
     *
     * @param User $user
     * @return array
     */
    public static function state(User $user): array
    {
        $userMethods = TwoFactor::userMethods($user->id);
        $methods = [];

        // the ones to set up, and the user's others (no longer in the config), which the user can remove
        $all = TwoFactor::methods();

        foreach (array_diff(array_keys($userMethods), [TwoFactor::RECOVERY]) as $id) {
            $all[$id] ??= TwoFactor::method($id);
        }

        foreach ($all as $id => $method) {
            $keys = $method->keys($userMethods[$id] ?? []);
            $methods[] = ['id' => $id, 'name' => $method->name(), 'description' => $method->description(),
                'enabled' => array_key_exists($id, $userMethods), 'keys' => $keys === null ? null :
                    array_map(fn($key, $name) => ['id' => $key, 'name' => $name], array_keys($keys), $keys)];
        }

        return [
            'methods' => $methods,
            'recoveryCodes' => array_key_exists(TwoFactor::RECOVERY, $userMethods) ?
                count($userMethods[TwoFactor::RECOVERY]['codes']) : null,
            'required' => TwoFactor::required(),
        ];
    }

    /**
     * Route that starts setting up a method for the logged-in user (or adding a key to a method with several), and
     * returns what the setup shows (e.g. the QR code); the method's data waits in the session until enable().
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function setUp(Request $request, Response $response): Response
    {
        if ($error = $this->checkPassword($request, $response)) {
            return $error;
        }

        $user = $this->currentUser();
        $id = $request->getString('method', 20, '');
        $method = TwoFactor::methods()[$id] ?? null;
        $data = TwoFactor::userMethods($user->id)[$id] ?? null;

        if (!$method || ($data !== null && $method->keys($data) === null)) {
            return $response->sendError('This method can\'t be set up.', 400);
        }

        $setup = $method->setUp($user, $data);
        Session::set('two_factor_setup', ['user_id' => $user->id, 'method' => $id, 'data' => $setup['data'],
            'time' => time()]);

        return $response->sendSuccess($setup['page']);
    }

    /**
     * Route that saves the method of setUp() when the code is right for it, and returns the two-factor state with the
     * recovery codes when they're new (the first method).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function enable(Request $request, Response $response): Response
    {
        $user = $this->currentUser();
        $setup = Session::get('two_factor_setup');
        $valid = is_array($setup) && $setup['user_id'] === $user->id && $setup['time'] >= time() - self::SETUP_DURATION;
        $method = $valid ? TwoFactor::method($setup['method']) : null;

        if (!$method) {
            return $response->sendError('The setup expired. Start it again.', 400);
        }

        $data = $method->verify($setup['data'], $request->getString('code', 10000, ''));

        if ($data === null) {
            return $response->sendFieldErrors(['code' => 'Wrong code. Check the code and try again.']);
        }

        Session::remove('two_factor_setup');
        $codes = TwoFactor::enable($user->id, $setup['method'], $data);
        $this->logEvent('account.two_factor_enabled', $method->name());

        // turned on: the sessions and remember cookies without the second factor end (not this one)
        if ($codes !== null) {
            Auth::endOtherSessions($user);
        }

        $this->sendChangeEmail($user, $codes === null ? "a two-factor method was added ({$method->name()})" :
            "two-factor authentication was turned on ({$method->name()})");

        return $response->sendSuccess(['twoFactor' => self::state($user), 'codes' => $codes]);
    }

    /**
     * Route that removes one of the logged-in user's methods, or with "key" one of its keys (the last one takes the
     * method with it); the last method takes the recovery codes with it, unless two-factor authentication is required.
     * Returns the two-factor state.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function disable(Request $request, Response $response): Response
    {
        if ($error = $this->checkPassword($request, $response)) {
            return $error;
        }

        $user = $this->currentUser();
        $id = $request->getString('method', 20, '');
        $key = $request->getString('key', 200, '');
        $userMethods = TwoFactor::userMethods($user->id);

        if ($id === TwoFactor::RECOVERY || !isset($userMethods[$id])) {
            return $response->sendError('This method isn\'t set up.', 400);
        }

        $method = TwoFactor::method($id);
        $keys = $method->keys($userMethods[$id]);

        if ($key !== '' && !isset($keys[$key])) {
            return $response->sendError('This key isn\'t set up.', 400);
        }

        // the method goes (with its last key)
        if (($key === '' || count($keys) === 1) && TwoFactor::required() &&
            array_diff(array_keys($userMethods), [$id, TwoFactor::RECOVERY]) === []) {
            return $response->sendError('Two-factor authentication is required: set up another method first.', 400);
        }

        $name = $method->name();

        if ($key === '') {
            TwoFactor::disable($user->id, $id);
            $this->logEvent('account.two_factor_disabled', $name);
            $this->sendChangeEmail($user, "a two-factor method was removed ($name)");
        } else {
            TwoFactor::removeKey($user->id, $id, $key);
            $this->logEvent('account.two_factor_key_removed', "$name: $keys[$key]");
            $this->sendChangeEmail($user, "a two-factor key was removed ($name: $keys[$key])");
        }

        return $response->sendSuccess(['twoFactor' => self::state($user)]);
    }

    /**
     * Route that turns the logged-in user's two-factor authentication off (all the methods and the recovery codes),
     * unless it's required, and returns the two-factor state.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function turnOff(Request $request, Response $response): Response
    {
        if ($error = $this->checkPassword($request, $response)) {
            return $error;
        }

        if (TwoFactor::required()) {
            return $response->sendError('Two-factor authentication is required.', 400);
        }

        $user = $this->currentUser();
        TwoFactor::reset($user->id);
        $this->logEvent('account.two_factor_turned_off');
        $this->sendChangeEmail($user, 'two-factor authentication was turned off');

        return $response->sendSuccess(['twoFactor' => self::state($user)]);
    }

    /**
     * Route that returns the logged-in user's recovery codes, new ones with "renew" (the old ones stop working), and
     * the two-factor state.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function recoveryCodes(Request $request, Response $response): Response
    {
        if ($error = $this->checkPassword($request, $response)) {
            return $error;
        }

        $user = $this->currentUser();
        $userMethods = TwoFactor::userMethods($user->id);

        if (!$userMethods) {
            return $response->sendError('Two-factor authentication is off.', 400);
        }

        if ($request->getBool('renew', false)) {
            $codes = TwoFactor::renewRecoveryCodes($user->id);
            $this->logEvent('account.two_factor_recovery_renewed');
            $this->sendChangeEmail($user, 'new recovery codes were made (the old ones no longer work)');
        } else {
            $codes = $userMethods[TwoFactor::RECOVERY]['codes'];
        }

        return $response->sendSuccess(['codes' => $codes, 'twoFactor' => self::state($user)]);
    }

    /**
     * Admin route that turns another user's two-factor authentication off (e.g. a lost phone), with the admin's
     * password, and ends the user's sessions (someone else's could have the device); returns the user's two-factor
     * state.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function reset(Request $request, Response $response): Response
    {
        if ($error = $this->checkPassword($request, $response)) {
            return $error;
        }

        if (!$user = User::findByPublicId($request->getString('id', default: ''))) {
            return $response->sendError('The user doesn\'t exist.', 404);
        }

        // e.g. another admin's reset first: nothing to end, log or email
        if (!TwoFactor::enabled($user->id)) {
            return $response->sendError('Two-factor authentication is already off.', 400);
        }

        TwoFactor::reset($user->id);
        Auth::endOtherSessions($user);
        $this->logEvent('user.two_factor_reset', $user->email);
        $this->sendChangeEmail($user, 'an administrator turned two-factor authentication off');

        return $response->sendSuccess(['twoFactor' => self::state($user)]);
    }

    /**
     * Returns the field error if the posted password isn't the logged-in user's (throttled like a login), or null.
     *
     * @param Request $request
     * @param Response $response
     * @return Response|null
     */
    private function checkPassword(Request $request, Response $response): ?Response
    {
        try {
            Auth::attempt($this->currentUser()->email, $request->getString('password', default: ''));
        } catch (InputException|LoginException $e) {
            return $response->sendFieldErrors(['password' => $e->getCode() === 429 ? $e->getMessage() :
                'Wrong password.']);
        }

        return null;
    }

    /**
     * Emails the user the change of the user's two-factor authentication, with two_factor_email_notifications; a
     * failure is only logged (the change is done).
     *
     * @param User $user
     * @param string $change
     * @return void
     */
    private function sendChangeEmail(User $user, string $change): void
    {
        if (!Config::get('two_factor_email_notifications')) {
            return;
        }

        try {
            Mailer::send($user->email, 'Two-factor authentication changed', "Hello,\n\nOn your account at " .
                Config::get('site_name') . " ($user->email), $change.\n\nIf you didn't do this, change your password " .
                "and check your two-factor authentication on your account page, or tell the site's administrator.");
        } catch (MailException $e) {
            error_log("Two-factor authentication email: {$e->getMessage()}");
        }
    }
}

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
use Enicore\Rivena\Classes\Web;
use Enicore\Rivena\Exceptions\InputException;
use Enicore\Rivena\Exceptions\LoginException;
use Enicore\Rivena\Models\Event;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Illuminate\Database\Capsule\Manager as DB;
use PHPMailer\PHPMailer\Exception as MailException;
use Random\RandomException;

/**
 * The login (with the second factor for the users who have it), the logout and the password resets (Auth). Auth
 * throttles the failed logins; logins, logouts and password resets go to the event log.
 *
 * @package Enicore\Rivena
 */
class AuthController extends Controller
{
    /**
     * The layout of the login pages, apart from the site's public layout (and with noindex).
     */
    protected string $layout = 'auth-layout';

    /**
     * Route that sends the login page, or redirects to the admin area if the user is logged in.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function loginPage(Request $request, Response $response): Response
    {
        if (Auth::check()) {
            return $response->sendRedirect(Config::get('user_path'));
        }

        // a new session if check() ended the user's, for the form's CSRF token
        Session::start();

        return $this->sendPage($request, $response, 'login', ['action' => $request->urlFor('login'),
            'passwordResets' => $this->passwordResets()], 'Login');
    }

    /**
     * Route that logs the user in and returns the redirect URL as JSON, or the error; the event log gets an entry.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function login(Request $request, Response $response): Response
    {
        $email = '';

        try {
            $email = $request->getString('email', 249);
            $remember = $request->getBool('remember', false);
            $user = Auth::attempt($email, $request->getString('password'));
        } catch (InputException) {
            $this->logEvent('auth.login_failed', '', User::where('email', $email)->value('id'), $email);
            return $response->sendError('Invalid email or password.', 401);
        } catch (LoginException $e) {
            $this->logEvent($e->getCode() === 429 ? 'auth.login_throttled' : 'auth.login_failed', '',
                User::where('email', $email)->value('id'), $email);
            return $response->sendError($e->getMessage(), $e->getCode());
        }

        // the wrong password's answer, so it doesn't tell that the password was right
        if ($this->refuseLogin($user)) {
            return $response->sendError('Invalid email or password.', 401);
        }

        // the second factor's step of the login page: the user's methods, the first one selected
        if (TwoFactor::enabled($user->id)) {
            Auth::startTwoFactor($user, $remember);
            $methods = [];

            foreach (array_keys(TwoFactor::userMethods($user->id)) as $id) {
                $methods[] = ['id' => $id, 'name' => TwoFactor::method($id)->name()];
            }

            return $response->sendSuccess(['twoFactor' => $methods]);
        }

        return $this->logInUser($response, $user, $remember, $remember ? 'Remembered' : '');
    }

    /**
     * Route that checks the second factor of the user waiting after the password (Auth::startTwoFactor()), logs the
     * user in and returns the redirect URL as JSON (the account page after a recovery code, which shows the codes
     * left), or the error ("restart" true when the login has to start again).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function loginTwoFactor(Request $request, Response $response): Response
    {
        if (!$user = Auth::twoFactorUser()) {
            return $response->sendError('The login expired. Log in again.', 401, ['restart' => true]);
        }

        try {
            $method = $request->getString('method', 20);
            [$user, $remember] = Auth::attemptTwoFactor($method, $request->getString('code', 10000));
        } catch (InputException) {
            return $response->sendError('Invalid code.', 400);
        } catch (LoginException $e) {
            $this->logEvent($e->getCode() === 429 ? 'auth.login_throttled' : 'auth.two_factor_failed', '', $user->id,
                $user->email);
            return $response->sendError($e->getMessage(), $e->getCode());
        }

        if ($this->refuseLogin($user)) {
            return $response->sendError('Invalid email or password.', 401, ['restart' => true]);
        }

        $recovery = $method === TwoFactor::RECOVERY;
        $message = implode(', ', array_filter([$recovery ? 'Recovery code' : '', $remember ? 'Remembered' : '']));

        return $this->logInUser($response, $user, $remember, $message, $recovery ?
            $request->urlFor('account', [], ['tab' => 'two-factor']) : null);
    }

    /**
     * Route that returns what the browser needs to log in with the method of the user waiting after the password
     * (WebAuthn's options), or the error ("restart" true when the login has to start again).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function loginTwoFactorChallenge(Request $request, Response $response): Response
    {
        if (!$user = Auth::twoFactorUser()) {
            return $response->sendError('The login expired. Log in again.', 401, ['restart' => true]);
        }

        $method = $request->getString('method', 20, '');

        if (!$challenge = TwoFactor::challenge($user->id, $method)) {
            return $response->sendError('This method can\'t be used.', 400);
        }

        Auth::setTwoFactorChallenge($method, $challenge['data']);

        return $response->sendSuccess($challenge['page']);
    }

    /**
     * Returns true for a disabled user or a user without the "login" permission, and logs the reason (the user only
     * gets the wrong password's answer).
     *
     * @param User $user
     * @return bool
     */
    private function refuseLogin(User $user): bool
    {
        if ($user->disabled) {
            $this->logEvent('auth.login_disabled', '', $user->id, $user->email);
            return true;
        }

        if (!User::can('login', $user->id)) {
            $this->logEvent('auth.login_not_allowed', '', $user->id, $user->email);
            return true;
        }

        return false;
    }

    /**
     * Logs the user in (the event log gets the message) and returns the redirect URL as JSON: the user area's by
     * default.
     *
     * @param Response $response
     * @param User $user
     * @param bool $remember
     * @param string $message
     * @param string|null $redirect
     * @return Response
     * @throws RandomException
     */
    private function logInUser(Response $response, User $user, bool $remember, string $message,
                               ?string $redirect = null): Response
    {
        Auth::logIn($user, $remember);
        $this->logEvent('auth.login', $message);
        Event::where('created_at', '<', strtotime('-' . Config::get('event_log_retention_months') . ' months'))
            ->delete();

        return $response->sendSuccess(['redirect' => $redirect ?? Config::get('user_path')]);
    }

    /**
     * Route that sends the page that asks for the email to send a password reset link to, or the 404 page if no role
     * has the password_reset permission.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function forgotPasswordPage(Request $request, Response $response): Response
    {
        if (!$this->passwordResets()) {
            return $this->notFoundRoute($request, $response, []);
        }

        return $this->sendPage($request, $response, 'forgot-password',
            ['action' => $request->urlFor('forgot-password.post')], 'Forgot password');
    }

    /**
     * Route that emails a password reset link to the user with the email if it has the login and password_reset
     * permissions, unless it's disabled or an admin. The answer is the same for every email, so it doesn't tell which
     * ones have an account; 404 if no role has the password_reset permission.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function sendPasswordReset(Request $request, Response $response): Response
    {
        if (!$this->passwordResets()) {
            return $response->sendError('Page not found.', 404);
        }

        try {
            $email = $request->getString('email', 249);
        } catch (InputException) {
            $email = null;
        }

        // all after the answer (see its Content-Length), so the answer's time doesn't tell that the email has an
        // account; a failure is only logged, for the same reason
        register_shutdown_function(function () use ($email, $request) {
            // the session's lock would make the next request wait for the email
            session_write_close();
            ignore_user_abort(true);
            while (ob_get_level() && ob_end_flush());
            flush();

            $user = $email === null ? null : User::where('email', $email)->first();

            if (!$user || !$token = Auth::createPasswordReset($user)) {
                return;
            }

            $url = Web::getUrl($request->urlFor('reset-password', ['token' => $token]));

            try {
                Mailer::send($user->email, 'Reset your password', "Hello,\n\nSomeone asked to reset the password " .
                    "of your account at " . Config::get('site_name') . " ($user->email). To choose a new password, " .
                    "open this link within an hour:\n\n$url\n\nIf it wasn't you, ignore this email; your password " .
                    "stays the same.");
                $this->logEvent('auth.password_reset_sent', '', $user->id, $user->email);
            } catch (MailException $e) {
                error_log("Password reset email: {$e->getMessage()}");
            }
        });

        // with its length, so the browser doesn't wait for the email; closed, so a proxy (e.g. Caddy) doesn't send the
        // next request, maybe another client's, through this connection, which is busy until the email is sent
        $response = $response->sendSuccess(['message' => 'If the email has an account, a link to reset its password ' .
            'is on its way.']);
        return $response->withHeader('Content-Length', (string)$response->getBody()->getSize())
            ->withHeader('Connection', 'close');
    }

    /**
     * Route that sends the page that sets a new password, for the token of the link.
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function resetPasswordPage(Request $request, Response $response, array $args): Response
    {
        return $this->sendPage($request, $response, 'reset-password', ['token' => $args['token'],
            'action' => $request->urlFor('reset-password.post'),
            'email' => Auth::passwordResetUser($args['token'])?->email], 'Reset password');
    }

    /**
     * Route that sets the new password of the token's user (which ends the user's sessions and the token).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $user = Auth::passwordResetUser($request->getString('token'));
            $password = $request->getString('password');
        } catch (InputException) {
            return $response->sendError('Invalid request.', 400);
        }

        if (!$user) {
            return $response->sendError('This link is invalid or expired.', 400);
        }

        if ($error = Auth::checkPassword($password, $user->email, $user->password)[1]) {
            return $response->sendError($error, 400);
        }

        Auth::setPassword($user, $password);
        $this->logEvent('auth.password_reset', '', $user->id, $user->email);

        return $response->sendSuccess(['message' => 'Your password is changed.']);
    }

    /**
     * Route that rates a new password as it's typed (a password input's strength bar), with the form's email: returns
     * its score and the reason it can't be used, or null (Auth::checkPassword()).
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function passwordStrength(Request $request, Response $response): Response
    {
        try {
            [$score, $error] = Auth::checkPassword($request->getString('password'), $request->getString('email'));
        } catch (InputException) {
            return $response->sendError('Invalid request.', 400);
        }

        return $response->sendSuccess(['score' => $score, 'error' => $error]);
    }

    /**
     * Route that logs the user out and redirects to the login page.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RandomException
     */
    public function logout(Request $request, Response $response): Response
    {
        if (Auth::check()) {
            $this->logEvent('auth.logout');
        }

        Auth::logOut();
        return $response->sendRedirect($request->urlFor('login'));
    }

    /**
     * Returns true if a role has the password_reset permission (the admins can't reset their passwords).
     *
     * @return bool
     */
    private function passwordResets(): bool
    {
        return DB::table('role_permissions')->where('permission', 'password_reset')->exists();
    }
}

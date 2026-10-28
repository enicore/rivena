<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Controllers;

use Enicore\Rivena\Classes\GeoIp;
use Enicore\Rivena\Classes\Http;
use Enicore\Rivena\Classes\Request;
use Enicore\Rivena\Classes\Response;
use Enicore\Rivena\Classes\View;
use Enicore\Rivena\Classes\Web;
use Enicore\Rivena\Models\Event;
use function donatj\UserAgent\parse_user_agent;

/**
 * Base controller: renders pages and sends responses.
 *
 * A page is one GET route: sendPage() returns the whole page, or JSON when RivenaSpa loads it. Actions (save,
 * delete...) are POST routes that return JSON.
 *
 * @package Enicore\Rivena
 */
class Controller
{
    /**
     * The controller's permissions, name => description, e.g. ['posts.edit' => 'Edit posts']: the Permissions page
     * lists them (for the controllers with routes), User::can() and PermissionMiddleware check them.
     */
    public const array PERMISSIONS = [];

    /**
     * The layout view: the public pages' (no noindex); AdminController uses "admin/layout", AuthController
     * "auth-layout".
     */
    protected string $layout = 'layout';

    /**
     * Creates the controller with the view renderer.
     *
     * @param View $view
     */
    public function __construct(protected View $view)
    {
    }

    /**
     * Route that sends the 404 page (the error view, so RivenaSpa shows it as a page).
     *
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function notFoundRoute(Request $request, Response $response, array $args): Response
    {
        return $this->sendPage($request, $response, 'error', ['code' => 404, 'message' => 'Page not found'],
            'Page not found', 404);
    }

    /**
     * Returns the response with the whole page (the layout with the view), or with RivenaSpa's JSON when it asks for
     * it. The data goes to the view and to window.spa_page_data; the code is for the whole page (the JSON is always
     * 200).
     *
     * @param Request $request
     * @param Response $response
     * @param string $view
     * @param array $data
     * @param string $title
     * @param int $code
     * @return Response
     */
    protected function sendPage(Request $request, Response $response, string $view, array $data = [],
                                string $title = '', int $code = 200): Response
    {
        $page = ['title' => $title, 'data' => $data, 'version' => Web::assetsVersion()];

        // the same URL gives HTML or JSON, so the browser caches them apart
        if (!Http::wantsJson($request)) {
            $html = $this->view->getLayoutHtml($view, $data + ['title' => $title, 'spaPage' => $page], $this->layout);
            return $response->sendHtml($html, $code)->withHeader('Vary', 'Accept');
        }

        $page['html'] = $this->view->partial($view, $data + ['title' => $title]);
        return $response->sendSuccess($page)->withHeader('Vary', 'Accept');
    }

    /**
     * Returns the response with the view, without the layout.
     *
     * @param Response $response
     * @param string $view
     * @param array $data
     * @param int $code
     * @return Response
     */
    protected function sendView(Response $response, string $view, array $data = [], int $code = 200): Response
    {
        return $response->sendHtml($this->view->partial($view, $data), $code);
    }

    /**
     * Returns the response with the error view in the layout.
     *
     * @param Response $response
     * @param int $code
     * @param string $message
     * @return Response
     */
    protected function sendErrorPage(Response $response, int $code, string $message): Response
    {
        $html = $this->view->getLayoutHtml(
            'error',
            ['code' => $code, 'message' => $message, 'title' => $message],
            $this->layout
        );

        return $response->sendHtml($html, $code);
    }

    /**
     * Adds an event log entry with the time, the user (the logged-in one, or the email's: then the ID is null for an
     * unknown email), the IP address, its country and the browser.
     *
     * @param string $action
     * @param string $message
     * @param int|null $userId
     * @param string|null $userEmail
     * @return void
     */
    protected function logEvent(string $action, string $message = '', ?int $userId = null,
                                ?string $userEmail = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        ['browser' => $browser, 'version' => $version, 'platform' => $platform] = parse_user_agent($userAgent);

        if ($browser) {
            $version = explode('.', (string)$version)[0];
            $userAgent = implode(' / ', array_filter([trim("$browser $version"), $platform]));
        }

        Event::insert([
            'created_at' => time(),
            'user_id' => $userEmail === null ? $_SESSION['auth_user_id'] ?? null : $userId,
            'user_email' => $userEmail ?? $_SESSION['auth_email'] ?? null,
            'ip' => $ip,
            'country' => GeoIp::country($ip) ?? '',
            'user_agent' => mb_substr($userAgent, 0, 500),
            'action' => mb_substr($action, 0, 100),
            'message' => mb_substr($message, 0, 1000),
        ]);
    }
}

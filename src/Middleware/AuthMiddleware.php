<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Enicore\Rivena\Classes\Auth;
use Enicore\Rivena\Classes\Http;
use Enicore\Rivena\Classes\Response as RivenaResponse;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Random\RandomException;
use Slim\Routing\RouteContext;

/**
 * Lets only logged-in users through; the others are redirected to the login page (JSON requests get a 401 with the
 * login URL). Disabled users, and the users without the "login" permission, are logged out. With two_factor_required,
 * the users without two-factor authentication only get the account page, to set it up.
 *
 * @package Enicore\Rivena
 */
final class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Lets logged-in users through; the others are redirected to the login page, or get a 401 with its URL as JSON.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     * @throws RandomException
     */
    public function process(Request $request, Handler $handler): Response
    {
        $loggedIn = Auth::check();

        if ($loggedIn && !User::can('login')) {
            Auth::logOut();
            $loggedIn = false;
        }

        $routeContext = RouteContext::fromRequest($request);

        if ($loggedIn && TwoFactor::required() && !TwoFactor::enabled(Auth::id())) {
            // only the account page and its actions, until a method is set up
            $name = (string)$routeContext->getRoute()?->getName();

            if (!in_array($name, ['account', 'account.save'], true) && !str_starts_with($name, 'account.two-factor.')) {
                $url = $routeContext->getRouteParser()->urlFor('account', [], ['tab' => 'two-factor']);

                return Http::wantsJson($request) ?
                    (new RivenaResponse())->sendError('Set up two-factor authentication first.', 403,
                        ['redirect' => $url]) :
                    (new RivenaResponse())->sendRedirect($url);
            }
        }

        if ($loggedIn) {
            return $handler->handle($request);
        }

        $url = $routeContext->getRouteParser()->urlFor('login');

        if (Http::wantsJson($request)) {
            return (new RivenaResponse())->sendError('Please log in.', 401, ['redirect' => $url]);
        }

        return (new RivenaResponse())->sendRedirect($url);
    }
}

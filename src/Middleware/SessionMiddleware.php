<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Enicore\Rivena\Classes\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Starts the session. Only the routes that need one get it (App::addRivenaRoutes() adds it), so other requests don't
 * create sessions.
 *
 * @package Enicore\Rivena
 */
final class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Starts the session before handling the request.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        Session::start();

        return $handler->handle($request);
    }
}

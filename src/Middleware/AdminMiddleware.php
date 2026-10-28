<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Enicore\Rivena\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Exception\HttpForbiddenException;

/**
 * Lets only admins (admins in .env) through; the others get a 403. Add it to a route or a group, inside
 * AuthMiddleware (see App::addRivenaRoutes()).
 *
 * @package Enicore\Rivena
 */
final class AdminMiddleware implements MiddlewareInterface
{
    /**
     * Lets the admins through; the others get a 403 error.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     * @throws HttpForbiddenException
     */
    public function process(Request $request, Handler $handler): Response
    {
        if (!User::isAdmin()) {
            throw new HttpForbiddenException($request, 'Access denied.');
        }

        return $handler->handle($request);
    }
}

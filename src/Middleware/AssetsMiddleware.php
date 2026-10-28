<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Enicore\Rivena\Classes\Assets;
use Enicore\Rivena\Classes\ErrorLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use RuntimeException;

/**
 * Dev only: rebuilds the changed CSS and JS before each request (see Assets). new App() adds it with app_env=dev.
 *
 * @package Enicore\Rivena
 */
final class AssetsMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with the asset builder.
     *
     * @param Assets $assets
     */
    public function __construct(private readonly Assets $assets)
    {
    }

    /**
     * Builds the changed styles and scripts before handling the request; errors go to PHP's log and the Error Log (the
     * page keeps the last built files, so they'd go unnoticed).
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        try {
            $this->assets->build();
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            ErrorLog::add('warning', $e->getMessage());
        }

        return $handler->handle($request);
    }
}

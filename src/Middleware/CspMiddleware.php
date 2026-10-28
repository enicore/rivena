<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Adds the Content-Security-Policy header: only the site's own files, no inline scripts or styles ('unsafe-eval' is
 * for Alpine). new App()'s csp option replaces it.
 *
 * @package Enicore\Rivena
 */
final class CspMiddleware implements MiddlewareInterface
{
    /**
     * Creates the middleware with the policy to use instead of Rivena's.
     *
     * @param string|null $policy
     */
    public function __construct(private readonly ?string $policy = null)
    {
    }

    /**
     * Adds the Content-Security-Policy header, unless the response has one.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);

        return $response->hasHeader('Content-Security-Policy')
            ? $response
            : $response->withHeader(
                'Content-Security-Policy',
                $this->policy ?? "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self'; ".
                    "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; ".
                    "form-action 'self'; frame-ancestors 'none';"
            );
    }
}

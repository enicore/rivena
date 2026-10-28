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
 * Adds the standard security headers (no MIME sniffing, no framing, referrer policy...).
 *
 * @package Enicore\Rivena
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Adds the security headers the response doesn't have.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);
        $headers = [
            // don't guess file types (an uploaded "image" isn't run as a script)
            'X-Content-Type-Options' => 'nosniff',
            // no framing by other sites (clickjacking), for browsers that don't support CSP's frame-ancestors
            'X-Frame-Options' => 'DENY',
            // send only the origin to other sites, not the full URL
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // a popup opened by another site can't access this site's window
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        foreach ($headers as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}

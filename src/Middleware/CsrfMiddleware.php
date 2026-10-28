<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use Enicore\Rivena\Classes\Http;
use Enicore\Rivena\Classes\Session;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Rejects requests that can change data (all but GET, HEAD, OPTIONS) without the session's CSRF token, sent in the
 * X-CSRF-Token header or a "csrf" form field.
 *
 * @package Enicore\Rivena
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Checks the CSRF token of all requests but GET, HEAD and OPTIONS; a wrong one gets a 403 error.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $sent = $request->getHeaderLine('X-CSRF-Token') ?: ($request->getParsedBody()['csrf'] ?? '');
        $stored = (string)Session::get('csrf');

        if ($stored === '' || !is_string($sent) || !hash_equals($stored, $sent)) {
            $response = new \Enicore\Rivena\Classes\Response();

            // with the session's token: after a remember-me login into a new session, postJson() retries with it
            if (Http::wantsJson($request)) {
                return $response->sendError('Invalid CSRF token. Please reload the page.', 403,
                    ['csrf' => Session::getCsrfToken()]);
            }

            return $response->sendText('Invalid CSRF token. Please reload the page.', 403);
        }

        return $handler->handle($request);
    }
}

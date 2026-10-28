<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Request helpers for the controllers and middleware.
 *
 * @package Enicore\Rivena
 */
class Http
{
    /**
     * Returns true if the request asks for JSON ("Accept: application/json"), like RivenaSpa's page loads.
     *
     * @param Request $request
     * @return bool
     */
    public static function wantsJson(Request $request): bool
    {
        return stripos($request->getHeaderLine('Accept'), 'application/json') !== false;
    }
}

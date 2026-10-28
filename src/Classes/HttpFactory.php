<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\ServerRequestCreatorInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Makes Slim give the routes Rivena's Request and Response (with getString(), sendSuccess()...) instead of its own.
 * new App() sets it up.
 *
 * @package Enicore\Rivena
 */
final class HttpFactory implements ResponseFactoryInterface, ServerRequestCreatorInterface
{
    /**
     * Returns Rivena's response instead of Slim's.
     *
     * @param int $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response()->withStatus($code, $reasonPhrase);
    }

    /**
     * Returns Rivena's request, made from the globals, instead of Slim's.
     *
     * @return ServerRequestInterface
     */
    public function createServerRequestFromGlobals(): ServerRequestInterface
    {
        return Request::from(ServerRequestFactory::createFromGlobals());
    }
}

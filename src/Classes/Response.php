<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Slim\Psr7\Response as SlimResponse;

/**
 * Slim's response with send methods: sendSuccess(), sendError(), sendHtml()... The routes get it instead of Slim's.
 *
 * The JSON responses are {"success": true, "data": {...}} or {"success": false, "error": "message"}.
 *
 * @package Enicore\Rivena
 */
class Response extends SlimResponse
{
    /**
     * Returns the response with the payload as JSON.
     *
     * @param array $payload
     * @param int $code
     * @return Response
     */
    public function sendJson(array $payload, int $code = 200): Response
    {
        $this->getBody()->write(json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));

        return $this
            ->withStatus($code)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Returns the response with {"success": true, "data": {...}} as JSON.
     *
     * @param array $data
     * @param int $code
     * @return Response
     */
    public function sendSuccess(array $data = [], int $code = 200): Response
    {
        return $this->sendJson(['success' => true, 'data' => $data], $code);
    }

    /**
     * Returns the response with {"success": false, "error": "message"} and the extra values as JSON. The status is 200
     * by default (the frontend checks success); pass a code for an HTTP error.
     *
     * @param string $message
     * @param int $code
     * @param array $extra
     * @return Response
     */
    public function sendError(string $message, int $code = 200, array $extra = []): Response
    {
        return $this->sendJson(['success' => false, 'error' => $message] + $extra, $code);
    }

    /**
     * Returns the response with the form fields' errors as JSON ("errors": {"email": "message"}, "error" is the first
     * one), which Controller.submit() shows at the fields.
     *
     * @param array $errors
     * @param int $code
     * @return Response
     */
    public function sendFieldErrors(array $errors, int $code = 400): Response
    {
        return $this->sendError(reset($errors), $code, ['errors' => $errors]);
    }

    /**
     * Returns the response with the HTML.
     *
     * @param string $html
     * @param int $code
     * @return Response
     */
    public function sendHtml(string $html, int $code = 200): Response
    {
        $this->getBody()->write($html);

        return $this
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Returns the response with the plain text.
     *
     * @param string $text
     * @param int $code
     * @return Response
     */
    public function sendText(string $text, int $code = 200): Response
    {
        $this->getBody()->write($text);

        return $this
            ->withStatus($code)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Returns the response as a redirect to the URL.
     *
     * @param string $url
     * @param int $code
     * @return Response
     */
    public function sendRedirect(string $url, int $code = 302): Response
    {
        return $this->withHeader('Location', $url)->withStatus($code);
    }
}

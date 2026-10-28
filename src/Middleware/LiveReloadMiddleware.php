<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Middleware;

use FilesystemIterator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Dev only: reloads the browser when files in src/ or public/ change (a CSS change only reloads the styles).
 * new App() adds it with app_env=dev.
 *
 * @package Enicore\Rivena
 */
final class LiveReloadMiddleware implements MiddlewareInterface
{
    public const PATH = '/_rivena/live-reload';

    private const SCRIPT = <<<'JS'
        (() => {
            const url = document.currentScript.src.replace(/\.js$/, '');
            let last = null;
            const check = () => fetch(url, {cache: 'no-store'})
                .then(response => response.json())
                .then(times => {
                    if (last && times.other !== last.other) {
                        return location.reload();
                    }
                    if (last && times.css !== last.css) {
                        document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                            const href = new URL(link.href);
                            href.searchParams.set('reload', times.css);
                            link.href = href;
                        });
                    }
                    last = times;
                })
                .catch(() => {})
                .finally(() => setTimeout(check, 1000));
            check();
        })();
        JS;

    /**
     * Creates the middleware with the directories to watch.
     *
     * @param array $dirs
     */
    public function __construct(private readonly array $dirs)
    {
    }

    /**
     * Serves the live reload script and the files' times, and adds the script to the HTML pages.
     *
     * @param Request $request
     * @param Handler $handler
     * @return Response
     */
    public function process(Request $request, Handler $handler): Response
    {
        $path = $request->getUri()->getPath();

        if ($path === self::PATH . '.js') {
            return $this->send(self::SCRIPT, 'text/javascript');
        }

        if ($path === self::PATH) {
            return $this->send(json_encode($this->getTimes()), 'application/json');
        }

        $response = $handler->handle($request);

        if (!str_starts_with($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $html = (string)$response->getBody();
        $script = '<script src="' . self::PATH . '.js"></script>';
        $position = strripos($html, '</body>');
        $html = $position === false ? $html . $script : substr_replace($html, $script, $position, 0);

        return $response->withBody((new StreamFactory())->createStream($html));
    }

    /**
     * Returns the latest modification time of the CSS files and of the other files.
     *
     * @return array
     */
    private function getTimes(): array
    {
        $times = ['css' => 0, 'other' => 0];

        foreach ($this->dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                $extension = $file->getExtension();

                if (!in_array($extension, ['scss', 'map'], true)) {
                    $type = $extension === 'css' ? 'css' : 'other';
                    $times[$type] = max($times[$type], $file->getMTime());
                }
            }
        }

        return $times;
    }

    /**
     * Returns a response with the body and content type, not cached.
     *
     * @param string $body
     * @param string $type
     * @return Response
     */
    private function send(string $body, string $type): Response
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write($body);

        return $response
            ->withHeader('Content-Type', "$type; charset=utf-8")
            ->withHeader('Cache-Control', 'no-store');
    }
}

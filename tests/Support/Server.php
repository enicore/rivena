<?php
namespace Tests\Support;

use RuntimeException;

/**
 * Runs a script in PHP's built-in server, for code that sends headers or exits.
 */
class Server
{
    private $process;
    private int $port;

    public function __construct(string $script)
    {
        // a free port: bind to port 0 and let the system choose
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$this->port", $script],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes
        );

        for ($i = 0; $i < 50; $i++) {
            if ($connection = @fsockopen('127.0.0.1', $this->port)) {
                fclose($connection);
                return;
            }
            usleep(100000);
        }

        throw new RuntimeException('The PHP server did not start.');
    }

    public function __destruct()
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    /**
     * Returns the status code, the headers (lowercase names) and the body of the response.
     */
    public function get(string $query, ?string $post = null, string $contentType = 'application/json'): array
    {
        $options = ['ignore_errors' => true];
        if ($post !== null) {
            $options += ['method' => 'POST', 'content' => $post, 'header' => "Content-Type: $contentType"];
        }

        $context = stream_context_create(['http' => $options]);
        $stream = fopen("http://127.0.0.1:$this->port/?$query", 'r', false, $context);
        $body = stream_get_contents($stream);
        $lines = stream_get_meta_data($stream)['wrapper_data'];
        fclose($stream);
        $headers = [];

        foreach (array_slice($lines, 1) as $line) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)] = trim($value);
        }

        return [(int)explode(' ', $lines[0])[1], $headers, $body];
    }
}

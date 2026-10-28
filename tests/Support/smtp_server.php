<?php
// a minimal SMTP server for the tests: accepts every email and adds its data to the file, each one ending with a NUL
[, $port, $file] = $argv;
$server = stream_socket_server("tcp://127.0.0.1:$port");

while ($client = stream_socket_accept($server, -1)) {
    fwrite($client, "220 test\r\n");

    while (($line = fgets($client)) !== false) {
        $command = strtoupper(substr($line, 0, 4));

        if ($command === 'QUIT') {
            fwrite($client, "221 bye\r\n");
            break;
        }

        if ($command === 'DATA') {
            fwrite($client, "354 go on\r\n");
            $data = '';

            while (($line = fgets($client)) !== false && $line !== ".\r\n") {
                $data .= $line;
            }

            file_put_contents($file, "$data\0", FILE_APPEND);
        }

        fwrite($client, "250 ok\r\n");
    }

    fclose($client);
}

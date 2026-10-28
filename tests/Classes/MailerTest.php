<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Mailer;
use PHPMailer\PHPMailer\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestConfig;

#[CoversClass(Mailer::class)]
class MailerTest extends TestCase
{
    /**
     * A server that takes the connection but never answers gives up after smtp_timeout, not PHPMailer's 300 s.
     */
    public function testTimeout(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        TestConfig::load(['smtp_host' => '127.0.0.1', 'smtp_port' => $port, 'mail_from' => 'site@example.com',
            'smtp_timeout' => 1]);
        $start = microtime(true);

        try {
            Mailer::send('user@example.com', 'Subject', 'Text');
            $this->fail('No exception');
        } catch (Exception) {
            $this->assertLessThan(5, microtime(true) - $start);
        } finally {
            fclose($server);
        }
    }
}

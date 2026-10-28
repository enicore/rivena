<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends plain text emails with PHPMailer, through the SMTP server of the config (smtp_host, smtp_port, smtp_username,
 * smtp_password), from mail_from with the site's name:
 *
 * Mailer::send('user@example.com', 'Welcome', "Hello,\n...");
 *
 * @package Enicore\Rivena
 */
final class Mailer
{
    /**
     * Sends the email.
     *
     * @param string $to
     * @param string $subject
     * @param string $text
     * @return void
     * @throws Exception
     */
    public static function send(string $to, string $subject, string $text): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)Config::get('smtp_host');
        $mail->Port = (int)Config::get('smtp_port', 587);
        // PHPMailer's 300 s would keep error pages from answering while the server is unreachable or silent (Timeout is
        // for connecting, Timelimit for each answer)
        $mail->Timeout = $mail->getSMTPInstance()->Timelimit = (int)Config::get('smtp_timeout');
        // port 465 is TLS from the start, 587 needs STARTTLS (someone on the network could remove the server's offer);
        // on the others, PHPMailer uses STARTTLS if the server offers it
        $mail->SMTPSecure = match ($mail->Port) {
            465 => PHPMailer::ENCRYPTION_SMTPS,
            587 => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mail->SMTPAuth = (string)Config::get('smtp_username') !== '';
        $mail->Username = (string)Config::get('smtp_username');
        $mail->Password = (string)Config::get('smtp_password');
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom((string)Config::get('mail_from'), (string)Config::get('site_name'));
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $text;
        $mail->send();
    }
}

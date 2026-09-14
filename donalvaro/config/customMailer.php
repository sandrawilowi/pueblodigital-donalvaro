<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * customMailer
 *
 * @author Sandra Campos
 * @copyright Wilowi
 * @version 1.1.0
 * @since 17 jun 2026
 */
class customMailer
{
    private PHPMailer $mail;

    private string $from = _PHPMAILER_FROM;
    private string $host = _PHPMAILER_HOST;
    private string $username = _PHPMAILER_USERNAME;
    private string $password = _PHPMAILER_PASSWORD;
    private int $port = _PHPMAILER_PORT;
    private string $encryption = _PHPMAILER_ENCRYPTION;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        $this->configure();
    }

    private function configure(): void
    {
        $this->mail->isSMTP();

        $this->mail->Host = $this->host;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->username;
        $this->mail->Password = $this->password;
        $this->mail->Port = $this->port;
        $this->mail->SMTPSecure = $this->encryption;
        $this->mail->Timeout = 30;
        $this->mail->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mail->Encoding = 'base64';

        $this->mail->setFrom(
            $this->from,
            'Don Álvaro - Pueblo Digital'
        );

        $this->mail->isHTML(true);

        $this->mail->AltBody =
            'Para ver el mensaje utilice un visor HTML compatible.';

        /*
         * Solo para entornos locales o de desarrollo.
         * En producción debe verificarse el certificado.
         */
        if (_ENVIRONMENT !== 'production') {
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
    }

    public function setOpen(): void
    {
        $this->mail->SMTPKeepAlive = true;
    }

    public function setClose(): void
    {
        $this->mail->smtpClose();
    }

    public function setAddress(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (is_array($address)) {
                $email = $address['email'] ?? '';
                $name = $address['name'] ?? '';

                if ($email !== '') {
                    $this->mail->addAddress($email, $name);
                }

                continue;
            }

            $this->mail->addAddress((string) $address);
        }
    }

    public function setBCC(array $addresses): void
    {
        foreach ($addresses as $address) {
            if (is_array($address)) {
                $email = $address['email'] ?? '';
                $name = $address['name'] ?? '';

                if ($email !== '') {
                    $this->mail->addBCC($email, $name);
                }

                continue;
            }

            $this->mail->addBCC((string) $address);
        }
    }

    public function setAttachment(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (is_array($attachment)) {
                $path = $attachment['path'] ?? '';
                $name = $attachment['name'] ?? '';

                if ($path !== '') {
                    $this->mail->addAttachment($path, $name);
                }

                continue;
            }

            $this->mail->addAttachment((string) $attachment);
        }
    }

    public function sendStandardMail(
        string $subject,
        string $body,
        string $altBody = ''
    ): bool {
        try {
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            $this->mail->AltBody = $altBody !== ''
                ? $altBody
                : trim(strip_tags($body));

            $this->mail->send();

            return true;

        } catch (Exception $e) {
            $log = new logsModel('mail', 'customMail.log');

            $log->error(
                'Error al enviar email',
                [
                    'subject' => $subject,
                    'exception' => $e->getMessage(),
                    'info' => $this->mail->ErrorInfo
                ]
            );

            return false;

        } finally {
            $this->clearMessage();
        }
    }

    public function enableDebug(): void
    {
        if (defined('_URL_LOGS')) {
            $directory = rtrim(_URL_LOGS, '/\\') . '/phpmailer/';

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            ini_set(
                'error_log',
                $directory . 'phpmailer-' . date('Ymd') . '.log'
            );
        }

        $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $this->mail->Debugoutput = 'error_log';
    }

    private function clearMessage(): void
    {
        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
        $this->mail->clearReplyTos();
        $this->mail->clearAttachments();

        $this->mail->Subject = '';
        $this->mail->Body = '';
    }
}
<?php

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fraym\Helper;

use Fraym\Interface\Helper;
use PHPMailer\PHPMailer\PHPMailer;

/* require INNER_PATH.'vendor/phpmailer/src/Exception.php';
require INNER_PATH.'vendor/phpmailer/src/PHPMailer.php';
require INNER_PATH.'vendor/phpmailer/src/SMTP.php'; */

abstract class EmailHelper implements Helper
{
    /** Отправка email-сообщений на основе библиотеки PHPmailer */
    public static function sendMail(
        string $fromName,
        string $fromEmail,
        string $toEmail,
        string $subject,
        string $message,
        bool $html = false,
        string $toName = '',
    ): bool {
        $server_email = $_ENV['NOTIFY_EMAIL'];

        if ($fromEmail === '') {
            $fromEmail = $server_email;
        }

        //Create a new PHPMailer instance
        $mail = new PHPMailer(CURRENT_USER->isAdmin());
        $mail->CharSet = 'utf-8';
        $mail->Encoding = 'base64';

        //SMTP-settings
        $CFG = $_ENV['EMAIL_SERVER'];

        if ($CFG) {
            if ($CFG['smtp']) {
                $mail->IsSMTP();
                $mail->Host = $CFG['host'];
                $mail->Port = $CFG['port'];

                if ($CFG['smtp_auth']) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $CFG['username'];
                    $mail->Password = $CFG['pass'];
                }
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            //Set who the message is to be sent from
            $mail->setFrom($server_email, $fromName);

            //Set an alternative reply-to address
            $mail->addReplyTo($fromEmail);

            //Set who the message is to be sent to
            $mail->addAddress($toEmail, $toName);

            //Set the subject line
            $mail->Subject = preg_replace("/[\r\n]/", " ", $subject);

            //Add List-Unsubscribe header
            $mail->AddCustomHeader(
                "List-Unsubscribe",
                "<mailto:" . $server_email . "?subject=Unsubscribe>, <" . ABSOLUTE_PATH . "/profile/unsubscribe_id=" . rand(1, 2000) . ">",
            );

            if ($html) {
                //Read an HTML message body from an external file, convert referenced images to embedded,
                //convert HTML into a basic plain-text alternative body
                $mail->msgHTML($message);

                //Replace the plain text body with one created manually
                //$mail->AltBody = $mail->html2text($message);

                //Attach an image file
                //$mail->addAttachment('images/phpmailer_mini.png');
            } else {
                $mail->Body = strip_tags(preg_replace('#<br>#', "\r\n", $mail->normalizeBreaks($message)));
            }

            //send the message, check for errors
            return $mail->send();
        }

        return false;
    }

    /**
     * Email-рассылка
     *
     * @return bool[]
     */
    public static function sendMails(
        string $fromName,
        string $fromEmail,
        array $toUsers,
        string $subject,
        string $message,
        bool $html = false,
    ): array {
        $sent = [];

        foreach ($toUsers as $userData) {
            if (is_numeric($userData) && defined('DB')) {
                $userData = DB->select('user', ['id' => $userData], true);
                $userData = $userData['em'];
            }
            $sent[$userData] = self::sendMail(
                $fromName,
                $fromEmail,
                $userData,
                $subject,
                $message,
                $html,
            );
        }

        return $sent;
    }
}

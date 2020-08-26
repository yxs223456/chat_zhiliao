<?php
/**
 * Created by PhpStorm.
 * User: lichaoyang
 * Date: 2020/3/26
 * Time: 下午2:50
 */

namespace app\common\helper;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use think\facade\Log;

class SendMail
{
    public static function sendQQMail(array $to, $subject, $content)
    {
        $mail = new PHPMailer(true);
        $config = config("account.qq");

        try {
            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host = $config["host"];                    // Set the SMTP server to send through
            $mail->SMTPAuth = true;                                   // Enable SMTP authentication
            $mail->Username = $config["from"];                     // SMTP username
            $mail->Password = $config["password"];                               // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port = $config["port"];                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

            //Recipients
            $mail->setFrom($config["from"]);
            // Add a recipient
            foreach ($to as $value) {
                $mail->addAddress($value);
            }

            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = $content;
            $mail->AltBody = 'diamond store alert';

            $res = $mail->send();
            if (!$res) {
                Log::error("send message error ", [$subject, $to, $content]);
            }
        } catch (\Throwable $e) {
            Log::error("Message could not be sent. Mailer Error: ".$e->getMessage());
            throw $e;
        }
    }
}
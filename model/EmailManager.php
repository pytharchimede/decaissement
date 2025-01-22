<?php

// Inclure PHPMailer manuellement
require_once '../phpmailer/src/PHPMailer.php';
require_once '../phpmailer/src/Exception.php';
require_once '../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailManager
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer()
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = 'mail.fidest.ci';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = "support@fidest.ci";
        $this->mailer->Password = "@Succes2019";
        $this->mailer->SMTPSecure = "ssl";
        $this->mailer->Port = 465;
        $this->mailer->From = "support@fidest.ci";
        $this->mailer->FromName = "SUPPORT FIDEST";
    }

    public function sendEmail($subject, $body, $recipients = [], $cc = [], $bcc = [])
    {
        try {
            foreach ($recipients as $email => $name) {
                $this->mailer->addAddress($email, $name);
            }

            foreach ($cc as $email => $name) {
                $this->mailer->addCC($email, $name);
            }

            foreach ($bcc as $email => $name) {
                $this->mailer->addBCC($email, $name);
            }

            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
}

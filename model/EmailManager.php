<?php

// Inclure PHPMailer avec des chemins robustes
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

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
        $this->mailer->Username = 'support@fidest.ci';
        $this->mailer->Password = '@Succes2019';

        // D'abord tenter SMTPS (465)
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // ssl implicite
        $this->mailer->Port = 465;

        // Options SSL (utile si certificat intermédiaire manquant sur l’hébergement)
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ];

        $this->mailer->setFrom('support@fidest.ci', 'SUPPORT FIDEST');
        $this->mailer->addReplyTo('support@fidest.ci', 'SUPPORT FIDEST');
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
            try {
                $this->mailer->send();
            } catch (Exception $primary) {
                // Fallback STARTTLS 587 si 465 échoue
                error_log('Email primary send failed on 465: ' . $this->mailer->ErrorInfo);
                $this->mailer->smtpClose();
                $this->mailer->clearAllRecipients();
                // Reconfig destinataires
                foreach ($recipients as $email => $name) {
                    $this->mailer->addAddress($email, $name);
                }
                foreach ($cc as $email => $name) {
                    $this->mailer->addCC($email, $name);
                }
                foreach ($bcc as $email => $name) {
                    $this->mailer->addBCC($email, $name);
                }
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // tls explicite
                $this->mailer->Port = 587;
                $this->mailer->send();
            }
            return true;
        } catch (Exception $e) {
            error_log("Email error: " . $this->mailer->ErrorInfo);
            return false;
        } finally {
            // Nettoyage pour éviter l’accumulation de destinataires
            try {
                $this->mailer->clearAllRecipients();
                $this->mailer->clearAttachments();
            } catch (Exception $ignored) {
            }
        }
    }
}

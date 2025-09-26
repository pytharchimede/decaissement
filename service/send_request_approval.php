<?php
require_once __DIR__ . '/../model/WhatsAppSMS.php';

// Configuration Twilio
$sid = "ACded19f6cd55b2ba3d18c13f438f1e878"; // Votre SID Twilio
$token = "fea6684286c6b923e8ce0ce19bc48cb4"; // Remplacez par votre AuthToken
$from = "whatsapp:+2250711048002"; // Numéro WhatsApp Twilio

// Créer une instance de WhatsAppSMS
$whatsapp = new WhatsAppSMS($sid, $token, $from);

// Informations du destinataire et variables du template
$number = "0748367710";
$recipient_number = "+225" . $number; // Numéro formaté avec indicatif pays
$submitter_name = "CISSE OUSMANE";
$file_number = "08132";
$amount = "100 000 FCFA";
$purpose = "Achat de ciment";

// Envoi du message
try {
    $response = $whatsapp->sendApprovalAlert($recipient_number, $submitter_name, $file_number, $amount, $purpose);

    if ($response['status'] === 'success') {
        echo "Message envoyé avec succès à $submitter_name (Numéro : $recipient_number).";
    } else {
        echo "Erreur lors de l'envoi : " . $response['message'];
    }
} catch (Exception $e) {
    echo "Exception rencontrée : " . $e->getMessage();
}

<?php
require_once __DIR__ . '/../model/WhatsAppSMS.php';

// Configuration Twilio
$sid = "ACded19f6cd55b2ba3d18c13f438f1e878"; // Votre SID Twilio
$token = "fea6684286c6b923e8ce0ce19bc48cb4"; // Remplacez par votre AuthToken
$from = "whatsapp:+2250711048002"; // Numéro WhatsApp Twilio

// Créer une instance de WhatsAppSMS
$whatsapp = new WhatsAppSMS($sid, $token, $from);

// Paramètres du destinataire et de la question
$to = "+2250748367710"; // Le numéro du destinataire
$question = "Quel est le solde de mon compte ?"; // La question posée au chatbot

// Envoi du message via la méthode sendMessage
$sid = $whatsapp->sendMessage($to, $question);

// Vérification du succès de l'envoi du message
if ($sid) {
    echo "Message envoyé avec succès, SID : $sid";
} else {
    echo "Échec de l'envoi du message.";
}

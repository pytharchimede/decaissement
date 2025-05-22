
<?php
require_once 'model/WhatsAppSMS.php';

// Paramètres Twilio (à adapter si besoin)
$sid = "ACded19f6cd55b2ba3d18c13f438f1e878";
$token = "7f1136b112e6d8cb4a6af94223d0872e"; // Numéro WhatsApp Twilio
$from = "whatsapp:+2250711048002";

$whatsapp = new WhatsAppSMS($sid, $token, $from);

// Données de test
$recipientNumber = "+22505055262"; // Numéro du DG ou de test
$fileNumber = "001";
$amount = "100000";
$submitterName = "CISSE OUSMANE";
$purpose = "Demande de carburant pour la tractopelle";

// Envoi du message
$response = $whatsapp->sendApprovalAlert($recipientNumber, $fileNumber, $amount, $submitterName, $purpose);

// Affichage du résultat
header('Content-Type: application/json');
echo json_encode($response);

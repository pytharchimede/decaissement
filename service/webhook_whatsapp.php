<?php
require_once '../model/WhatsAppSMS.php'; // Classe WhatsAppSMS pour envoyer des messages WhatsApp


// Configuration Twilio
$sid = "ACded19f6cd55b2ba3d18c13f438f1e878"; // SID Twilio
$token = "fea6684286c6b923e8ce0ce19bc48cb4"; // AuthToken Twilio
$from = "whatsapp:+2250711048002"; // Numéro WhatsApp Twilio
$whatsapp = new WhatsAppSMS($sid, $token, $from); // Instance WhatsAppSMS

// Récupérer les paramètres envoyés par Twilio dans le webhook
$sender = isset($_POST['From']) ? $_POST['From'] : null; // Numéro de l'utilisateur (WhatsApp)
$body = isset($_POST['Body']) ? trim(strtolower($_POST['Body'])) : null; // Réponse de l'utilisateur ("approuver", "refuser", "solde")
$recipientNumber = isset($_POST['To']) ? $_POST['To'] : null; // Numéro du destinataire
$waId = isset($_POST['WaId']) ? $_POST['WaId'] : null; // ID WhatsApp (WaId)

// Vérifier que les paramètres requis sont présents
if (!$sender || !$body || !$recipientNumber) {
    http_response_code(400); // Mauvaise requête
    error_log("Requête invalide. Paramètres manquants : " . json_encode($_POST));
    echo "Requête invalide. Paramètres manquants.";
    exit;
}


// Extraire le numéro sans le préfixe "whatsapp:"
$senderNumber = str_replace("whatsapp:", "", $sender);  // Retirer le "whatsapp:" du début du numéro
$senderNumberWithoutPrefix = '07' . substr($senderNumber, 4); // Retirer le préfixe pays "+225" si nécessaire

// Vérifier que le numéro est correct
if (empty($senderNumberWithoutPrefix)) {
    http_response_code(400); // Mauvaise requête
    error_log("Numéro invalide : " . $sender);
    echo "Numéro invalide.";
    exit;
}

// Envoi de la réponse via WhatsApp
$num_recepteur_reponse = '+225' . $senderNumberWithoutPrefix;
try {
    $whatsapp->sendMessage($num_recepteur_reponse, $body); // Répondre à l'expéditeur
    echo "Message envoyé avec succès.";
} catch (Exception $e) {
    error_log("Erreur lors de l'envoi du message WhatsApp : " . $e->getMessage());
    echo "Erreur lors de l'envoi du message.";
}

http_response_code(200); // Réponse réussie

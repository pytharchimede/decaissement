<?php

require_once __DIR__ . '/../model/SmsSender.php';
require_once __DIR__ . '/../model/EmailManager.php';

// Récupérer les données POST envoyées par Twilio
$data = json_decode(file_get_contents('php://input'), true);

// Pour Twilio, parfois les données sont en POST classique :
if (empty($data) && !empty($_POST)) {
    $data = $_POST;
}

// Exemple de récupération des variables du template
$num_fiche = $data['contentVariables']['1'] ?? null; // numéro de fiche
$montant = $data['contentVariables']['2'] ?? null;
$nom_demandeur = $data['contentVariables']['3'] ?? null;
$precision = $data['contentVariables']['4'] ?? null;

// Vérifier l'action (bouton cliqué)
$button_id = $data['buttonPayload'] ?? null; // "1" pour Approuver, "2" pour Refuser

if ($button_id === "1" && $num_fiche) {
    // Générer un OTP
    $otp = rand(100000, 999999);

    // Récupérer le numéro de téléphone du bénéficiaire depuis la base (à partir du numéro de fiche)
    // $telephone = getTelephoneByNumFiche($num_fiche);
    $smsSender = new SmsSender();
    $stationManagerName = "Ulrich AMANI"; // À récupérer selon votre logique
    $telephone = "2250748367710"; // À récupérer selon la fiche

    $smsResponse = $smsSender->sendOtpToStationManager($telephone, $otp, $stationManagerName);

    // Préparer l'email
    $emailManager = new EmailManager();
    $recipients = [
        "braud@banamur.com" => "Alex BRAUD",
        "ulrich@banamur.com" => "Ulrich AMANI"
    ];

    // Analyse de la réponse SMS
    $success = false;
    $message = "";
    if ($smsResponse && (stripos($smsResponse, 'success') !== false || stripos($smsResponse, 'OK') !== false)) {
        $success = true;
        $message = "Le gérant de la station ($stationManagerName) a bien reçu le SMS OTP pour la fiche $num_fiche.<br><br>Réponse API SMS : <pre>$smsResponse</pre>";
    } else {
        $message = "Échec de l'envoi du SMS OTP au gérant de la station ($stationManagerName) pour la fiche $num_fiche.<br><br>Réponse API SMS : <pre>$smsResponse</pre>";
    }

    $subject = $success
        ? "Succès envoi OTP SMS fiche $num_fiche"
        : "Échec envoi OTP SMS fiche $num_fiche";

    $emailManager->sendEmail($subject, $message, $recipients);

    // Log ou réponse
    error_log("Fiche $num_fiche approuvée, montant de $montant  OTP $otp envoyé à $telephone. Réponse SMS : $smsResponse");
}

// Répondre à Twilio (important)
http_response_code(200);
echo json_encode(['status' => 'received']);

<?php

require_once __DIR__ . '/../model/SmsSender.php';

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
    // Ici, la fiche $num_fiche a été approuvée

    // Générer un OTP
    $otp = rand(100000, 999999);

    // Récupérer le numéro de téléphone du bénéficiaire depuis la base (à partir du numéro de fiche)
    // ... Connexion à la base et requête SELECT ...

    // Exemple fictif :
    // $telephone = getTelephoneByNumFiche($num_fiche);

    $smsSender = new SmsSender();
    $stationManagerName = "Ulrich AMANI"; // À récupérer selon votre logique
    $telephone = "2250748367710"; // À récupérer selon la fiche

    $smsSender->sendOtpToStationManager($telephone, $otp, $stationManagerName);

    // Log ou réponse
    error_log("Fiche $num_fiche approuvée, montant de $montant  OTP $otp envoyé à $telephone");
}

// Répondre à Twilio (important)
http_response_code(200);
echo json_encode(['status' => 'received']);

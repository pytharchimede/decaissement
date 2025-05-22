<?php

require_once 'model/SmsSender.php';

$smsSender = new SmsSender();

// Paramètres de test
$phoneNumber = "0748367710"; // Mets ici ton numéro de test au format international sans +
$otp = rand(100000, 999999);
$stationManagerName = "Ulrich AMANI";

// Envoi du SMS
$response = $smsSender->sendOtpToStationManager($phoneNumber, $otp, $stationManagerName);

// Affichage de la réponse brute de l'API
header('Content-Type: text/plain');
echo "Réponse API :\n";
var_dump($response);

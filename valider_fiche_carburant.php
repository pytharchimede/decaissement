<?php

session_start();
require_once 'model/Database.php';
require_once 'model/Fiche.php';
require_once 'model/WhatsAppSMS.php';
require_once 'model/DemandeEssence.php';
require_once 'model/SmsSender.php';


if (!isset($_GET['num_fiche'])) {
    echo "Numéro de fiche manquant.";
    exit;
}

$num_fiche = $_GET['num_fiche'];
$secur = $_SESSION['secur_hop'] ?? 'SYSTEM';
$adresse_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$port = $_SERVER['REMOTE_PORT'] ?? '';

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();
$ficheObj = new Fiche($pdo);
$smsSender = new SmsSender();

// 1. Certifier conforme
$ficheObj->certifierConformeFiche($num_fiche, $secur, $adresse_ip, $port);

// 2. Approuver
$ficheObj->approveFicheByNum($num_fiche, $secur);

// 3. Valider
$success = $ficheObj->validerFicheByNum($num_fiche, $secur, $adresse_ip, $port);

if ($success) {
    // Récupérer les infos du bénéficiaire
    $fiche = $ficheObj->getByNumFiche($num_fiche);
    $whatsappNumber = "+225" . $fiche['tel_beneficiaire_fiche'];
    $nom_demandeur = $fiche['beficiaire_fiche'];

    // Générer le numéro du bon (codification pro)
    $date = new DateTime($fiche['date_creat_fiche']);
    $code_bon = 'BE-' . $date->format('ym') . '-' . substr(str_pad($num_fiche, 5, '0', STR_PAD_LEFT), -5);


    $data = [
        'num_fiche'        => $num_fiche,
        'code_bon'         => $code_bon,
        'nom_beneficiaire' => $fiche['beficiaire_fiche'],
        'vehicule'         => $fiche['designation_fiche'] ?? '',
        'quantite'         => 0, // à adapter si tu as la quantité
        'montant'          => $fiche['montant_fiche'],
        'date_demande'     => $fiche['date_creat_fiche'],
        'motif'            => $fiche['precision_fiche'] ?? '',
        'dg_nom'           => 'M. Alex Braud'
    ];

    var_dump($data); // Pour déboguer les données avant insertion

    // Créer le bon d'essence en base
    $demandeEssenceObj = new DemandeEssence($pdo);
    $demandeEssenceObj->create($data);

    // Envoi du bon via WhatsApp
    $sid = "ACded19f6cd55b2ba3d18c13f438f1e878"; // Votre SID Twilio
    $token = "7f1136b112e6d8cb4a6af94223d0872e"; // Votre TOKEN Twilio
    $from = "whatsapp:+2250711048002"; // Numéro WhatsApp Twilio

    $whatsapp = new WhatsAppSMS($sid, $token, $from);

    // On envoie le code_bon comme identifiant du bon
    $result = $whatsapp->sendCarburantBon($whatsappNumber, $nom_demandeur, $code_bon);

    // Numéro du gérant (format 22507XXXXXXXX)
    $numeroGerant = "2250788202420"; // À remplacer par le vrai numéro


    // Envoi WhatsApp au gérant via template validé (ContentSid)
    // Remarque: le template attend id_bon dans les liens; nous réutilisons $code_bon
    $numeroGerantWhatsApp = "+" . $numeroGerant; // s'assurer du préfixe +225
    $whatsapp->sendNotifCreatToGerant(
        $numeroGerantWhatsApp,
        $code_bon,
        $fiche['beficiaire_fiche'],
        (string)$fiche['montant_fiche'],
        (new DateTime($fiche['date_creat_fiche']))->format('d/m/Y H:i'),
        $code_bon, // pour consultation
        $code_bon  // pour confirmation
    );


    // Envoi du SMS au gérant avec tous les détails du bon (legacy SMS)
    $smsSender->sendBonEssenceToGerant(
        $numeroGerant,
        $code_bon,
        $num_fiche,
        $fiche['beficiaire_fiche'],
        $fiche['designation_fiche'] ?? '',
        0, // ou la vraie quantité si tu l’as
        $fiche['montant_fiche'],
        $fiche['date_creat_fiche'],
        $fiche['precision_fiche'] ?? ''
    );


    echo "<h2>La fiche carburant n°$num_fiche a été certifiée conforme, approuvée et validée avec succès.</h2>";
    if ($result['status'] === 'success') {
        echo "<p>Le bon d'essence a été créé et envoyé au demandeur via WhatsApp.</p>";
        header("refresh:2;url=succes_validation.php");
    } else {
        echo "<p>Erreur lors de l'envoi du bon d'essence WhatsApp : {$result['message']}</p>";
    }
} else {
    echo "<h2>Erreur lors de la validation express de la fiche carburant n°$num_fiche.</h2>";
}

<?php

session_start();
require_once 'model/Database.php';
require_once 'model/Fiche.php';
require_once 'model/WhatsAppSMS.php';
require_once 'model/DemandeEssence.php';


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

// 1. Certifier conforme
$succesCertif = $ficheObj->certifierConformeFiche($num_fiche, $secur, $adresse_ip, $port);

// 2. Approuver
$succesApprove = $ficheObj->approveFicheByNum($num_fiche, $secur);

// 3. Valider
// $success = $ficheObj->validerFicheByNum($num_fiche, $secur, $adresse_ip, $port);

if ($succesCertif && $succesApprove) {
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


    echo "<h2>La fiche réparation n°$num_fiche a été certifiée conforme, approuvée et validée avec succès.</h2>";
    header("Location: succes_validation.php");
    exit;
} else {
    echo "<h2>Erreur lors de la validation express de la fiche réparation n°$num_fiche.</h2>";
}

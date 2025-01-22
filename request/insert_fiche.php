<?php

require_once '../model/Fiche.php';
require_once '../model/EmailManager.php';
require_once '../model/Database.php';

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

// Initialisation
$ficheObj = new Fiche($pdo);
$emailManagerObj = new EmailManager();

// Données de la fiche
$data = [
    'beficiaire_fiche' => $_POST['nom_prenom'],
    'montant_fiche' => $_POST['montant'],
    'tel_beneficiaire_fiche' => $_POST['telephone'],
    'num_fiche' => $ficheObj->generateNumFiche(),
    'affectation_id' => $_POST['affectation'],
    'designation_fiche' => $_POST['motif_demande'],
    'num_piece' => $_POST['mode_paiement'],
    'chantier_id' => $_POST['chantier'],
    'precision_fiche' => $_POST['details_demande'],
    'serv_bureau_banamur_id' => $_POST['service'],
    'serv_bureau_fidest_id' => $_POST['service'],
    'serv_rh_id' => $_POST['service'],
    'serv_log_id' => $_POST['service'],
    'code_autorisation_feb' => $_POST['service']
];

// Insérer la fiche
if ($ficheObj->insertFiche($data)) {
    $subject = "Nouvelle fiche créée";
    $body = "<b>{$data['beficiaire_fiche']}</b> a émis une demande...";
    $recipients = [
        "amichia@fidest.org" => "Amichia KANE",
        "amani_ulrich@outlook.fr" => "Ulrich AMANI"
    ];

    // Envoyer l'email
    $emailManagerObj->sendEmail($subject, $body, $recipients);

    // Retourner un message de succès
    echo json_encode(["status" => "success", "message" => "Fiche insérée avec succès."]);
} else {
    // Retourner un message d'erreur si l'insertion échoue
    echo json_encode(["status" => "error", "message" => "Une erreur est survenue lors de l'insertion."]);
}

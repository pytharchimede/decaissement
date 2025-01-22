<?php

require_once '../model/Fiche.php';
require_once '../model/EmailManager.php';
require_once '../model/Database.php';

// Initialisation
$ficheObj = new Fiche($pdo);
$emailManagerObj = new EmailManager();

// Données de la fiche
$data = [
    'beneficiaire_fiche' => $_POST['beneficiaire_fiche'],
    'montant_fiche' => $_POST['montant_fiche'],
    'tel_beneficiaire_fiche' => $_POST['tel_beneficiaire_fiche'],
    'num_fiche' => $ficheObj->generateNumFiche(),
    'affectation_id' => $_POST['affectation_id'],
    'designation_fiche' => $_POST['designation_fiche'],
    'num_piece' => $_POST['num_piece'],
    'chantier_id' => $_POST['chantier_id'],
    'precision_fiche' => $_POST['precision_fiche'],
    'serv_bureau_banamur_id' => $_POST['serv_bureau_banamur_id'],
    'serv_bureau_fidest_id' => $_POST['serv_bureau_fidest_id'],
    'serv_rh_id' => $_POST['serv_rh_id'],
    'serv_log_id' => $_POST['serv_log_id'],
    'code_autorisation_feb' => $_POST['code_autorisation_feb']
];

// Insérer la fiche
if ($ficheObj->insertFiche($data)) {
    $subject = "Nouvelle fiche créée";
    $body = "<b>{$data['beneficiaire_fiche']}</b> a émis une demande...";
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

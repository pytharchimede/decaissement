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
    'num_piece' => $_POST['mode_paiement'],
    'chantier_id' => $_POST['chantier'],
    'precision_fiche' => $_POST['details_demande'],
    'serv_bureau_banamur_id' => $_POST['service'],
    'code_autorisation_feb' => $_POST['service'],
];

// Vérification des fichiers
$data['photo_beneficiaire'] = isset($_FILES['photo_demandeur']['name']) && $_FILES['photo_demandeur']['error'] == UPLOAD_ERR_OK
    ? $_FILES['photo_demandeur']['name'] : null;

$data['cni_beneficiaire'] = isset($_FILES['cni_demandeur']['name']) && $_FILES['cni_demandeur']['error'] == UPLOAD_ERR_OK
    ? $_FILES['cni_demandeur']['name'] : null;

$data['signature_beneficiaire'] = '';

// Nettoyage des données
$data = array_map(function ($value) {
    return is_string($value) ? trim($value) : $value;
}, $data);

// Debug temporaire
var_dump($data);

// Vérifiez si l'affectation est un chantier (ID = 1)
if ($_POST['affectation'] == "1") {
    $data['chantier_id'] = $_POST['chantier'];
    // Le motif peut être saisi librement
    $data['designation_fiche'] = $_POST['motif_select'];
}

// Vérifiez si l'affectation est un bureau (ID = 19)
if ($_POST['affectation'] == "19") {
    // Utiliser un motif prédéfini ou imposer une validation différente
    if (empty($_POST['motif_input'])) {
        $data['designation_fiche'] = "Aucun motif";
    } else {
        $data['designation_fiche'] = $_POST['motif_input'];
    }

    // Ajoutez les données spécifiques au bureau
    $data['serv_bureau_banamur_id'] = $_POST['service'];
    $data['code_autorisation_feb'] = $_SESSION['code_autorisation_feb'];
}

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

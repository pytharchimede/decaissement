<?php
session_start();
require_once '../model/Fiche.php';
require_once '../model/EmailManager.php';
require_once '../model/Database.php';
require_once '../model/WhatsAppSMS.php';

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

// Initialisation
$ficheObj = new Fiche($pdo);
$emailManagerObj = new EmailManager();

// Envoi notification whatsapp
$sid = "ACded19f6cd55b2ba3d18c13f438f1e878"; // Votre SID Twilio
$token = "7f1136b112e6d8cb4a6af94223d0872e"; // Numéro WhatsApp Twilio
$from = "whatsapp:+2250711048002"; // Numéro WhatsApp Twilio

$whatsapp = new WhatsAppSMS($sid, $token, $from);


// Dossiers pour les fichiers
$photoDir = '../../img_demande/';
$cniDir = '../../logi/img/';

// Création des dossiers s'ils n'existent pas
if (!is_dir($photoDir)) {
    mkdir($photoDir, 0777, true);
}
if (!is_dir($cniDir)) {
    mkdir($cniDir, 0777, true);
}

// Vérification et gestion de la photo du bénéficiaire
if (isset($_FILES['photo_demandeur']['name']) && $_FILES['photo_demandeur']['error'] == UPLOAD_ERR_OK) {
    $photoExtension = pathinfo($_FILES['photo_demandeur']['name'], PATHINFO_EXTENSION);
    $photoNewName = "photo_" . time() . "." . $photoExtension;
    $photoPath = $photoDir . $photoNewName;

    if (move_uploaded_file($_FILES['photo_demandeur']['tmp_name'], $photoPath)) {
        $data['photo_beneficiaire'] = $photoNewName;
    } else {
        $data['photo_beneficiaire'] = '';
    }
}

// Ajoutez cette ligne avant la gestion de la CNI
$cniNewName = '';

// Vérification et gestion de la CNI du bénéficiaire
if (isset($_FILES['cni_demandeur']['name']) && $_FILES['cni_demandeur']['error'] == UPLOAD_ERR_OK) {
    $cniExtension = pathinfo($_FILES['cni_demandeur']['name'], PATHINFO_EXTENSION);
    $cniNewName = "cni_" . time() . "." . $cniExtension;
    $cniPath = $cniDir . $cniNewName;

    if (move_uploaded_file($_FILES['cni_demandeur']['tmp_name'], $cniPath)) {
        $data['cni_beneficiaire'] = $cniNewName;
    } else {
        $data['cni_beneficiaire'] = '';
    }
} else {
    $data['cni_beneficiaire'] = '';
}

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
    'code_autorisation_feb' => isset($_SESSION['code_autorisation_feb']) ? $_SESSION['code_autorisation_feb'] : '',
    'entreprise' => isset($_SESSION['companyName']) ? $_SESSION['companyName'] : '',
];

error_log(json_encode($data));

if ($data['code_autorisation_feb'] == 'Pas autorise' || !isset($data['code_autorisation_feb']) || $_SESSION['companyName'] == '' || !isset($_SESSION['companyName'])) {
    // Retourner un message de succès
    echo json_encode(["status" => "error", "message" => "Session expirée ou inexistante ! Veuillez recommencer svp."]);
    exit; // Arrête l'exécution du script
}


//Définir une variable de session
$_SESSION['num_fiche'] = $data['num_fiche'];

// Vérification des fichiers
$data['photo_beneficiaire'] = $photoNewName ? $photoNewName : '';

$data['cni_beneficiaire'] = $cniNewName ? $cniNewName : '';

// Nettoyage des données
$data = array_map(function ($value) {
    return is_string($value) ? trim($value) : $value;
}, $data);


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
    $body = "<b>{$data['beficiaire_fiche']}</b> a émis une demande de <b>{$data['montant_fiche']}</b> pour <b>{$data['precision_fiche']}</b>";
    $recipients = [
        "braud@fidest.org" => "Alex BRAUD",
        "amani_ulrich@outlook.fr" => "Ulrich AMANI"
    ];

    // Envoyer l'email
    $emailManagerObj->sendEmail($subject, $body, $recipients);

    //Envoyer message de confirmation de soumission au demandeur
    $whatsappNumber = "+225" . $data['tel_beneficiaire_fiche'];
    $num_fiche = $data['num_fiche'];

    // $whatsapp->sendConfirmationSoumissionFicheDecaissement($whatsappNumber, $data['beficiaire_fiche'], $data['num_fiche']);

    // Vérifier si la demande concerne le carburant dans precision_fiche ou designation_fiche
    $texte_precision = isset($data['precision_fiche']) ? $data['precision_fiche'] : '';
    $texte_designation = isset($data['designation_fiche']) ? $data['designation_fiche'] : '';

    if (
        stripos($texte_precision, 'carburant') !== false ||
        stripos($texte_designation, 'carburant') !== false
    ) {
        // Numéro du DG (à adapter si besoin)
        $num_dg = "05055262";
        $whatsappNumberDG = "+225" . $num_dg;

        // Appel à la méthode d'envoi de l'alerte au DG uniquement
        $responseApproval = $whatsapp->sendCarburantApprovalAlert(
            $whatsappNumberDG,
            $data['beficiaire_fiche'],
            $data['num_fiche'],
            $data['montant_fiche'],
            $texte_precision ?: $texte_designation
        );

        // Débogage : Vérifier la réponse de Twilio
        if ($responseApproval['status'] !== 'success') {
            error_log("Erreur lors de l'envoi du message WhatsApp : " . $responseApproval['message']);
        }
    }

    // Retourner un message de succès
    echo json_encode(["status" => "success", "message" => "Fiche inseree avec succes."]);
} else {
    // Retourner un message d'erreur si l'insertion échoue
    echo json_encode(["status" => "error", "message" => "Une erreur est survenue lors de l'insertion."]);
}

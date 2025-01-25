<?php
require_once '../model/Database.php'; // Classe pour gérer la connexion à la base de données
require_once '../model/Fiche.php'; // Classe Fiche
require_once '../model/Utilisateur.php'; // Classe Utilisateur

// Configuration de la connexion PDO
$pdo = Database::getConnection();
$ficheModel = new Fiche($pdo); // Instancier la classe Fiche
$utilisateurModel = new Utilisateur($pdo); // Instancier la classe Utilisateur

// Récupérer les paramètres envoyés par Twilio dans le webhook
$recipientNumber = isset($_GET['recipientNumber']) ? $_GET['recipientNumber'] : null; // Paramètre dans l'URL
$from = isset($_POST['From']) ? $_POST['From'] : null; // Numéro de l'utilisateur (WhatsApp)
$body = isset($_POST['Body']) ? trim(strtolower($_POST['Body'])) : null; // Réponse de l'utilisateur ("approuver" ou "refuser")
$fileNumber = isset($_POST['file_number']) ? $_POST['file_number'] : null; // Numéro de la fiche
$amount = isset($_POST['amount']) ? $_POST['amount'] : null; // Montant envoyé
$purpose = isset($_POST['purpose']) ? $_POST['purpose'] : null; // Objectif de la demande

// Vérifier que les paramètres requis sont présents
if (!$recipientNumber || !$from || !$body || !$fileNumber || !$amount || !$purpose) {
    http_response_code(400); // Mauvaise requête
    error_log("Requête invalide. Paramètres manquants : " . json_encode($_POST));
    echo "Requête invalide. Paramètres manquants.";
    exit;
}

// Retirer le préfixe +225 du recipientNumber
$recipientNumberWithoutPrefix = substr($recipientNumber, 4); // Retirer les 4 premiers caractères (+225)

// Récupérer l'utilisateur à partir du numéro sans le préfixe
$utilisateur = $utilisateurModel->getByPhoneNumber($recipientNumberWithoutPrefix);

// Vérifier si l'utilisateur a été trouvé
if (!$utilisateur) {
    http_response_code(404); // Pas trouvé
    error_log("Utilisateur introuvable pour ce numéro : " . $recipientNumberWithoutPrefix);
    echo "Utilisateur introuvable.";
    exit;
}

// Extraire les informations nécessaires de l'utilisateur
$secur = $utilisateur['secur']; // Récupérer la valeur du champ 'secur'
$signature_util = $utilisateur['signature_util']; // Récupérer la valeur du champ 'signature_util'

// Traitez la réponse de l'utilisateur
if ($body === 'approuver') {
    // Mettre à jour la fiche comme "Approuvée"
    $ficheModel->approveFicheByNum($fileNumber, $secur);
    echo "La fiche $fileNumber a été approuvée.";
} elseif ($body === 'refuser') {
    // Mettre à jour la fiche comme "Refusée"
    $ficheModel->refuseFicheByNum($fileNumber, $secur);
    echo "La fiche $fileNumber a été refusée.";
} else {
    echo "Réponse invalide. Veuillez répondre avec 'Approuver' ou 'Refuser'.";
}

http_response_code(200);

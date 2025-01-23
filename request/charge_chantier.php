<?php
session_start();

include '../model/Database.php';
include '../model/Chantier.php';

// Initialisation de la connexion à la base de données
$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();
$chantierObj = new Chantier($pdo);

// Vérification si le paramètre 'entreprise' est passé via POST
if (isset($_POST['entreprise']) && !empty($_POST['entreprise'])) {
    $entreprise = $_POST['entreprise'];

    // Récupération des chantiers associés à l'entreprise
    $listeChantier = $chantierObj->getAllChantiersByEntreprise($entreprise);

    // Retourner les chantiers au format JSON
    echo json_encode($listeChantier);
} else {
    // Retourner une réponse vide si le paramètre 'entreprise' est manquant
    echo json_encode([]);
}

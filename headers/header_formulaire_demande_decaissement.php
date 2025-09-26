<?php
session_start();

// Mode EXPRESS: permet d'accéder sans code initial et génère un code express incrémental
if ((!isset($_SESSION['code_autorisation_feb']) || $_SESSION['code_autorisation_feb'] == '')
    && isset($_GET['express']) && $_GET['express'] == '1'
) {
    // Inclure les dépendances minimales pour générer un code express
    require_once __DIR__ . '/../model/Database.php';
    require_once __DIR__ . '/../model/Fiche.php';

    $dataBaseObj = new Database();
    $pdo = $dataBaseObj->getConnection();
    $ficheTmp = new Fiche($pdo);

    // Préfixe caractéristique du chantier dépollution
    $prefix = 'EXP-DEP';
    $generated = $ficheTmp->generateExpressAuthCode($prefix);
    $_SESSION['code_autorisation_feb'] = $generated;
    $_SESSION['express_mode'] = true;
}

// Si toujours pas de code en session, rediriger vers le portail d'autorisation standard
if (!isset($_SESSION['code_autorisation_feb']) || $_SESSION['code_autorisation_feb'] == '') {
    header('Location: https://fidest.ci/performance/demande_decaissement.php');
    exit();
}

require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/Affectation.php';
require_once __DIR__ . '/../model/Service.php';
require_once __DIR__ . '/../model/Fiche.php';

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

$affectationObj = new Affectation($pdo);
$serviceObj = new Service($pdo);
$ficheObj = new Fiche($pdo);

$listeAffectation = $affectationObj->getAllAffectations();
$listeService = $serviceObj->getAllServices();

$_SESSION['code_autorisation_feb'] = isset($_SESSION['code_autorisation_feb']) ? $_SESSION['code_autorisation_feb'] : 'Pas autorise';

$exist = $ficheObj->getByAuthCode($_SESSION['code_autorisation_feb']);

if (!empty($exist)) { // Si un enregistrement existe avec ce code
    unset($_SESSION['code_autorisation_feb']);
    header('Location: ../performance/code_deja_utilise.php');
    exit();
}

<?php
session_start();

// if (!isset($_SESSION['code_autorisation_feb']) || $_SESSION['code_autorisation_feb'] == '') {

//     header('Location: https://fidest.ci/performance/demande_decaissement.php');

//     exit();
// }

include 'model/Database.php';
include 'model/Affectation.php';
include 'model/Service.php';
include 'model/Fiche.php';

$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

$affectationObj = new Affectation($pdo);
$serviceObj = new Service($pdo);
$ficheObj = new Fiche($pdo);

$listeAffectation = $affectationObj->getAllAffectations();
$listeService = $serviceObj->getAllServices();

// $exist = $ficheObj->getByAuthCode($_SESSION['code_autorisation_feb']);

// $nbExist = count($exist);

// if ($nbExist > 0) {

//     unset($_SESSION['code_autorisation_feb']);

//     header('Location: ../performance/code_deja_utilise.php');

//     exit();
// }

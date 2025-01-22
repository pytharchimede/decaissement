<?php
session_start();

// if (!isset($_SESSION['code_autorisation_feb']) || $_SESSION['code_autorisation_feb'] == '') {

//     header('Location: https://fidest.ci/performance/demande_decaissement.php');

//     exit();
// }

include 'model/Database.php';
include 'model/Affectation.php';
include 'model/Chantier.php';
include 'model/Service.php';


$dataBaseObj = new Database();
$pdo = $dataBaseObj->getConnection();

$chantierObj = new Chantier($pdo);
$affectationObj = new Affectation($pdo);
$serviceObj = new Service($pdo);

$listeAffectation = $affectationObj->getAllAffectations();
$listeChantier = $chantierObj->getAllChantiers();
$listeService = $serviceObj->getAllServices();

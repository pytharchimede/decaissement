<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Inclusion des controllers avec chemins absolus
require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/DemandeEssenceController.php';
require_once __DIR__ . '/../model/RechargementCarburantController.php';
require_once __DIR__ . '/../model/OtpController.php';
require_once __DIR__ . '/../model/SoldeController.php';
require_once __DIR__ . '/../model/StationsServiceController.php';
require_once __DIR__ . '/../model/CarburantUtilisationController.php';
require_once __DIR__ . '/../model/HistoriqueBonsController.php';
require_once __DIR__ . '/../model/DemandesCarburantAttenteController.php';
require_once __DIR__ . '/../model/VehiculeController.php';
require_once __DIR__ . '/../model/ChauffeurController.php';
require_once __DIR__ . '/../model/MarqueController.php';
require_once __DIR__ . '/../model/TypeEnginController.php';
require_once __DIR__ . '/../model/SoldeEvolutionController.php';
require_once __DIR__ . '/../model/PlanningController.php';
require_once __DIR__ . '/../model/RapportJournalierController.php';

// StationsServiceController sera inclus uniquement si nécessaire

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Gestion des requêtes OPTIONS pour CORS pré-flight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

switch ($endpoint) {
    case 'demande_essence':
        if ($method === 'GET') {
            $controller = new DemandeEssenceController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'stations_service':
        if ($method === 'GET') {
            $controller = new StationsServiceController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'rechargement_carburant':
        if ($method === 'GET') {
            $controller = new RechargementCarburantController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'confirmation_carburant':
        if ($method === 'POST') {
            $controller = new OtpController();
            $controller->sendConfirmationCarburant();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;


    case 'validate_otp':
        if ($method === 'POST') {
            $controller = new OtpController();
            $controller->validateOtp();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'solde':
        if ($method === 'GET') {
            $controller = new SoldeController();
            $controller->getSolde();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'carburant_utilisation':
        if ($method === 'GET') {
            $controller = new CarburantUtilisationController();
            $controller->getUtilisation();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'solde_evolution':
        if ($method === 'GET') {
            $controller = new SoldeEvolutionController();
            $controller->getEvolution();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'historique_bons':
        if ($method === 'GET') {
            $controller = new HistoriqueBonsController();
            $controller->getHistorique();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'demandes_carburant_attente':
        if ($method === 'GET') {
            $controller = new DemandesCarburantAttenteController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'vehicules':
        if ($method === 'GET') {
            $controller = new VehiculeController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_vehicule':
        if ($method === 'POST') {
            $controller = new VehiculeController();
            $controller->ajouterVehicule();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'chauffeurs':
        if ($method === 'GET') {
            $controller = new ChauffeurController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'marques':
        if ($method === 'GET') {
            $controller = new MarqueController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_marque':
        if ($method === 'POST') {
            $controller = new MarqueController();
            $controller->ajouterMarque();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'types_engin':
        if ($method === 'GET') {
            $controller = new TypeEnginController();
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_chauffeur':
        if ($method === 'POST') {
            $controller = new ChauffeurController();
            $controller->ajouterChauffeur();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'chauffeur_pieces':
        if ($method === 'GET') {
            $controller = new ChauffeurController();
            $controller->getPieces();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;


    case 'planning':
        if ($method === 'GET') {
            $controller = new PlanningController();
            $controller->getAll();
        } elseif ($method === 'POST') {
            $controller = new PlanningController();
            $controller->ajouter();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'rapport_journalier':
        if ($method === 'GET') {
            $controller = new RapportJournalierController();
            $controller->getAll();
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $controller = new RapportJournalierController();
            $controller->update();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

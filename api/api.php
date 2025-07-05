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
            // Exemple statique, à adapter
            echo json_encode(['utilisation' => 0.75]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'solde_evolution':
        if ($method === 'GET') {
            // Exemple statique, à adapter
            echo json_encode([
                ['jour' => 1, 'solde' => 350000],
                ['jour' => 5, 'solde' => 420000],
                ['jour' => 10, 'solde' => 380000],
                ['jour' => 15, 'solde' => 20000],
                ['jour' => 20, 'solde' => 500000],
                ['jour' => 25, 'solde' => 450000],
                ['jour' => 30, 'solde' => 520000],
            ]);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

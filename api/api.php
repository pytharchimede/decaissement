<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../model/Database.php';
require_once '../model/DemandeEssenceController.php';
require_once '../model/RechargementCarburantController.php';
require_once '../model/OtpController.php';
require_once '../model/SoldeController.php';


$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'demande_essence':
        $controller = new DemandeEssenceController();
        if ($method === 'GET') {
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'rechargement_carburant':
        $controller = new RechargementCarburantController();
        if ($method === 'GET') {
            $controller->getAll();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'validate_otp':
        $controller = new OtpController();
        if ($method === 'POST') {
            $controller->validateOtp();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case 'solde':
        if ($method === 'GET') {
            require_once '../model/SoldeController.php';
            $controller = new SoldeController();
            $controller->getSolde();
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

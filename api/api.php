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




    case 'historique_bons':
        if ($method === 'GET') {
            // Récupération des paramètres de filtre et pagination
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $station = isset($_GET['station']) ? trim($_GET['station']) : null;
            $dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
            $dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;
            $montantMin = isset($_GET['montant_min']) ? floatval($_GET['montant_min']) : null;
            $montantMax = isset($_GET['montant_max']) ? floatval($_GET['montant_max']) : null;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $pdo = Database::getConnection();
            $where = ["b.desactive = 0"];
            $params = [];

            if ($station) {
                $where[] = "s.nom_station LIKE :station";
                $params['station'] = "%$station%";
            }
            if ($dateDebut) {
                $where[] = "DATE(b.date_demande) >= :dateDebut";
                $params['dateDebut'] = $dateDebut;
            }
            if ($dateFin) {
                $where[] = "DATE(b.date_demande) <= :dateFin";
                $params['dateFin'] = $dateFin;
            }
            if ($montantMin !== null) {
                $where[] = "b.montant >= :montantMin";
                $params['montantMin'] = $montantMin;
            }
            if ($montantMax !== null) {
                $where[] = "b.montant <= :montantMax";
                $params['montantMax'] = $montantMax;
            }

            $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

            try {
                // Récupérer les bons paginés
                $sql = "SELECT b.*, s.nom_station, s.nom_gerant 
                FROM demande_essence b
                LEFT JOIN stations_service s ON b.station_id = s.id
                $whereSql
                ORDER BY b.date_demande DESC
                LIMIT :limit OFFSET :offset";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $bons = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Montant total filtré
                $sqlTotal = "SELECT SUM(b.montant) as montantTotal
                     FROM demande_essence b
                     LEFT JOIN stations_service s ON b.station_id = s.id
                     $whereSql";
                $stmtTotal = $pdo->prepare($sqlTotal);
                foreach ($params as $k => $v) {
                    $stmtTotal->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmtTotal->execute();
                $montantTotal = $stmtTotal->fetchColumn() ?: 0;

                // Savoir s'il y a encore des bons à charger
                $sqlCount = "SELECT COUNT(*) FROM demande_essence b
                     LEFT JOIN stations_service s ON b.station_id = s.id
                     $whereSql";
                $stmtCount = $pdo->prepare($sqlCount);
                foreach ($params as $k => $v) {
                    $stmtCount->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmtCount->execute();
                $totalCount = $stmtCount->fetchColumn();
                $hasMore = ($offset + $limit) < $totalCount;

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'bons' => $bons,
                        'montantTotal' => floatval($montantTotal),
                        'hasMore' => $hasMore,
                        'page' => $page,
                        'totalCount' => intval($totalCount)
                    ]
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération de l'historique : " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'demandes_carburant_attente':
        if ($method === 'GET') {
            try {
                $pdo = Database::getConnection();
                // Recherche insensible à la casse sur precision_fiche
                $sql = "SELECT * FROM fiche 
                    WHERE approuve = 0 
                    AND LOWER(precision_fiche) LIKE :motclef
                    ORDER BY date_creat_fiche DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['motclef' => '%carburant%']);
                $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'status' => 'success',
                    'data' => $demandes
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération des demandes : " . $e->getMessage()
                ]);
            }
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

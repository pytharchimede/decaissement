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
            $controller = new HistoriqueBonsController();
            $controller->getHistorique();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
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

    case 'vehicules':
        if ($method === 'GET') {
            try {
                $pdo = Database::getConnection();
                // Recherche par marque ou chauffeur
                $where = [];
                $params = [];
                if (!empty($_GET['marque'])) {
                    $where[] = "v.marque LIKE :marque";
                    $params['marque'] = "%" . $_GET['marque'] . "%";
                }
                if (!empty($_GET['chauffeur'])) {
                    $where[] = "c.nom LIKE :chauffeur";
                    $params['chauffeur'] = "%" . $_GET['chauffeur'] . "%";
                }
                $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

                $sql = "SELECT v.*, m.nom AS marque_nom, m.logo_url AS marque_logo, c.nom AS chauffeur_nom, c.telephone AS chauffeur_telephone
                        FROM vehicule v
                        LEFT JOIN marque m ON v.marque_id = m.id
                        LEFT JOIN chauffeur c ON v.chauffeur_id = c.id
                        $whereSql
                        ORDER BY v.created_at DESC";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
                }
                $stmt->execute();
                $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Ajout des documents pour chaque véhicule
                foreach ($vehicules as &$vehicule) {
                    $stmtDoc = $pdo->prepare("SELECT type, url FROM vehicule_document WHERE vehicule_id = ?");
                    $stmtDoc->execute([$vehicule['id']]);
                    $vehicule['documents'] = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $vehicules
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération : " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_vehicule':
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['type'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Type requis']);
                return;
            }
            try {
                $pdo = Database::getConnection();
                // Gestion du chauffeur
                $chauffeur_id = null;
                if (!empty($input['chauffeur_nom'])) {
                    // Recherche ou ajout du chauffeur
                    $stmt = $pdo->prepare("SELECT id FROM chauffeur WHERE nom = :nom");
                    $stmt->execute(['nom' => $input['chauffeur_nom']]);
                    $chauffeur = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($chauffeur) {
                        $chauffeur_id = $chauffeur['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO chauffeur (nom, telephone, permis) VALUES (:nom, :tel, :permis)");
                        $stmt->execute([
                            'nom' => $input['chauffeur_nom'],
                            'tel' => $input['chauffeur_telephone'] ?? null,
                            'permis' => $input['permis'] ?? null
                        ]);
                        $chauffeur_id = $pdo->lastInsertId();
                    }
                }

                $fields = [
                    'type',
                    'plaque',
                    'type_engin_id',
                    'marque_id',
                    'modele',
                    'permis',
                    'carte_grise',
                    'assurance',
                    'nom',
                    'numero_serie',
                    'photo_url'
                ];
                $cols = [];
                $vals = [];
                $params = [];
                foreach ($fields as $f) {
                    if (isset($input[$f])) {
                        $cols[] = $f;
                        $vals[] = ":$f";
                        $params[$f] = $input[$f];
                    }
                }
                // Ajout du chauffeur_id UNIQUEMENT s'il n'est pas déjà dans $input
                if ($chauffeur_id && !isset($input['chauffeur_id'])) {
                    $cols[] = 'chauffeur_id';
                    $vals[] = ':chauffeur_id';
                    $params['chauffeur_id'] = $chauffeur_id;
                }
                $sql = "INSERT INTO vehicule (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $vehicule_id = $pdo->lastInsertId();

                // Ajout des documents
                if (!empty($input['documents']) && is_array($input['documents'])) {
                    $stmtDoc = $pdo->prepare("INSERT INTO vehicule_document (vehicule_id, type, url) VALUES (?, ?, ?)");
                    foreach ($input['documents'] as $doc) {
                        $stmtDoc->execute([$vehicule_id, $doc['type'], $doc['url'] ?? null]);
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Ajouté avec succès']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'chauffeurs':
        if ($method === 'GET') {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT * FROM chauffeur ORDER BY nom ASC");
                $chauffeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'status' => 'success',
                    'data' => $chauffeurs
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération : " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'marques':
        if ($method === 'GET') {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT * FROM marque ORDER BY nom ASC");
                $marques = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'status' => 'success',
                    'data' => $marques
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération : " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_marque':
        if ($method === 'POST') {
            if (!isset($_POST['nom'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Nom de la marque requis']);
                return;
            }
            $nom = trim($_POST['nom']);
            $logo_url = null;

            // Gestion de l'upload du logo (optionnel)
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/../uploads/marques/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true)) {
                            // On continue sans logo si le dossier ne peut pas être créé
                            $logo_url = null;
                        }
                    }
                    $filename = uniqid('marque_') . '.' . $ext;
                    $dest = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        $logo_url = 'uploads/marques/' . $filename;
                    }
                }
                // Si le format n'est pas conforme, on ignore simplement le logo
            }

            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("INSERT INTO marque (nom, logo_url) VALUES (:nom, :logo_url)");
                $stmt->execute([
                    'nom' => $nom,
                    'logo_url' => $logo_url
                ]);
                echo json_encode(['status' => 'success', 'message' => 'Marque ajoutée']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'types_engin':
        if ($method === 'GET') {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SELECT * FROM type_engin ORDER BY nom ASC");
                $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'status' => 'success',
                    'data' => $types
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Erreur lors de la récupération : " . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'ajouter_chauffeur':
        if ($method === 'POST') {
            if (!isset($_POST['nom'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Nom du chauffeur requis']);
                return;
            }
            $nom = trim($_POST['nom']);
            $telephone = $_POST['telephone'] ?? null;
            $permis = $_POST['permis'] ?? null;
            $groupe_sanguin = $_POST['groupe_sanguin'] ?? null;
            $photo_url = null;

            // Gestion de l'upload de la photo (optionnel)
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/../uploads/chauffeurs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $filename = uniqid('chauffeur_') . '.' . $ext;
                    $dest = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                        $photo_url = 'uploads/chauffeurs/' . $filename;
                    }
                }
            }

            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("INSERT INTO chauffeur (nom, telephone, permis, groupe_sanguin, photo_url) VALUES (:nom, :telephone, :permis, :groupe_sanguin, :photo_url)");
                $stmt->execute([
                    'nom' => $nom,
                    'telephone' => $telephone,
                    'permis' => $permis,
                    'groupe_sanguin' => $groupe_sanguin,
                    'photo_url' => $photo_url
                ]);
                $chauffeur_id = $pdo->lastInsertId();

                // Gestion des pièces jointes (ex: CNI, autres)
                if (!empty($_FILES['pieces']) && is_array($_FILES['pieces']['name'])) {
                    $uploadDir = __DIR__ . '/../uploads/chauffeurs/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    foreach ($_FILES['pieces']['name'] as $i => $pieceName) {
                        if ($_FILES['pieces']['error'][$i] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($pieceName, PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                            if (in_array($ext, $allowed)) {
                                $filename = uniqid('piece_') . '.' . $ext;
                                $dest = $uploadDir . $filename;
                                if (move_uploaded_file($_FILES['pieces']['tmp_name'][$i], $dest)) {
                                    $piece_url = 'uploads/chauffeurs/' . $filename;
                                    $type_piece = $_POST['pieces_types'][$i] ?? 'Document';
                                    // Enregistrement en base (table à créer si besoin)
                                    $stmtPiece = $pdo->prepare("INSERT INTO chauffeur_piece (chauffeur_id, type, url) VALUES (?, ?, ?)");
                                    $stmtPiece->execute([$chauffeur_id, $type_piece, $piece_url]);
                                }
                            }
                        }
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Chauffeur ajouté']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
            }
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case 'chauffeur_pieces':
        if ($method === 'GET' && isset($_GET['chauffeur_id'])) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT * FROM chauffeur_piece WHERE chauffeur_id = ?");
                $stmt->execute([$_GET['chauffeur_id']]);
                $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'status' => 'success',
                    'data' => $pieces
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'chauffeur_id requis']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

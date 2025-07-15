<?php
// filepath: c:\wamp\www\decaissement\model\VehiculeController.php

class VehiculeController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function ajouterVehicule()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['type'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Type requis']);
            return;
        }
        try {
            // Gestion du chauffeur
            $chauffeur_id = null;
            if (!empty($input['chauffeur_nom'])) {
                // Recherche ou ajout du chauffeur
                $stmt = $this->pdo->prepare("SELECT id FROM chauffeur WHERE nom = :nom");
                $stmt->execute(['nom' => $input['chauffeur_nom']]);
                $chauffeur = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($chauffeur) {
                    $chauffeur_id = $chauffeur['id'];
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO chauffeur (nom, telephone, permis) VALUES (:nom, :tel, :permis)");
                    $stmt->execute([
                        'nom' => $input['chauffeur_nom'],
                        'tel' => $input['chauffeur_telephone'] ?? null,
                        'permis' => $input['permis'] ?? null
                    ]);
                    $chauffeur_id = $this->pdo->lastInsertId();
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
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $vehicule_id = $this->pdo->lastInsertId();

            // Ajout des documents
            if (!empty($input['documents']) && is_array($input['documents'])) {
                $stmtDoc = $this->pdo->prepare("INSERT INTO vehicule_document (vehicule_id, type, url) VALUES (?, ?, ?)");
                foreach ($input['documents'] as $doc) {
                    $stmtDoc->execute([$vehicule_id, $doc['type'], $doc['url'] ?? null]);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Ajouté avec succès']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
        }
    }


    public function getAll()
    {
        try {
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
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(":$k", $v, PDO::PARAM_STR);
            }
            $stmt->execute();
            $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Ajout des documents pour chaque véhicule
            foreach ($vehicules as &$vehicule) {
                $stmtDoc = $this->pdo->prepare("SELECT type, url FROM vehicule_document WHERE vehicule_id = ?");
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
    }
}

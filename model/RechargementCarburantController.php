<?php
require_once 'Database.php';

class RechargementCarburantController
{
    public function getAll()
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM rechargement_carburant ORDER BY date_rechargement_carburant DESC");
            $stmt->execute();
            $rechargements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['data' => $rechargements]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }

    public function getHistorique()
    {
        try {
            $pdo = Database::getConnection();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Paramètres
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? max(1, min(200, intval($_GET['per_page']))) : 50;
            $offset = ($page - 1) * $perPage;
            $dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
            $dateFin   = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;
            $stationId = isset($_GET['station_id']) ? intval($_GET['station_id']) : null;

            // Filtre de dates (prend la date effective: date_rechargement_carburant si valide sinon date_enregistrement)
            $dateExpr = "CASE WHEN date_rechargement_carburant IS NOT NULL AND date_rechargement_carburant <> '0000-00-00' THEN DATE(date_rechargement_carburant) ELSE DATE(date_enregistrement) END";

            $where = [];
            $params = [];
            if (!empty($dateDebut)) {
                $where[] = "$dateExpr >= :d1";
                $params[':d1'] = $dateDebut;
            }
            if (!empty($dateFin)) {
                $where[] = "$dateExpr <= :d2";
                $params[':d2'] = $dateFin;
            }
            if (!empty($stationId)) {
                $where[] = "station_id = :sid";
                $params[':sid'] = $stationId;
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            // Requête principale paginée
            $sql = "SELECT id_rechargement_carburant AS id, $dateExpr AS date, montant_rechargement_carburant AS montant, img_recu
                    FROM rechargement_carburant
                    $whereSql
                    ORDER BY $dateExpr DESC, id_rechargement_carburant DESC
                    LIMIT :lim OFFSET :off";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalisation image: si présent sans chemin, préfixer dossier upload
            foreach ($rows as &$r) {
                if (!empty($r['img_recu'])) {
                    $img = trim($r['img_recu']);
                    if ($img && !preg_match('/^https?:\/\//i', $img)) {
                        // Conserver uniquement le nom de fichier si chemin complet stocké
                        $base = basename($img);
                        $r['img_recu'] = 'recharge_recu/' . $base;
                    }
                }
            }

            // Total pour la période
            $sqlTotal = "SELECT COALESCE(SUM(montant_rechargement_carburant),0) AS total
                         FROM rechargement_carburant $whereSql";
            $stTotal = $pdo->prepare($sqlTotal);
            foreach ($params as $k => $v) {
                $stTotal->bindValue($k, $v);
            }
            $stTotal->execute();
            $total = (float)($stTotal->fetchColumn() ?: 0);

            // hasMore
            $sqlCount = "SELECT COUNT(*) FROM rechargement_carburant $whereSql";
            $stCount = $pdo->prepare($sqlCount);
            foreach ($params as $k => $v) {
                $stCount->bindValue($k, $v);
            }
            $stCount->execute();
            $count = (int)$stCount->fetchColumn();
            $hasMore = ($offset + count($rows)) < $count;

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'rechargements' => $rows,
                    'hasMore' => $hasMore,
                    'totalMontant' => $total,
                    'page' => $page,
                    'perPage' => $perPage
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur serveur', 'detail' => $e->getMessage()]);
        }
    }
}

<?php
// filepath: c:\wamp\www\decaissement\model\HistoriqueBonsController.php

class HistoriqueBonsController
{
    private $pdo;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 200;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    private function jsonOut($payload, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private function validateDate(?string $d): ?string
    {
        if (!$d) return null;
        // Accepte formats YYYY-MM-DD ou DD/MM/YYYY -> convertit en YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $d, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null; // invalide
    }

    public function getHistorique(): void
    {
        // Paramètres & validation
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limitReq = isset($_GET['limit']) ? intval($_GET['limit']) : self::DEFAULT_LIMIT;
        $limit = ($limitReq > 0) ? min($limitReq, self::MAX_LIMIT) : self::DEFAULT_LIMIT;
        $station = isset($_GET['station']) ? trim($_GET['station']) : null;
        $dateDebut = $this->validateDate($_GET['date_debut'] ?? null);
        $dateFin = $this->validateDate($_GET['date_fin'] ?? null);
        $montantMin = isset($_GET['montant_min']) ? (is_numeric($_GET['montant_min']) ? floatval($_GET['montant_min']) : null) : null;
        $montantMax = isset($_GET['montant_max']) ? (is_numeric($_GET['montant_max']) ? floatval($_GET['montant_max']) : null) : null;
        $sort = $_GET['sort'] ?? 'date_demande';
        $dir = strtolower($_GET['dir'] ?? 'desc');
        $allowedSort = ['date_demande', 'montant', 'station'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'date_demande';
        $dir = ($dir === 'asc') ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $limit;

        if ($dateDebut && $dateFin && $dateFin < $dateDebut) {
            $this->jsonOut(['status' => 'error', 'message' => 'Intervalle de dates invalide (date_fin < date_debut)'], 400);
            return; // éviter return value sur void
        }
        if ($montantMin !== null && $montantMax !== null && $montantMax < $montantMin) {
            $this->jsonOut(['status' => 'error', 'message' => 'Intervalle de montants invalide (montant_max < montant_min)'], 400);
            return;
        }

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
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Mapping tri
        $orderBy = 'b.date_demande DESC';
        if ($sort === 'montant') $orderBy = "b.montant $dir";
        elseif ($sort === 'station') $orderBy = "s.nom_station $dir";
        elseif ($sort === 'date_demande') $orderBy = "b.date_demande $dir";

        try {
            // Requête principale paginée
            $sql = "SELECT b.*, s.nom_station, s.nom_gerant
                    FROM demande_essence b
                    LEFT JOIN stations_service s ON b.station_id = s.id
                    $whereSql
                    ORDER BY $orderBy
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
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
            $stmtTotal = $this->pdo->prepare($sqlTotal);
            foreach ($params as $k => $v) {
                $stmtTotal->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtTotal->execute();
            $montantTotal = $stmtTotal->fetchColumn() ?: 0;

            // Nombre total
            $sqlCount = "SELECT COUNT(*) FROM demande_essence b
                         LEFT JOIN stations_service s ON b.station_id = s.id
                         $whereSql";
            $stmtCount = $this->pdo->prepare($sqlCount);
            foreach ($params as $k => $v) {
                $stmtCount->bindValue(":$k", $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $totalCount = (int)$stmtCount->fetchColumn();
            $hasMore = ($offset + $limit) < $totalCount;
            $totalPages = ($limit > 0) ? (int)ceil($totalCount / $limit) : 1;

            // Réponse aplatie pour correspondre au client mobile (result['bons'], etc.)
            $this->jsonOut([
                'status' => 'success',
                'bons' => $bons,
                'montantTotal' => (float)$montantTotal,
                'hasMore' => $hasMore,
                'page' => $page,
                'limit' => $limit,
                'totalCount' => $totalCount,
                'totalPages' => $totalPages,
                'sort' => $sort,
                'dir' => strtolower($dir)
            ]);
        } catch (Throwable $e) {
            $this->jsonOut([
                'status' => 'error',
                'message' => "Erreur lors de la récupération de l'historique : " . $e->getMessage()
            ], 500);
        }
    }
}

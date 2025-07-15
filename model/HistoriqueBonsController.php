<?php
// filepath: c:\wamp\www\decaissement\model\HistoriqueBonsController.php

class HistoriqueBonsController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getHistorique()
    {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $station = isset($_GET['station']) ? trim($_GET['station']) : null;
        $dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
        $dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;
        $montantMin = isset($_GET['montant_min']) ? floatval($_GET['montant_min']) : null;
        $montantMax = isset($_GET['montant_max']) ? floatval($_GET['montant_max']) : null;
        $limit = 20;
        $offset = ($page - 1) * $limit;

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
            // Bons paginés
            $sql = "SELECT b.*, s.nom_station, s.nom_gerant 
                FROM demande_essence b
                LEFT JOIN stations_service s ON b.station_id = s.id
                $whereSql
                ORDER BY b.date_demande DESC
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
    }
}

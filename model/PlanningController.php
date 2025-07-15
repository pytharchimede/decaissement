<?php
// filepath: c:\wamp\www\decaissement\model\PlanningController.php

class PlanningController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Récupérer le planning pour une période (par défaut, tout le mois)
    public function getAll()
    {
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;

        $where = [];
        $params = [];

        if ($dateDebut) {
            $where[] = "date_planning >= :dateDebut";
            $params['dateDebut'] = $dateDebut;
        }
        if ($dateFin) {
            $where[] = "date_planning <= :dateFin";
            $params['dateFin'] = $dateFin;
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT * FROM planning_journalier_logistique $whereSql ORDER BY date_planning ASC, heure ASC";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Regroupement par date (format YYYY-MM-DD)
        $result = [];
        foreach ($rows as $row) {
            $key = $row['date_planning'];
            if (!isset($result[$key])) $result[$key] = [];
            $result[$key][] = [
                'tache' => $row['tache'],
                'responsable' => $row['responsable'],
                'heure' => $row['heure'],
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => $result
        ]);
    }

    // Ajouter une tâche au planning
    public function ajouter()
    {
        $date = $_POST['date_planning'] ?? null;
        $tache = $_POST['tache'] ?? null;
        $responsable = $_POST['responsable'] ?? null;
        $heure = $_POST['heure'] ?? null;

        if (!$date || !$tache || !$responsable || !$heure) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tous les champs sont requis']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO planning_journalier_logistique (date_planning, tache, responsable, heure) VALUES (?, ?, ?, ?)");
            $stmt->execute([$date, $tache, $responsable, $heure]);
            echo json_encode(['status' => 'success', 'message' => 'Tâche ajoutée']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

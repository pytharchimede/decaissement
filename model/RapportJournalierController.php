<?php
// filepath: c:\wamp\www\decaissement\model\RapportJournalierController.php

class RapportJournalierController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Récupérer les rapports pour une période, synchronisés avec le planning
    public function getAll()
    {
        $dateDebut = $_GET['date_debut'] ?? null;
        $dateFin = $_GET['date_fin'] ?? null;

        $where = [];
        $params = [];

        if ($dateDebut) {
            $where[] = "p.date_planning >= :dateDebut";
            $params['dateDebut'] = $dateDebut;
        }
        if ($dateFin) {
            $where[] = "p.date_planning <= :dateFin";
            $params['dateFin'] = $dateFin;
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        // On récupère toutes les lignes de planning, et on joint les rapports s'ils existent
        $sql = "SELECT 
                    p.id AS planning_line_id,
                    p.date_planning,
                    p.tache,
                    p.responsable,
                    p.heure,
                    r.id AS rapport_id,
                    r.etat,
                    r.commentaire
                FROM planning_journalier_logistique p
                LEFT JOIN rapport_journalier_logistique r
                    ON r.planning_line_id = p.id AND r.date_rapport = p.date_planning
                $whereSql
                ORDER BY p.date_planning ASC, p.heure ASC";
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
                'planning_line_id' => $row['planning_line_id'],
                'rapport_id' => $row['rapport_id'],
                'tache' => $row['tache'],
                'responsable' => $row['responsable'],
                'heure' => $row['heure'],
                'etat' => $row['etat'] ?? 'Non démarré',
                'commentaire' => $row['commentaire'] ?? '',
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data' => empty($result) ? (object)[] : $result
        ]);
    }

    // Mettre à jour ou créer l'état/commentaire d'une tâche du rapport
    public function update()
    {
        $planning_line_id = $_POST['planning_line_id'] ?? null;
        $date_rapport = $_POST['date_rapport'] ?? null;
        $etat = $_POST['etat'] ?? null;
        $commentaire = $_POST['commentaire'] ?? null;

        if (!$planning_line_id || !$date_rapport || !$etat) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'planning_line_id, date_rapport et etat requis']);
            return;
        }

        try {
            // Vérifie si un rapport existe déjà pour cette ligne de planning et cette date
            $stmt = $this->pdo->prepare("SELECT id FROM rapport_journalier_logistique WHERE planning_line_id = ? AND date_rapport = ?");
            $stmt->execute([$planning_line_id, $date_rapport]);
            $rapport = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rapport) {
                // Mise à jour
                $stmt = $this->pdo->prepare("UPDATE rapport_journalier_logistique SET etat = ?, commentaire = ? WHERE id = ?");
                $stmt->execute([$etat, $commentaire, $rapport['id']]);
            } else {
                // Création
                $stmt = $this->pdo->prepare("INSERT INTO rapport_journalier_logistique (planning_line_id, date_rapport, etat, commentaire) VALUES (?, ?, ?, ?)");
                $stmt->execute([$planning_line_id, $date_rapport, $etat, $commentaire]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Rapport mis à jour']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

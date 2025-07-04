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
}

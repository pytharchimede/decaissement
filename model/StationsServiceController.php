<?php

require_once 'Database.php';

class StationsServiceController
{
    public function getAll()
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT id, nom_station, nom_gerant, telephone_gerant FROM stations_service ORDER BY nom_station");
            $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $stations]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }
}

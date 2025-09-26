<?php
require_once __DIR__ . '/Database.php';

class DemandeEssenceController
{
    public function getAll()
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM demande_essence WHERE desactive = 0 ORDER BY date_demande DESC");
            $stmt->execute();
            $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['data' => $demandes]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }
}

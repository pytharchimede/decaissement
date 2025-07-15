<?php
// filepath: c:\wamp\www\decaissement\model\DemandesCarburantAttenteController.php

class DemandesCarburantAttenteController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            // Recherche insensible à la casse sur precision_fiche
            $sql = "SELECT * FROM fiche 
                    WHERE approuve = 0 
                    AND LOWER(precision_fiche) LIKE :motclef
                    ORDER BY date_creat_fiche DESC";
            $stmt = $this->pdo->prepare($sql);
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
    }
}
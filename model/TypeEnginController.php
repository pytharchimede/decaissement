<?php
// filepath: c:\wamp\www\decaissement\model\TypeEnginController.php

class TypeEnginController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM type_engin ORDER BY nom ASC");
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'status' => 'success',
                'data' => $types
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

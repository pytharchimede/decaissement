<?php
// filepath: c:\wamp\www\decaissement\model\ChantierController.php

class ChantierController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Liste des chantiers
    public function getAll()
    {
        $sql = "SELECT id_chantier AS id, lib_chantier as libelle FROM chantier ORDER BY id_chantier DESC";
        $stmt = $this->pdo->query($sql);
        $chantiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $chantiers
        ]);
    }
}

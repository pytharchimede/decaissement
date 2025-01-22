<?php

class Operation
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Ajouter une nouvelle opération
    public function createOperation($lib_operation, $societe_operation_id, $secur_ajout_operation, $num_operation, $chantier_id_operation)
    {
        $sql = "INSERT INTO operation (
                    lib_operation, societe_operation_id, date_creat_operation, 
                    secur_ajout_operation, date_mod_operation, secur_mod_operation, 
                    num_operation, chantier_id_operation
                ) 
                VALUES (
                    :lib_operation, :societe_operation_id, NOW(),
                    :secur_ajout_operation, NOW(), :secur_ajout_operation,
                    :num_operation, :chantier_id_operation
                )";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':lib_operation' => $lib_operation,
            ':societe_operation_id' => $societe_operation_id,
            ':secur_ajout_operation' => $secur_ajout_operation,
            ':num_operation' => $num_operation,
            ':chantier_id_operation' => $chantier_id_operation,
        ]);
    }

    // Lire toutes les opérations
    public function getAllOperations()
    {
        $sql = "SELECT * FROM operation";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lire une opération par ID
    public function getOperationById($id_operation)
    {
        $sql = "SELECT * FROM operation WHERE id_operation = :id_operation";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_operation' => $id_operation]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lire une opération par ID de chantier
    public function getOperationByChantierId($chantier_id)
    {
        $sql = "SELECT * FROM operation WHERE chantier_id_operation = :chantier_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':chantier_id' => $chantier_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mettre à jour une opération
    public function updateOperation($id_operation, $lib_operation, $societe_operation_id, $secur_mod_operation, $num_operation, $chantier_id_operation)
    {
        $sql = "UPDATE operation
                SET lib_operation = :lib_operation,
                    societe_operation_id = :societe_operation_id,
                    date_mod_operation = NOW(),
                    secur_mod_operation = :secur_mod_operation,
                    num_operation = :num_operation,
                    chantier_id_operation = :chantier_id_operation
                WHERE id_operation = :id_operation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id_operation' => $id_operation,
            ':lib_operation' => $lib_operation,
            ':societe_operation_id' => $societe_operation_id,
            ':secur_mod_operation' => $secur_mod_operation,
            ':num_operation' => $num_operation,
            ':chantier_id_operation' => $chantier_id_operation,
        ]);
    }

    // Supprimer une opération
    public function deleteOperation($id_operation)
    {
        $sql = "DELETE FROM operation WHERE id_operation = :id_operation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id_operation' => $id_operation]);
    }
}

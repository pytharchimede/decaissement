<?php

class Chantier
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Ajouter un nouveau chantier
    public function createChantier($lib_chantier, $societe_chantier_id, $secur_ajout_chantier, $num_chantier, $cout_total_chantier)
    {
        $sql = "INSERT INTO chantier (lib_chantier, societe_chantier_id, date_creat_chantier, secur_ajout_chantier, date_mod_chantier, secur_mod_chantier, num_chantier, cout_total_chantier)
                VALUES (:lib_chantier, :societe_chantier_id, NOW(), :secur_ajout_chantier, NOW(), :secur_mod_chantier, :num_chantier, :cout_total_chantier)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':lib_chantier' => $lib_chantier,
            ':societe_chantier_id' => $societe_chantier_id,
            ':secur_ajout_chantier' => $secur_ajout_chantier,
            ':secur_mod_chantier' => $secur_ajout_chantier,
            ':num_chantier' => $num_chantier,
            ':cout_total_chantier' => $cout_total_chantier,
        ]);
    }

    // Lire tous les chantiers
    public function getAllChantiers()
    {
        $sql = "SELECT * FROM chantier WHERE num_chantier!='' ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lire un chantier par ID
    public function getChantierById($id_chantier)
    {
        $sql = "SELECT * FROM chantier WHERE id_chantier = :id_chantier";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_chantier' => $id_chantier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour un chantier
    public function updateChantier($id_chantier, $lib_chantier, $societe_chantier_id, $secur_mod_chantier, $num_chantier, $cout_total_chantier)
    {
        $sql = "UPDATE chantier
                SET lib_chantier = :lib_chantier,
                    societe_chantier_id = :societe_chantier_id,
                    date_mod_chantier = NOW(),
                    secur_mod_chantier = :secur_mod_chantier,
                    num_chantier = :num_chantier,
                    cout_total_chantier = :cout_total_chantier
                WHERE id_chantier = :id_chantier";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id_chantier' => $id_chantier,
            ':lib_chantier' => $lib_chantier,
            ':societe_chantier_id' => $societe_chantier_id,
            ':secur_mod_chantier' => $secur_mod_chantier,
            ':num_chantier' => $num_chantier,
            ':cout_total_chantier' => $cout_total_chantier,
        ]);
    }

    // Supprimer un chantier
    public function deleteChantier($id_chantier)
    {
        $sql = "DELETE FROM chantier WHERE id_chantier = :id_chantier";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id_chantier' => $id_chantier]);
    }
}

<?php

class Affectation
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Ajouter une nouvelle affectation
    public function createAffectation($lib_affectation, $num_affectation, $societe_affectation_id, $secur_ajout_affectation)
    {
        $sql = "INSERT INTO affectation (lib_affectation, date_creat_affectation, secur_ajout_affectation, num_affectation, societe_affectation_id, date_mod_affectation, secur_mod_affectation)
                VALUES (:lib_affectation, NOW(), :secur_ajout_affectation, :num_affectation, :societe_affectation_id, NOW(), :secur_mod_affectation)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':lib_affectation' => $lib_affectation,
            ':secur_ajout_affectation' => $secur_ajout_affectation,
            ':num_affectation' => $num_affectation,
            ':societe_affectation_id' => $societe_affectation_id,
            ':secur_mod_affectation' => $secur_ajout_affectation,
        ]);
    }

    // Lire toutes les affectations
    public function getAllAffectations()
    {
        $sql = "SELECT * FROM affectation";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lire une affectation par ID
    public function getAffectationById($id_affectation)
    {
        $sql = "SELECT * FROM affectation WHERE id_affectation = :id_affectation";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_affectation' => $id_affectation]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour une affectation
    public function updateAffectation($id_affectation, $lib_affectation, $num_affectation, $societe_affectation_id, $secur_mod_affectation)
    {
        $sql = "UPDATE affectation
                SET lib_affectation = :lib_affectation,
                    num_affectation = :num_affectation,
                    societe_affectation_id = :societe_affectation_id,
                    date_mod_affectation = NOW(),
                    secur_mod_affectation = :secur_mod_affectation
                WHERE id_affectation = :id_affectation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id_affectation' => $id_affectation,
            ':lib_affectation' => $lib_affectation,
            ':num_affectation' => $num_affectation,
            ':societe_affectation_id' => $societe_affectation_id,
            ':secur_mod_affectation' => $secur_mod_affectation,
        ]);
    }

    // Supprimer une affectation
    public function deleteAffectation($id_affectation)
    {
        $sql = "DELETE FROM affectation WHERE id_affectation = :id_affectation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id_affectation' => $id_affectation]);
    }
}

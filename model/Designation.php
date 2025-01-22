<?php

class Designation
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Ajouter une nouvelle désignation
    public function createDesignation($lib_designation, $societe_designation_id, $secur_ajout_designation, $num_designation, $operation_id_designation, $qte_designation, $prix_designation, $fourniture_debourse, $main_doeuvre_debourse, $montant_debourse)
    {
        $sql = "INSERT INTO designation (
                    lib_designation, societe_designation_id, date_creat_designation, 
                    secur_ajout_designation, date_mod_designation, secur_mod_designation, 
                    num_designation, operation_id_designation, qte_designation, 
                    prix_designation, fourniture_debourse, main_doeuvre_debourse, montant_debourse
                ) 
                VALUES (
                    :lib_designation, :societe_designation_id, NOW(),
                    :secur_ajout_designation, NOW(), :secur_mod_designation,
                    :num_designation, :operation_id_designation, :qte_designation,
                    :prix_designation, :fourniture_debourse, :main_doeuvre_debourse, :montant_debourse
                )";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':lib_designation' => $lib_designation,
            ':societe_designation_id' => $societe_designation_id,
            ':secur_ajout_designation' => $secur_ajout_designation,
            ':secur_mod_designation' => $secur_ajout_designation,
            ':num_designation' => $num_designation,
            ':operation_id_designation' => $operation_id_designation,
            ':qte_designation' => $qte_designation,
            ':prix_designation' => $prix_designation,
            ':fourniture_debourse' => $fourniture_debourse,
            ':main_doeuvre_debourse' => $main_doeuvre_debourse,
            ':montant_debourse' => $montant_debourse,
        ]);
    }

    // Lire toutes les désignations
    public function getAllDesignations()
    {
        $sql = "SELECT * FROM designation";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lire une désignation par ID
    public function getDesignationById($id_designation)
    {
        $sql = "SELECT * FROM designation WHERE id_designation = :id_designation";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_designation' => $id_designation]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lire les désignations par ID de l'operation
    public function getDesignationByOperationId($operation_id)
    {
        $sql = "SELECT * FROM designation WHERE operation_id_designation = :operation_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':operation_id' => $operation_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mettre à jour une désignation
    public function updateDesignation($id_designation, $lib_designation, $societe_designation_id, $secur_mod_designation, $num_designation, $operation_id_designation, $qte_designation, $prix_designation, $fourniture_debourse, $main_doeuvre_debourse, $montant_debourse)
    {
        $sql = "UPDATE designation
                SET lib_designation = :lib_designation,
                    societe_designation_id = :societe_designation_id,
                    date_mod_designation = NOW(),
                    secur_mod_designation = :secur_mod_designation,
                    num_designation = :num_designation,
                    operation_id_designation = :operation_id_designation,
                    qte_designation = :qte_designation,
                    prix_designation = :prix_designation,
                    fourniture_debourse = :fourniture_debourse,
                    main_doeuvre_debourse = :main_doeuvre_debourse,
                    montant_debourse = :montant_debourse
                WHERE id_designation = :id_designation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id_designation' => $id_designation,
            ':lib_designation' => $lib_designation,
            ':societe_designation_id' => $societe_designation_id,
            ':secur_mod_designation' => $secur_mod_designation,
            ':num_designation' => $num_designation,
            ':operation_id_designation' => $operation_id_designation,
            ':qte_designation' => $qte_designation,
            ':prix_designation' => $prix_designation,
            ':fourniture_debourse' => $fourniture_debourse,
            ':main_doeuvre_debourse' => $main_doeuvre_debourse,
            ':montant_debourse' => $montant_debourse,
        ]);
    }

    // Supprimer une désignation
    public function deleteDesignation($id_designation)
    {
        $sql = "DELETE FROM designation WHERE id_designation = :id_designation";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id_designation' => $id_designation]);
    }
}

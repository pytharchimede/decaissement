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

    // Lire tous les chantiers de l'entreprise selectionnée
    public function getAllChantiersByEntreprise($entreprise)
    {
        try {
            // D'abord, vérifions si la colonne 'entreprise' existe
            $checkColumn = "SHOW COLUMNS FROM chantier LIKE 'entreprise'";
            $stmt = $this->pdo->query($checkColumn);
            $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($columnExists) {
                // La colonne entreprise existe
                $sql = "SELECT * FROM chantier WHERE entreprise = :entreprise AND num_chantier != '' ORDER BY num_chantier";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':entreprise', $entreprise, PDO::PARAM_STR);
            } else {
                // La colonne entreprise n'existe pas, essayons avec une autre logique
                // Peut-être que l'entreprise est déterminée par le nom du chantier ou une autre colonne

                // Vérification si lib_chantier contient l'entreprise
                $sql = "SELECT * FROM chantier WHERE lib_chantier LIKE :entreprise AND num_chantier != '' ORDER BY num_chantier";
                $stmt = $this->pdo->prepare($sql);
                $entreprisePattern = '%' . $entreprise . '%';
                $stmt->bindParam(':entreprise', $entreprisePattern, PDO::PARAM_STR);
            }

            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log pour debug
            error_log("SQL Query executed. Results count: " . count($result));
            if (count($result) > 0) {
                error_log("First result: " . json_encode($result[0]));
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Database error in getAllChantiersByEntreprise: " . $e->getMessage());
            return [];
        }
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

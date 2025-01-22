<?php

class Service
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Ajouter un nouveau service
    public function createService($libelle, $securAjout)
    {
        $sql = "INSERT INTO serv_bureau_banamur (lib_serv_bureau_banamur, date_creat_serv_bureau_banamur, secur_ajout_serv_bureau_banamur)
               VALUES (:libelle, NOW(), :securAjout)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':libelle' => $libelle,
            ':securAjout' => $securAjout
        ]);
    }

    // Lire tous les services
    public function getAllServices()
    {
        $sql = "SELECT * FROM serv_bureau_banamur ORDER BY lib_serv_bureau_banamur";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lire un service par ID
    public function getServiceById($id)
    {
        $sql = "SELECT * FROM serv_bureau_banamur WHERE id_serv_bureau_banamur = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mettre à jour un service
    public function updateService($id, $libelle, $securAjout)
    {
        $sql = "UPDATE serv_bureau_banamur
               SET lib_serv_bureau_banamur = :libelle,
                   secur_ajout_serv_bureau_banamur = :securAjout,
                   date_creat_serv_bureau_banamur = NOW()
               WHERE id_serv_bureau_banamur = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':libelle' => $libelle,
            ':securAjout' => $securAjout
        ]);
    }

    // Supprimer un service
    public function deleteService($id)
    {
        $sql = "DELETE FROM serv_bureau_banamur WHERE id_serv_bureau_banamur = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}

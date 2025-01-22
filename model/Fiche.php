<?php
class Fiche
{
    private $pdo;

    // Constructeur pour initialiser la connexion PDO
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Méthode pour insérer une nouvelle fiche
    public function insertFiche($data)
    {
        $query = 'INSERT INTO fiche (
            beficiaire_fiche, 
            montant_fiche, 
            tel_beneficiaire_fiche, 
            date_creat_fiche, 
            num_fiche, 
            affectation_id, 
            designation_fiche, 
            num_piece, 
            chantier_id, 
            precision_fiche, 
            serv_bureau_banamur_id, 
            serv_bureau_fidest_id, 
            serv_rh_id, 
            serv_log_id, 
            code_autorisation_feb
        ) VALUES (
            :beneficiaire, :montant, :telephone, :date_creat, :num_fiche, 
            :affectation, :designation, :num_piece, :chantier, :precision, 
            :serv_banamur, :serv_fidest, :serv_rh, :serv_log, :code_autorisation
        )';

        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            'beneficiaire' => $data['beficiaire_fiche'],
            'montant' => $data['montant_fiche'],
            'telephone' => $data['tel_beneficiaire_fiche'],
            'date_creat' => gmdate('Y-m-d H:i:s'),
            'num_fiche' => $data['num_fiche'],
            'affectation' => $data['affectation_id'],
            'designation' => $data['designation_fiche'],
            'num_piece' => $data['num_piece'],
            'chantier' => $data['chantier_id'],
            'precision' => $data['precision_fiche'],
            'serv_banamur' => $data['serv_bureau_banamur_id'],
            'serv_fidest' => $data['serv_bureau_fidest_id'],
            'serv_rh' => $data['serv_rh_id'],
            'serv_log' => $data['serv_log_id'],
            'code_autorisation' => $data['code_autorisation_feb']
        ]);
    }

    // Méthode pour lire une fiche par son ID
    public function getById($id_fiche)
    {
        $sql = "SELECT * FROM fiche WHERE id_fiche = :id_fiche";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_fiche', $id_fiche, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Méthode pour mettre à jour une fiche
    public function update($id_fiche, $data)
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $sql = "UPDATE fiche SET " . implode(', ', $fields) . " WHERE id_fiche = :id_fiche";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_fiche', $id_fiche, PDO::PARAM_INT);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    }

    // Méthode pour supprimer une fiche
    public function delete($id_fiche)
    {
        $sql = "DELETE FROM fiche WHERE id_fiche = :id_fiche";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_fiche', $id_fiche, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Méthode pour lister toutes les fiches
    public function getAll()
    {
        $sql = "SELECT * FROM fiche ORDER BY date_creat_fiche DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByNumFiche($num_fiche)
    {
        try {
            // Préparation de la requête SQL pour récupérer une fiche par son numéro
            $sql = "SELECT * FROM fiche WHERE num_fiche = :num_fiche";
            $stmt = $this->pdo->prepare($sql);

            // Liaison du paramètre
            $stmt->bindParam(':num_fiche', $num_fiche, PDO::PARAM_STR);

            // Exécution de la requête
            $stmt->execute();

            // Récupération du résultat
            $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification si une fiche a été trouvée
            if ($fiche) {
                return $fiche;
            } else {
                return null; // Retourne null si aucune fiche n'est trouvée
            }
        } catch (Exception $e) {
            // Gestion des erreurs
            return 'Erreur : ' . $e->getMessage();
        }
    }

    public function generateNumFiche()
    {
        // Récupérer le dernier numéro de fiche
        $sql = "SELECT num_fiche FROM fiche ORDER BY id_fiche DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);

        // Vérifier si un numéro existe déjà
        $lastNumFiche = $stmt->fetchColumn();

        if ($lastNumFiche) {
            // Extraire le numéro et l'incrémenter
            $numberPart = (int) filter_var($lastNumFiche, FILTER_SANITIZE_NUMBER_INT);
            $newNumber = $numberPart + 1;

            // Générer le nouveau numéro avec le format requis
            return '0' . str_pad($newNumber, 4, '0', STR_PAD_LEFT); // Exemple : 0001, 0002...
        } else {
            // Aucun numéro précédent, commencer à 0001
            return '0001';
        }
    }
}

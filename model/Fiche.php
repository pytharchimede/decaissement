<?php
class Fiche
{
    private $pdo;

    // Constructeur pour initialiser la connexion PDO
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function insertFiche($data)
    {
        // Vérifier et nettoyer les données pour éviter les erreurs
        $data = array_map('trim', $data); // Supprime les espaces inutiles
        $data['date_creat_fiche'] = gmdate('Y-m-d H:i:s'); // Ajout de la date de création

        // Requête d'insertion SQL
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
            code_autorisation_feb,
            photo_beneficiaire,
            cni_beneficiaire,
            entreprise
        ) VALUES (
            :beneficiaire, :montant, :telephone, :date_creat, :num_fiche, 
            :affectation, :designation, :num_piece, :chantier, :precision, 
            :serv_banamur, :code_autorisation, :photo_beneficiaire,
            :cni_beneficiaire, :entreprise
        )';

        // Préparer la requête
        $stmt = $this->pdo->prepare($query);

        // Exécution avec les valeurs mappées
        return $stmt->execute([
            'beneficiaire' => $data['beficiaire_fiche'],
            'montant' => $data['montant_fiche'],
            'telephone' => $data['tel_beneficiaire_fiche'],
            'date_creat' => $data['date_creat_fiche'],
            'num_fiche' => $data['num_fiche'],
            'affectation' => $data['affectation_id'],
            'designation' => $data['designation_fiche'],
            'num_piece' => $data['num_piece'],
            'chantier' => $data['chantier_id'],
            'precision' => $data['precision_fiche'],
            'serv_banamur' => $data['serv_bureau_banamur_id'],
            'code_autorisation' => $data['code_autorisation_feb'],
            'photo_beneficiaire' => $data['photo_beneficiaire'],
            'cni_beneficiaire' => $data['cni_beneficiaire'],
            'entreprise' => $data['entreprise'],
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

    // Méthode pour lire une fiche par son ID
    public function getByAuthCode($code_autorisation_feb)
    {
        $sql = "SELECT * FROM fiche WHERE code_autorisation_feb = :code_autorisation_feb";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':code_autorisation_feb', $code_autorisation_feb, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function approveFicheByNum($num_fiche, $secur_approuve)
    {
        try {
            // Obtenir la date et l'heure actuelles
            $date_approuve = date('Y-m-d H:i:s'); // Format datetime (YYYY-MM-DD HH:MM:SS)

            // Requête SQL pour mettre à jour les champs approuve, secur_approuve et date_approuve
            $sql = "UPDATE fiche SET approuve = 1, secur_approuve = :secur_approuve, date_approuve = :date_approuve WHERE num_fiche = :num_fiche";

            // Préparation de la requête
            $stmt = $this->pdo->prepare($sql);

            // Liaison des paramètres avec les valeurs correspondantes
            $stmt->bindParam(':num_fiche', $num_fiche, PDO::PARAM_STR);
            $stmt->bindParam(':secur_approuve', $secur_approuve, PDO::PARAM_STR);
            $stmt->bindParam(':date_approuve', $date_approuve, PDO::PARAM_STR);

            // Exécution de la requête
            return $stmt->execute();
        } catch (Exception $e) {
            // Retourner le message d'erreur en cas d'exception
            return 'Erreur : ' . $e->getMessage();
        }
    }


    public function refuseFicheByNum($num_fiche, $secur_desapprouve)
    {
        try {
            // Obtenir la date et l'heure actuelles
            $date_desapprouve = date('Y-m-d H:i:s'); // Format datetime (YYYY-MM-DD HH:MM:SS)

            // Requête SQL pour mettre à jour les champs approuve, secur_desapprouve et date_desapprouve
            $sql = "UPDATE fiche SET approuve = 2, secur_desapprouve = :secur_desapprouve, date_desapprouve = :date_desapprouve WHERE num_fiche = :num_fiche";

            // Préparation de la requête
            $stmt = $this->pdo->prepare($sql);

            // Liaison des paramètres avec les valeurs correspondantes
            $stmt->bindParam(':num_fiche', $num_fiche, PDO::PARAM_STR);
            $stmt->bindParam(':secur_desapprouve', $secur_desapprouve, PDO::PARAM_STR);
            $stmt->bindParam(':date_desapprouve', $date_desapprouve, PDO::PARAM_STR);

            // Exécution de la requête
            return $stmt->execute();
        } catch (Exception $e) {
            // Retourner le message d'erreur en cas d'exception
            return 'Erreur : ' . $e->getMessage();
        }
    }


    /**
     * Certifie conforme une fiche par son numéro.
     * Si le chantier_id = 35, approuve automatiquement la fiche.
     * Ajoute une trace de l'action.
     *
     * @param string $num_fiche
     * @param string $secur_conforme (identifiant de l'utilisateur)
     * @param string $adresse_ip (adresse IP de l'utilisateur)
     * @param int $port (port de l'utilisateur)
     * @return bool
     */
    public function certifierConformeFiche($num_fiche, $secur_conforme, $adresse_ip, $port)
    {
        try {
            $date_trace = gmdate('Y-m-d H:i:s');

            // 1. Mettre à jour conforme
            $sql = "UPDATE fiche SET conforme = 1, secur_conforme = :secur_conforme, date_conforme = :date_conforme WHERE num_fiche = :num_fiche";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'secur_conforme' => $secur_conforme,
                'date_conforme' => $date_trace,
                'num_fiche' => $num_fiche
            ]);

            // 2. Récupérer la fiche et le chantier_id
            $sql = "SELECT * FROM fiche WHERE num_fiche = :num_fiche";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['num_fiche' => $num_fiche]);
            $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

            // 3. Si chantier_id == 35, approuver automatiquement
            if ($fiche && isset($fiche['chantier_id']) && $fiche['chantier_id'] == 35) {
                $sql = "UPDATE fiche SET approuve = 1, secur_approuve = :secur_approuve, date_approuve = :date_approuve WHERE num_fiche = :num_fiche";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'secur_approuve' => $secur_conforme,
                    'date_approuve' => $date_trace,
                    'num_fiche' => $num_fiche
                ]);
                // Trace approbation
                $lib_trace = "Approbation de la fiche N° <b>$num_fiche</b> soumise par <b>{$fiche['beficiaire_fiche']}</b> pour <b>{$fiche['designation_fiche']}</b> <br> ";
                $adresse = "Adresse IP: $adresse_ip Port: $port";
                $this->addTrace($lib_trace, $date_trace, $adresse, $secur_conforme);
            }

            // 4. Trace conformité
            $lib_trace = "Declaree conforme | Fiche N° <b>$num_fiche</b> soumise par <b>{$fiche['beficiaire_fiche']}</b> pour <b>{$fiche['designation_fiche']}</b> <br> ";
            $adresse = "Adresse IP: $adresse_ip Port: $port";
            $this->addTrace($lib_trace, $date_trace, $adresse, $secur_conforme);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ajoute une trace dans la table trace.
     */
    private function addTrace($lib_trace, $date_trace, $adresse, $secur)
    {
        $sql = "INSERT INTO trace (lib_trace, date_trace, adresse_ip, secur) VALUES (:lib_trace, :date_trace, :adresse_ip, :secur)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'lib_trace' => $lib_trace,
            'date_trace' => $date_trace,
            'adresse_ip' => $adresse,
            'secur' => $secur
        ]);
    }

    /**
     * Valide une fiche par son numéro.
     * Met à jour les champs et ajoute une trace.
     *
     * @param string $num_fiche
     * @param string $secur_valid (identifiant de l'utilisateur)
     * @param string $adresse_ip (adresse IP de l'utilisateur)
     * @param int $port (port de l'utilisateur)
     * @return bool
     */
    public function validerFicheByNum($num_fiche, $secur_valid, $adresse_ip, $port)
    {
        try {
            // 1. Mettre à jour la fiche (etat_fiche=1, sauvegarder=0, secur_valid)
            $sql = "UPDATE fiche SET etat_fiche = 1, sauvegarder = 0, secur_valid = :secur_valid WHERE num_fiche = :num_fiche";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'secur_valid' => $secur_valid,
                'num_fiche' => $num_fiche
            ]);

            // 2. Récupérer les infos de la fiche
            $sql = "SELECT * FROM fiche WHERE num_fiche = :num_fiche";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['num_fiche' => $num_fiche]);
            $fiche = $stmt->fetch(PDO::FETCH_ASSOC);

            // 3. Préparer la traçabilité
            $date_trace = gmdate('Y-m-d H:i:s');
            $lib_trace = "Validation de la fiche  N° <b>$num_fiche</b> soumise par <b>{$fiche['beficiaire_fiche']}</b> pour <b>{$fiche['designation_fiche']}</b> <br> ";
            $adresse = "Adresse IP: $adresse_ip Port: $port";

            // 4. Ajouter la trace
            $this->addTrace($lib_trace, $date_trace, $adresse, $secur_valid);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

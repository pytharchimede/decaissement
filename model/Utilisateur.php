<?php
class Utilisateur
{
    private $pdo;

    // Constructeur pour initialiser la connexion PDO
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByPhoneNumber($tel_utilisateur)
    {
        try {
            // Préparation de la requête SQL pour récupérer une fiche par son numéro
            $sql = "SELECT * FROM utilisateur WHERE tel_utilisateur = :tel_utilisateur";
            $stmt = $this->pdo->prepare($sql);

            // Liaison du paramètre
            $stmt->bindParam(':tel_utilisateur', $tel_utilisateur, PDO::PARAM_STR);

            // Exécution de la requête
            $stmt->execute();

            // Récupération du résultat
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification si une fiche a été trouvée
            if ($utilisateur) {
                return $utilisateur;
            } else {
                return null; // Retourne null si aucune fiche n'est trouvée
            }
        } catch (Exception $e) {
            // Gestion des erreurs
            return 'Erreur : ' . $e->getMessage();
        }
    }
}

<?php
class TracabilitePerformance
{
    private $pdo;
    private $ip;
    private $port;
    private $user_agent;
    private $date;
    private $heure;
    private $matricule;
    private $latitude;
    private $longitude;

    // Constructeur avec l'initialisation de la connexion à la base de données
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->port = $_SERVER['REMOTE_PORT'];
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $this->date = date("Y-m-d");
        $this->heure = date("H:i:s");
        $this->matricule = isset($_SESSION['matricule_personnel_tasks']) ? $_SESSION['matricule_personnel_tasks'] : 'Inconnu';
        $this->latitude = null;
        $this->longitude = null;
    }

    // Méthode pour obtenir la géolocalisation à partir de l'IP
    private function obtenirGeolocalisation()
    {
        $geo_api_url = "http://ip-api.com/json/{$this->ip}" ?? '';
        $geolocation_data = file_get_contents($geo_api_url) ?? '127.0.0.1';
        $geo = json_decode($geolocation_data, true);

        if ($geo && $geo['status'] == 'success') {
            $this->latitude = $geo['lat'];
            $this->longitude = $geo['lon'];
        }
    }

    // Méthode pour enregistrer une action dans la base de données
    public function enregistrerAction($libelle)
    {
        try {
            // Appel de la fonction pour obtenir la géolocalisation
            $this->obtenirGeolocalisation();

            // Préparation de la requête SQL
            $sql = "INSERT INTO tracabilite_performance 
                    (ip_tracabilite_performance, libelle_tracabilite, port_tracabilite_performance, user_agent, date_tracabilite, heure_tracabilite, matricule, latitude, longitude)
                    VALUES (:ip, :libelle, :port, :user_agent, :date_tracabilite, :heure_tracabilite, :matricule, :latitude, :longitude)";

            $stmt = $this->pdo->prepare($sql);

            // Liaison des paramètres
            $stmt->bindParam(':ip', $this->ip);
            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':port', $this->port);
            $stmt->bindParam(':user_agent', $this->user_agent);
            $stmt->bindParam(':date_tracabilite', $this->date);
            $stmt->bindParam(':heure_tracabilite', $this->heure);
            $stmt->bindParam(':matricule', $this->matricule);
            $stmt->bindParam(':latitude', $this->latitude);
            $stmt->bindParam(':longitude', $this->longitude);

            // Exécution de la requête
            $stmt->execute();
        } catch (PDOException $e) {
            echo 'Erreur lors de l\'enregistrement de la traçabilité : ' . $e->getMessage();
        }
    }
}

<?php
require_once 'Database.php';

class Helper
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function calculerScore($completedDuration, $assignedDuration)
    {
        if ($assignedDuration == 0) {
            return 0; // Éviter division par zéro si aucune tâche n'est assignée
        }

        // Calculer le pourcentage du temps de travail terminé
        $score = ($completedDuration / $assignedDuration) * 100;

        return round($score, 2); // Arrondi à deux décimales
    }

    public function dateEnFrancais($date_time)
    {
        if (!$date_time) {
            return 'Date non définie'; // Gérer le cas où la date est nulle
        }

        $timestamp = strtotime($date_time);
        $date = new DateTime();
        $date->setTimestamp($timestamp);

        // Si l'extension intl est disponible
        if (class_exists('IntlDateFormatter')) {
            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::SHORT);
            $formatter->setPattern('EEEE d MMMM yyyy à HH:mm');
            return $formatter->format($date);
        }

        // Si intl n'est pas disponible, utiliser un fallback avec traduction manuelle
        $formatter = $date->format('l d F Y à H:i');

        $jours = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];

        $mois = [
            'January' => 'Janvier',
            'February' => 'Février',
            'March' => 'Mars',
            'April' => 'Avril',
            'May' => 'Mai',
            'June' => 'Juin',
            'July' => 'Juillet',
            'August' => 'Août',
            'September' => 'Septembre',
            'October' => 'Octobre',
            'November' => 'Novembre',
            'December' => 'Décembre'
        ];

        // Appliquer les traductions manuelles pour les jours et les mois
        $formatter = str_replace(array_keys($jours), array_values($jours), $formatter);
        return str_replace(array_keys($mois), array_values($mois), $formatter);
    }


    public function dateEnFrancaisSansHeure($date_time)
    {
        if (!$date_time) {
            return 'Date non définie';
        }

        $timestamp = strtotime($date_time);
        $date = new DateTime();
        $date->setTimestamp($timestamp);

        // Si l'extension intl est disponible
        if (class_exists('IntlDateFormatter')) {
            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            $formatter->setPattern('EEEE d MMMM yyyy');
            return $formatter->format($date);
        }

        // Si intl n'est pas disponible, utiliser un fallback
        $formatter = $date->format('l d F Y'); // Formattage simple (non traduit)

        // Traduction manuelle (par exemple)
        $jours = [
            'Monday' => 'Lundi',
            'Tuesday' => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi',
            'Friday' => 'Vendredi',
            'Saturday' => 'Samedi',
            'Sunday' => 'Dimanche'
        ];
        $mois = [
            'January' => 'Janvier',
            'February' => 'Février',
            'March' => 'Mars',
            'April' => 'Avril',
            'May' => 'Mai',
            'June' => 'Juin',
            'July' => 'Juillet',
            'August' => 'Août',
            'September' => 'Septembre',
            'October' => 'Octobre',
            'November' => 'Novembre',
            'December' => 'Décembre'
        ];

        $formatter = str_replace(array_keys($jours), array_values($jours), $formatter);
        return str_replace(array_keys($mois), array_values($mois), $formatter);
    }


    public function checkCodeUsage($code)
    {
        // Vérification de la connexion à la base de données
        if (!$this->conn) {
            error_log("Erreur de connexion à la base de données.");
            return false; // Retourne faux si la connexion échoue
        }

        // Préparation de la requête SQL pour vérifier l'utilisation du code
        $sql = "SELECT COUNT(*) FROM fiche WHERE code_autorisation_feb = :code";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':code', $code);

            // Exécution de la requête
            $stmt->execute();

            // Récupération du nombre de résultats
            $count = $stmt->fetchColumn();

            error_log("Code recherché : " . $code); // Ajout de log pour voir le code recherché
            error_log("Parfait : Count = " . $count);

            // Retourner vrai si le code est déjà utilisé, sinon faux
            return $count > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de l'exécution de la requête : " . $e->getMessage());
            return false; // Retourne faux en cas d'erreur
        }
    }
}

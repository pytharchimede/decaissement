<?php
// Désactiver l'affichage des erreurs pour cette API JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

session_start();

require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/Chantier.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Initialisation de la connexion à la base de données
    $pdo = Database::getConnection();
    $chantierObj = new Chantier($pdo);

    // Vérification si le paramètre 'entreprise' est passé via POST
    if (isset($_POST['entreprise']) && !empty($_POST['entreprise'])) {
        $entreprise = trim($_POST['entreprise']);

        // Log pour debug
        error_log("Entreprise sélectionnée: " . $entreprise);

        // Récupération des chantiers associés à l'entreprise
        $listeChantier = $chantierObj->getAllChantiersByEntreprise($entreprise);

        // Log pour debug
        error_log("Nombre de chantiers trouvés: " . count($listeChantier));

        // Nettoyer et limiter les données pour éviter les problèmes de performance
        $chantiersCleans = array();
        foreach ($listeChantier as $chantier) {
            // Nettoyer les caractères sans utiliser les fonctions dépréciées
            $libChantier = trim($chantier['lib_chantier']);

            // Assurer l'encodage UTF-8 correct
            if (!mb_check_encoding($libChantier, 'UTF-8')) {
                $libChantier = mb_convert_encoding($libChantier, 'UTF-8', 'auto');
            }

            $chantiersCleans[] = array(
                'id_chantier' => (int)$chantier['id_chantier'],
                'num_chantier' => trim($chantier['num_chantier']),
                'lib_chantier' => $libChantier,
                'entreprise' => $chantier['entreprise']
            );
        }

        // Retourner les chantiers au format JSON avec l'encodage UTF-8
        echo json_encode($chantiersCleans, JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // Log pour debug
        error_log("Paramètre entreprise manquant ou vide");

        // Retourner une réponse vide si le paramètre 'entreprise' est manquant
        echo json_encode([]);
        exit;
    }
} catch (Exception $e) {
    // Log de l'erreur
    error_log("Erreur dans charge_chantier.php: " . $e->getMessage());

    // Retourner une erreur en JSON
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    exit;
}

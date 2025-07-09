<?php
require_once 'Database.php';

class CarburantUtilisationController
{
    public function getUtilisation()
    {
        $pdo = Database::getConnection();

        // Total rechargé
        $stmt = $pdo->query("SELECT SUM(montant_rechargement_carburant) as total_recharge FROM rechargement_carburant");
        $totalRecharge = (float)($stmt->fetchColumn() ?: 0);

        // Total consommé (seulement les demandes actives)
        $stmt = $pdo->query("SELECT SUM(montant) as total_consomme FROM demande_essence WHERE desactive = 0");
        $totalConsomme = (float)($stmt->fetchColumn() ?: 0);

        // Calcul du ratio (évite la division par zéro)
        $utilisation = $totalRecharge > 0 ? round($totalConsomme / $totalRecharge, 2) : 0.0;

        echo json_encode(['utilisation' => $utilisation]);
    }
}

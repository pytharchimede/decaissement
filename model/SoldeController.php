<?php
require_once 'Database.php';

class SoldeController
{
    public function getSolde()
    {
        try {
            $pdo = Database::getConnection();

            // Total rechargé (validé)
            $stmt = $pdo->query("SELECT SUM(montant_rechargement_carburant) as total_recharge FROM rechargement_carburant WHERE id_rechargement_carburant != '' ");
            $totalRecharge = $stmt->fetch(PDO::FETCH_ASSOC)['total_recharge'] ?? 0;

            // Total consommé
            $stmt = $pdo->query("SELECT SUM(montant) as total_consomme FROM demande_essence WHERE desactive = 0 AND img_recu_station!=''");
            $totalConsomme = $stmt->fetch(PDO::FETCH_ASSOC)['total_consomme'] ?? 0;

            // Dernier crédit validé
            $stmt = $pdo->query("SELECT montant_rechargement_carburant FROM rechargement_carburant WHERE id_rechargement_carburant != '' ORDER BY date_rechargement_carburant DESC LIMIT 1");
            $dernierCredit = $stmt->fetch(PDO::FETCH_ASSOC)['montant_rechargement_carburant'] ?? 0;

            $solde = floatval($totalRecharge) - floatval($totalConsomme);

            echo json_encode([
                'solde' => round($solde, 2),
                'dernierCredit' => round(floatval($dernierCredit), 2)
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }
}

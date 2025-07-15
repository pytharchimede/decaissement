<?php
// filepath: c:\wamp\www\decaissement\model\SoldeEvolutionController.php

class SoldeEvolutionController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * Retourne l'évolution du solde carburant pour chaque jour du mois courant
     * (Entrées = rechargement_carburant, Sorties = demande_essence)
     */
    public function getEvolution()
    {

        try {

            // Période du mois courant
            $year = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
            $month = isset($_GET['mois']) ? intval($_GET['mois']) : date('n');
            $nbJours = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // Initialisation
            $solde = 0;
            $result = [];

            // Récupérer toutes les entrées (rechargements) groupées par jour
            $sqlIn = "SELECT DAY(date_rechargement_carburant) as jour, SUM(montant_rechargement_carburant) as total_in
                  FROM rechargement_carburant
                  WHERE YEAR(date_rechargement_carburant) = :annee AND MONTH(date_rechargement_carburant) = :mois
                  GROUP BY jour";
            $stmtIn = $this->pdo->prepare($sqlIn);
            $stmtIn->execute(['annee' => $year, 'mois' => $month]);
            $entrees = [];
            foreach ($stmtIn->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $entrees[intval($row['jour'])] = floatval($row['total_in']);
            }

            // Récupérer toutes les sorties (bons) groupées par jour
            $sqlOut = "SELECT DAY(date_demande) as jour, SUM(montant) as total_out
                   FROM demande_essence
                   WHERE desactive = 0 AND YEAR(date_demande) = :annee AND MONTH(date_demande) = :mois
                   GROUP BY jour";
            $stmtOut = $this->pdo->prepare($sqlOut);
            $stmtOut->execute(['annee' => $year, 'mois' => $month]);
            $sorties = [];
            foreach ($stmtOut->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sorties[intval($row['jour'])] = floatval($row['total_out']);
            }

            // Calcul du solde jour par jour
            for ($jour = 1; $jour <= $nbJours; $jour++) {
                $in = $entrees[$jour] ?? 0;
                $out = $sorties[$jour] ?? 0;
                $solde += $in - $out;
                $result[] = [
                    'jour' => $jour,
                    'entree' => $in,
                    'sortie' => $out,
                    'solde' => $solde
                ];
            }

            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}

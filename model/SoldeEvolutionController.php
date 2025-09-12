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
     * Evolution pour graphiques:
     * - Axe X: dates des demande_essence (du mois sélectionné)
     * - Courbe 1 (entree_nette): rechargements du jour − demandes non servies du jour
     * - Courbe 2 (sortie_servie): montants des demandes servies du jour
     */
    public function getEvolution()
    {
        try {
            // Période du mois courant
            $year = isset($_GET['annee']) ? intval($_GET['annee']) : intval(date('Y'));
            $month = isset($_GET['mois']) ? intval($_GET['mois']) : intval(date('n'));

            // 1) Récupère toutes les dates de demande_essence (axe X)
            $sqlDates = "SELECT DATE(date_demande) AS d
                         FROM demande_essence
                         WHERE YEAR(date_demande) = :annee AND MONTH(date_demande) = :mois
                         GROUP BY d
                         ORDER BY d ASC";
            $stmtDates = $this->pdo->prepare($sqlDates);
            $stmtDates->execute(['annee' => $year, 'mois' => $month]);
            $datesDemandes = array_map(function ($r) {
                return $r['d'];
            }, $stmtDates->fetchAll(PDO::FETCH_ASSOC));

            // 2) Rechargements par date (utiliser date effective: si 0000-00-00 alors date_enregistrement)
            $sqlIn = "SELECT
                                                        DATE(CASE
                                                                    WHEN date_rechargement_carburant IS NULL OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                                                    THEN date_enregistrement
                                                                    ELSE date_rechargement_carburant
                                                                END) AS d,
                                                        SUM(montant_rechargement_carburant) AS total_in
                                                FROM rechargement_carburant
                                                WHERE YEAR(CASE
                                                                         WHEN date_rechargement_carburant IS NULL OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                                                         THEN date_enregistrement
                                                                         ELSE date_rechargement_carburant
                                                                     END) = :annee
                                                    AND MONTH(CASE
                                                                         WHEN date_rechargement_carburant IS NULL OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                                                         THEN date_enregistrement
                                                                         ELSE date_rechargement_carburant
                                                                     END) = :mois
                                                GROUP BY d
                                                ORDER BY d ASC";
            $stmtIn = $this->pdo->prepare($sqlIn);
            $stmtIn->execute(['annee' => $year, 'mois' => $month]);
            $inByDate = [];
            $datesRecharge = [];
            foreach ($stmtIn->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $inByDate[$r['d']] = floatval($r['total_in']);
                $datesRecharge[] = $r['d'];
            }

            // 3) Demandes non servies (img_recu_station vide) par date de demande
            $sqlPending = "SELECT DATE(date_demande) AS d, SUM(montant) AS total_pending
                           FROM demande_essence
                           WHERE desactive = 0
                             AND (img_recu_station IS NULL OR TRIM(img_recu_station) = '')
                             AND YEAR(date_demande) = :annee AND MONTH(date_demande) = :mois
                           GROUP BY d";
            $stmtPending = $this->pdo->prepare($sqlPending);
            $stmtPending->execute(['annee' => $year, 'mois' => $month]);
            $pendingByDate = [];
            foreach ($stmtPending->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $pendingByDate[$r['d']] = floatval($r['total_pending']);
            }

            // 4) Demandes servies (img_recu_station renseigné) par date de demande
            $sqlServed = "SELECT DATE(date_demande) AS d, SUM(montant) AS total_served
                          FROM demande_essence
                          WHERE desactive = 0
                            AND img_recu_station IS NOT NULL AND TRIM(img_recu_station) <> ''
                            AND YEAR(date_demande) = :annee AND MONTH(date_demande) = :mois
                          GROUP BY d";
            $stmtServed = $this->pdo->prepare($sqlServed);
            $stmtServed->execute(['annee' => $year, 'mois' => $month]);
            $servedByDate = [];
            foreach ($stmtServed->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $servedByDate[$r['d']] = floatval($r['total_served']);
            }

            // 5) Assemblage des données par date
            // 5) Assemblage des données par date (union des dates demandes + rechargements)
            $allDates = array_values(array_unique(array_merge($datesDemandes, $datesRecharge)));
            sort($allDates);

            if (empty($allDates)) {
                echo json_encode(['status' => 'success', 'data' => []]);
                return;
            }

            $solde = 0.0;
            $result = [];
            foreach ($allDates as $d) {
                $rechargement = $inByDate[$d] ?? 0.0;
                $pending = $pendingByDate[$d] ?? 0.0;
                $entreeNette = $rechargement - $pending; // règle: on retranche les demandes non servies du jour
                $sortieServie = $servedByDate[$d] ?? 0.0;
                // solde évolutif = cumul (entrée nette - sorties servies)
                $solde += ($entreeNette - $sortieServie);
                $result[] = [
                    'date' => $d,
                    'rechargement' => $rechargement,
                    'entree_brute' => $rechargement, // rétrocompatibilité
                    'demande_non_servie' => $pending,
                    'entree_nette' => $entreeNette,
                    'sortie_servie' => $sortieServie,
                    'solde_evolutif' => $solde
                ];
            }

            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}

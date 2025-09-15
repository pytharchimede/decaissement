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
            $year = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
            $month = isset($_GET['mois']) ? (int)$_GET['mois'] : (int)date('n');
            $stationId = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;

            // 1) Dates des demandes (axe X partiel)
            $sqlDates = "SELECT DATE(date_demande) AS d
                         FROM demande_essence
                         WHERE YEAR(date_demande) = :annee
                           AND MONTH(date_demande) = :mois"
                . ($stationId !== null ? " AND station_id = :station_id" : "") .
                " GROUP BY d
                         ORDER BY d ASC";
            $stmtDates = $this->pdo->prepare($sqlDates);
            $params = ['annee' => $year, 'mois' => $month];
            if ($stationId !== null) $params['station_id'] = $stationId;
            $stmtDates->execute($params);
            $datesDemandes = array_map(fn($r) => $r['d'], $stmtDates->fetchAll(PDO::FETCH_ASSOC));

            // 2) Rechargements par date (date effective)
            $sqlIn = "SELECT
                          DATE(
                              CASE
                                  WHEN date_rechargement_carburant IS NULL
                                       OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                  THEN date_enregistrement
                                  ELSE date_rechargement_carburant
                              END
                          ) AS d,
                          SUM(montant_rechargement_carburant) AS total_in
                      FROM rechargement_carburant
                      WHERE YEAR(
                                CASE
                                    WHEN date_rechargement_carburant IS NULL
                                         OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                    THEN date_enregistrement
                                    ELSE date_rechargement_carburant
                                END
                            ) = :annee
                        AND MONTH(
                                CASE
                                    WHEN date_rechargement_carburant IS NULL
                                         OR date_rechargement_carburant = '0000-00-00 00:00:00'
                                    THEN date_enregistrement
                                    ELSE date_rechargement_carburant
                                END
                            ) = :mois"
                . ($stationId !== null ? " AND station_id = :station_id" : "") .
                " GROUP BY d
                        ORDER BY d ASC";
            $stmtIn = $this->pdo->prepare($sqlIn);
            $paramsIn = ['annee' => $year, 'mois' => $month];
            if ($stationId !== null) $paramsIn['station_id'] = $stationId;
            $stmtIn->execute($paramsIn);
            $inByDate = [];
            $datesRecharge = [];
            foreach ($stmtIn->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $dateKey = $r['d'];
                $inByDate[$dateKey] = (float)$r['total_in'];
                $datesRecharge[] = $dateKey;
            }

            // 3) Demandes non servies (img_recu_station vide) par date
            $sqlPending = "SELECT DATE(date_demande) AS d, SUM(montant) AS total_pending
                           FROM demande_essence
                           WHERE desactive = 0
                             AND (img_recu_station IS NULL OR TRIM(img_recu_station) = '')
                             AND YEAR(date_demande) = :annee
                             AND MONTH(date_demande) = :mois"
                . ($stationId !== null ? " AND station_id = :station_id" : "") .
                " GROUP BY d";
            $stmtPending = $this->pdo->prepare($sqlPending);
            $paramsPending = ['annee' => $year, 'mois' => $month];
            if ($stationId !== null) $paramsPending['station_id'] = $stationId;
            $stmtPending->execute($paramsPending);
            $pendingByDate = [];
            foreach ($stmtPending->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $pendingByDate[$r['d']] = (float)$r['total_pending'];
            }

            // 4) Demandes servies (img_recu_station non vide) par date
            $sqlServed = "SELECT DATE(date_demande) AS d, SUM(montant) AS total_served
                          FROM demande_essence
                          WHERE desactive = 0
                            AND img_recu_station IS NOT NULL AND TRIM(img_recu_station) <> ''
                            AND YEAR(date_demande) = :annee
                            AND MONTH(date_demande) = :mois"
                . ($stationId !== null ? " AND station_id = :station_id" : "") .
                " GROUP BY d";
            $stmtServed = $this->pdo->prepare($sqlServed);
            $paramsServed = ['annee' => $year, 'mois' => $month];
            if ($stationId !== null) $paramsServed['station_id'] = $stationId;
            $stmtServed->execute($paramsServed);
            $servedByDate = [];
            foreach ($stmtServed->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $servedByDate[$r['d']] = (float)$r['total_served'];
            }

            // 5) Union des dates (demandes + rechargements), tri croissant
            $allDates = array_values(array_unique(array_merge($datesDemandes, $datesRecharge)));
            sort($allDates);

            if (empty($allDates)) {
                echo json_encode(['status' => 'success', 'data' => []]);
                return;
            }

            // 6) Construction du résultat et solde évolutif
            $solde = 0.0;
            $result = [];
            foreach ($allDates as $d) {
                $rechargement   = $inByDate[$d]       ?? 0.0;
                $pending        = $pendingByDate[$d]  ?? 0.0; // demandes non servies du jour
                $sortieServie   = $servedByDate[$d]   ?? 0.0; // demandes servies du jour
                $entreeNette    = $rechargement - $pending;   // règle demandée

                // solde évolutif (cumul): (entrées nettes − sorties servies)
                $solde += ($entreeNette - $sortieServie);

                $result[] = [
                    'date'               => $d,
                    'rechargement'       => $rechargement,
                    'entree_brute'       => $rechargement,     // pour compatibilité
                    'demande_non_servie' => $pending,
                    'entree_nette'       => $entreeNette,
                    'sortie_servie'      => $sortieServie,
                    'solde_evolutif'     => $solde
                ];
            }

            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

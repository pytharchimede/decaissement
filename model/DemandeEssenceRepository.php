<?php

class DemandeEssenceRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
    }

    /**
     * Retourne toutes les demandes actives (non désactivées)
     */
    public function getAllActive(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM demande_essence WHERE desactive = 0 ORDER BY date_demande DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Désactive toutes les lignes partageant le même code_bon.
     * Optionnellement écrit un motif si la colonne desactivation_motif existe.
     * Retourne le nombre de lignes affectées.
     */
    public function desactiverParCodeBon(string $codeBon, ?string $motif = null): int
    {
        if ($codeBon === '') return 0;
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE demande_essence SET desactive = 1 WHERE code_bon = :cb');
            $stmt->execute([':cb' => $codeBon]);
            $updated = $stmt->rowCount();

            if ($updated > 0 && $motif !== null && $motif !== '') {
                try {
                    $this->pdo->query('SELECT desactivation_motif FROM demande_essence WHERE 1=0');
                    $stmtM = $this->pdo->prepare('UPDATE demande_essence SET desactivation_motif = :m WHERE code_bon = :cb');
                    $stmtM->execute([':m' => $motif, ':cb' => $codeBon]);
                } catch (Throwable $e) {
                    // colonne absente, ignorer
                }
            }
            $this->pdo->commit();
            return $updated;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Résout le code_bon depuis un id de la table.
     */
    public function resolveCodeBonById(int $id): string
    {
        if ($id <= 0) return '';
        $stmt = $this->pdo->prepare('SELECT code_bon FROM demande_essence WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && !empty($row['code_bon']) ? (string)$row['code_bon'] : '';
    }

    /**
     * Recherche pour la gérante station: filtre dynamique, dédup par code_bon, jointure fiche.
     * Filters keys: date_debut, date_fin, demandeur (LIKE), motif (LIKE), num_fiche (exact), code_bon (exact), include_disabled (bool)
     */
    public function searchForStation(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        // Pas de filtre véhicule: la gérante doit voir tous les bons d'essence

        if (empty($filters['include_disabled'])) {
            $conditions[] = "(e.desactive IS NULL OR e.desactive = 0)";
        }
        if (!empty($filters['date_debut'])) {
            $conditions[] = 'e.date_demande >= :date_debut';
            $params[':date_debut'] = (string)$filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $conditions[] = 'e.date_demande <= :date_fin';
            $params[':date_fin'] = (string)$filters['date_fin'];
        }
        if (!empty($filters['demandeur'])) {
            $conditions[] = 'e.nom_beneficiaire LIKE :demandeur';
            $params[':demandeur'] = '%' . (string)$filters['demandeur'] . '%';
        }
        if (!empty($filters['motif'])) {
            $conditions[] = 'e.motif LIKE :motif';
            $params[':motif'] = '%' . (string)$filters['motif'] . '%';
        }
        if (!empty($filters['num_fiche'])) {
            $conditions[] = 'e.num_fiche = :num_fiche';
            $params[':num_fiche'] = (string)$filters['num_fiche'];
        }
        if (!empty($filters['code_bon'])) {
            $conditions[] = 'e.code_bon = :code_bon';
            $params[':code_bon'] = (string)$filters['code_bon'];
        }
        if (!empty($filters['receipt_status'])) {
            if ($filters['receipt_status'] === 'with') {
                $conditions[] = "(e.img_recu_station IS NOT NULL AND e.img_recu_station <> '')";
            } elseif ($filters['receipt_status'] === 'pending') {
                $conditions[] = "(e.img_recu_station IS NULL OR e.img_recu_station = '')";
            }
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        // Dédup stricte comme recap: MIN(id) par code_bon
        $whereSub = $where ? preg_replace('/\be\./', 'd.', $where) : '';
        $sql = "SELECT e.*, f.precision_fiche
                FROM demande_essence e
                JOIN (
                    SELECT d.code_bon, MIN(d.id) AS id
                    FROM demande_essence d
                    $whereSub
                    GROUP BY d.code_bon
                ) u ON u.id = e.id
                LEFT JOIN fiche f ON f.num_fiche = e.num_fiche
                ORDER BY e.date_demande DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Version paginée de searchForStation: applique les mêmes filtres + dédup par code_bon,
     * ordonné par date_demande DESC, puis LIMIT/OFFSET.
     */
    public function searchForStationPaged(array $filters = [], int $offset = 0, int $limit = 50): array
    {
        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        } // garde-fou
        if ($offset < 0) {
            $offset = 0;
        }

        $conditions = [];
        $params = [];

        if (empty($filters['include_disabled'])) {
            $conditions[] = "(e.desactive IS NULL OR e.desactive = 0)";
        }
        if (!empty($filters['date_debut'])) {
            $conditions[] = 'e.date_demande >= :date_debut';
            $params[':date_debut'] = (string)$filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $conditions[] = 'e.date_demande <= :date_fin';
            $params[':date_fin'] = (string)$filters['date_fin'];
        }
        if (!empty($filters['demandeur'])) {
            $conditions[] = 'e.nom_beneficiaire LIKE :demandeur';
            $params[':demandeur'] = '%' . (string)$filters['demandeur'] . '%';
        }
        if (!empty($filters['motif'])) {
            $conditions[] = 'e.motif LIKE :motif';
            $params[':motif'] = '%' . (string)$filters['motif'] . '%';
        }
        if (!empty($filters['num_fiche'])) {
            $conditions[] = 'e.num_fiche = :num_fiche';
            $params[':num_fiche'] = (string)$filters['num_fiche'];
        }
        if (!empty($filters['code_bon'])) {
            $conditions[] = 'e.code_bon = :code_bon';
            $params[':code_bon'] = (string)$filters['code_bon'];
        }
        if (!empty($filters['receipt_status'])) {
            if ($filters['receipt_status'] === 'with') {
                $conditions[] = "(e.img_recu_station IS NOT NULL AND e.img_recu_station <> '')";
            } elseif ($filters['receipt_status'] === 'pending') {
                $conditions[] = "(e.img_recu_station IS NULL OR e.img_recu_station = '')";
            }
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $whereSub = $where ? preg_replace('/\be\./', 'd.', $where) : '';

        $sql = "SELECT e.*, f.precision_fiche
                FROM demande_essence e
                JOIN (
                    SELECT d.code_bon, MIN(d.id) AS id
                    FROM demande_essence d
                    $whereSub
                    GROUP BY d.code_bon
                ) u ON u.id = e.id
                LEFT JOIN fiche f ON f.num_fiche = e.num_fiche
                ORDER BY e.date_demande DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Calcule les statistiques globales pour la vue station avec les filtres appliqués (dédup par code_bon)
     * Retourne: [total_count, total_montant, served_count, pending_count]
     */
    public function statsForStation(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (empty($filters['include_disabled'])) {
            $conditions[] = "(e.desactive IS NULL OR e.desactive = 0)";
        }
        if (!empty($filters['date_debut'])) {
            $conditions[] = 'e.date_demande >= :date_debut';
            $params[':date_debut'] = (string)$filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $conditions[] = 'e.date_demande <= :date_fin';
            $params[':date_fin'] = (string)$filters['date_fin'];
        }
        if (!empty($filters['demandeur'])) {
            $conditions[] = 'e.nom_beneficiaire LIKE :demandeur';
            $params[':demandeur'] = '%' . (string)$filters['demandeur'] . '%';
        }
        if (!empty($filters['motif'])) {
            $conditions[] = 'e.motif LIKE :motif';
            $params[':motif'] = '%' . (string)$filters['motif'] . '%';
        }
        if (!empty($filters['num_fiche'])) {
            $conditions[] = 'e.num_fiche = :num_fiche';
            $params[':num_fiche'] = (string)$filters['num_fiche'];
        }
        if (!empty($filters['code_bon'])) {
            $conditions[] = 'e.code_bon = :code_bon';
            $params[':code_bon'] = (string)$filters['code_bon'];
        }
        if (!empty($filters['receipt_status'])) {
            if ($filters['receipt_status'] === 'with') {
                $conditions[] = "(e.img_recu_station IS NOT NULL AND e.img_recu_station <> '')";
            } elseif ($filters['receipt_status'] === 'pending') {
                $conditions[] = "(e.img_recu_station IS NULL OR e.img_recu_station = '')";
            }
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $whereSub = $where ? preg_replace('/\be\./', 'd.', $where) : '';

        $sql = "SELECT COUNT(*) AS total_count,
                       SUM(COALESCE(e.montant,0)) AS total_montant,
                       SUM(CASE WHEN (e.img_recu_station IS NOT NULL AND e.img_recu_station <> '') THEN 1 ELSE 0 END) AS served_count
                FROM demande_essence e
                JOIN (
                    SELECT d.code_bon, MIN(d.id) AS id
                    FROM demande_essence d
                    $whereSub
                    GROUP BY d.code_bon
                ) u ON u.id = e.id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_count' => 0, 'total_montant' => 0, 'served_count' => 0];
        $total = (int)($row['total_count'] ?? 0);
        $served = (int)($row['served_count'] ?? 0);
        $pending = max(0, $total - $served);
        return [
            'total_count' => $total,
            'total_montant' => (float)($row['total_montant'] ?? 0),
            'served_count' => $served,
            'pending_count' => $pending,
        ];
    }

    /**
     * Met à jour le reçu pour un bon donné (numéro et fichier image)
     */
    public function updateReceiptByCodeBon(string $codeBon, string $numRecu, ?string $imgFileName): bool
    {
        if ($codeBon === '' || $numRecu === '') return false;
        $sql = 'UPDATE demande_essence SET num_recu_station = :nr, img_recu_station = :img WHERE code_bon = :cb';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':nr' => $numRecu, ':img' => $imgFileName, ':cb' => $codeBon]);
    }
}

<?php
// Point Prestataire par liste de matricules
// Permet de renseigner une liste de matricules et d'obtenir un récap (totaux, litres, voyages équiv.)

session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssenceRepository.php';

// Helpers
function norm_matricule(string $m): string
{
    $m = trim($m);
    $m = strtoupper($m);
    $m = preg_replace('/\s+/', '', $m);
    return $m;
}

function extract_matricule(?string $motif, ?string $precision): ?string
{
    $sources = [];
    if (!empty($motif)) $sources[] = (string)$motif;
    if (!empty($precision)) $sources[] = (string)$precision;
    if (empty($sources)) return null;
    $t = implode("\n", $sources);
    if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        return trim($m[1]);
    }
    return null;
}

// Params
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$include_disabled = isset($_GET['include_disabled']) && $_GET['include_disabled'] == '1';
$nom_presta = trim($_GET['nom_presta'] ?? 'Prestataire');

// Liste des matricules: accepter séparateur par virgule, point-virgule, retour ligne, espace
$mat_input = $_GET['matricules'] ?? "9634wwwCI01\n15315wwwci01\n2697wwwci01\n2698wwwCI01";
$mat_lines = preg_split('/[\s,;]+/', $mat_input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$mat_set = [];
foreach ($mat_lines as $m) {
    $mat_set[norm_matricule($m)] = true;
}

$pdo = Database::getConnection();
$repo = new DemandeEssenceRepository($pdo);

// Récupérer dédupliqué par code_bon (même logique que station): on filtre ensuite par matricules extraits
$rows = $repo->searchForStation([
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'include_disabled' => $include_disabled,
]);

// Filtrer par matricules
$filtered = [];
foreach ($rows as $r) {
    $mat = extract_matricule($r['motif'] ?? '', $r['precision_fiche'] ?? '') ?? '';
    if ($mat === '') continue;
    $k = norm_matricule($mat);
    if (isset($mat_set[$k])) {
        $r['__matricule'] = $mat;
        $filtered[] = $r;
    }
}

// Agrégats
$totalCount = count($filtered);
$totalMontant = 0.0;
$basePerTrip = 33750.0; // 50 L pour 33 750 FCFA
$litersPerTrip = 50.0;
$pricePerLiter = $basePerTrip / $litersPerTrip; // 675 FCFA/L
$totalTripsEq = 0.0;
$totalLiters = 0.0;
$servedCount = 0;

// Regroupement par matricule
$byMat = [];
foreach ($filtered as $r) {
    $m = (float)($r['montant'] ?? 0);
    $totalMontant += $m;
    if ($m > 0) {
        $totalTripsEq += ($m / $basePerTrip);
        $totalLiters += ($m / $pricePerLiter);
    }
    $img = isset($r['img_recu_station']) ? trim((string)$r['img_recu_station']) : '';
    if ($img !== '') $servedCount++;

    $mat = $r['__matricule'] ?? '';
    $k = norm_matricule($mat);
    if (!isset($byMat[$k])) {
        $byMat[$k] = [
            'matricule' => $mat,
            'count' => 0,
            'montant' => 0.0,
            'liters' => 0.0,
            'trips' => 0.0,
        ];
    }
    $byMat[$k]['count']++;
    $byMat[$k]['montant'] += $m;
    $byMat[$k]['trips'] += ($m > 0 ? $m / $basePerTrip : 0);
    $byMat[$k]['liters'] += ($m > 0 ? $m / $pricePerLiter : 0);
}
$pendingCount = max(0, $totalCount - $servedCount);

// Export CSV option
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="point_prestataire.csv"');
    $out = fopen('php://output', 'w');
    // BOM UTF-8
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Prestataire', $nom_presta]);
    fputcsv($out, ['Matricules', $mat_input]);
    fputcsv($out, []);
    fputcsv($out, ['Code bon', 'Date', 'Bénéficiaire', 'Matricule', 'Montant (FCFA)', 'N° fiche', 'Reçu']);
    foreach ($filtered as $r) {
        fputcsv($out, [
            (string)($r['code_bon'] ?? ''),
            (string)date('d/m/Y', strtotime($r['date_demande'])),
            (string)($r['nom_beneficiaire'] ?? ''),
            (string)($r['__matricule'] ?? ''),
            (string)number_format((float)($r['montant'] ?? 0), 0, ',', ' '),
            (string)($r['num_fiche'] ?? ''),
            (string)(((isset($r['img_recu_station']) && trim((string)$r['img_recu_station']) !== '') ? 'Oui' : 'Non')),
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Totaux', 'Bons', 'Montant', 'Voyages eq.', 'Litres', 'Reçus', 'En attente']);
    fputcsv($out, [
        '',
        (string)$totalCount,
        (string)number_format($totalMontant, 0, ',', ' '),
        (string)number_format($totalTripsEq, 2, ',', ' '),
        (string)number_format($totalLiters, 2, ',', ' '),
        (string)$servedCount,
        (string)$pendingCount,
    ]);
    fclose($out);
    exit;
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Point Prestataire</title>
    <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <meta name="robots" content="noindex,nofollow">
    <style>
        body {
            padding: 16px
        }

        .table thead th {
            white-space: nowrap
        }

        .badge-soft {
            background: #f1f3f5;
            color: #343a40;
            border: 1px solid #dee2e6;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Point Prestataire</h3>
            <div class="text-muted"><?= htmlspecialchars($nom_presta) ?></div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="get">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Nom prestataire (affichage)</label>
                        <input type="text" class="form-control form-control-sm" name="nom_presta" value="<?= htmlspecialchars($nom_presta) ?>" placeholder="Ex: PRESTATAIRE ABC">
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label">Matricules (séparés par virgule / point-virgule / espaces / retour ligne)</label>
                        <textarea class="form-control form-control-sm" name="matricules" rows="2" placeholder="Saisir la liste des matricules..."><?= htmlspecialchars($mat_input) ?></textarea>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Début</label>
                        <input type="date" class="form-control form-control-sm" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Fin</label>
                        <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="include_disabled" value="1" <?= $include_disabled ? 'checked' : '' ?>>
                            <span class="form-check-label">Inclure les bons désactivés</span>
                        </label>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary btn-sm">Filtrer</button>
                        <a class="btn btn-outline-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export CSV</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Nombre de bons</div>
                        <div class="h5 mb-0"><?= number_format($totalCount, 0, ',', ' ') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Montant total</div>
                        <div class="h5 mb-0"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Voyages équiv.</div>
                        <div class="h5 mb-0"><?= number_format($totalTripsEq, 2, ',', ' ') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Litres estimés</div>
                        <div class="h5 mb-0"><?= number_format($totalLiters, 2, ',', ' ') ?> L</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Par matricule -->
        <div class="card mb-3">
            <div class="card-header">Répartition par matricule</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Matricule</th>
                                <th class="text-end">Bons</th>
                                <th class="text-end">Montant (FCFA)</th>
                                <th class="text-end">Voyages eq.</th>
                                <th class="text-end">Litres</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($byMat)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Aucune donnée</td>
                                </tr>
                                <?php else: foreach ($byMat as $k => $v): ?>
                                    <tr>
                                        <td class="font-monospace"><?= htmlspecialchars($v['matricule']) ?></td>
                                        <td class="text-end"><?= number_format($v['count'], 0, ',', ' ') ?></td>
                                        <td class="text-end"><?= number_format($v['montant'], 0, ',', ' ') ?></td>
                                        <td class="text-end"><?= number_format($v['trips'], 2, ',', ' ') ?></td>
                                        <td class="text-end"><?= number_format($v['liters'], 2, ',', ' ') ?></td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Liste des bons -->
        <div class="card">
            <div class="card-header">Bons filtrés</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N° Bon</th>
                                <th>Date</th>
                                <th>Bénéficiaire</th>
                                <th>Matricule</th>
                                <th class="text-end">Montant</th>
                                <th>N° Fiche</th>
                                <th>Reçu</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filtered)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Aucun bon</td>
                                </tr>
                                <?php else: foreach ($filtered as $r): ?>
                                    <?php $img = isset($r['img_recu_station']) ? trim((string)$r['img_recu_station']) : ''; ?>
                                    <tr>
                                        <td class="font-monospace"><?= htmlspecialchars($r['code_bon'] ?? '') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['date_demande']))) ?></td>
                                        <td><?= htmlspecialchars($r['nom_beneficiaire'] ?? '') ?></td>
                                        <td class="font-monospace"><?= htmlspecialchars($r['__matricule'] ?? '') ?></td>
                                        <td class="text-end"><?= number_format((float)($r['montant'] ?? 0), 0, ',', ' ') ?></td>
                                        <td class="font-monospace"><?= htmlspecialchars($r['num_fiche'] ?? '') ?></td>
                                        <td>
                                            <?php if ($img !== ''): ?>
                                                <span class="badge bg-info text-dark">Reçu joint</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">En attente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-primary btn-sm" target="_blank" href="bon/bon_essence.php?id_bon=<?= urlencode($r['code_bon']) ?>">Voir</a>
                                            <a class="btn btn-outline-warning btn-sm" target="_blank" href="bon/servir_essence.php?id_bon=<?= urlencode($r['code_bon']) ?>">Joindre reçu</a>
                                            <?php if ($img !== ''): ?>
                                                <a class="btn btn-outline-success btn-sm" target="_blank" href="https://fidest.ci/decaissement/uploads/recu_station/<?= htmlspecialchars($img) ?>">Voir reçu</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 text-end text-muted small">
            Prix/L estimé: <?= number_format($pricePerLiter, 0, ',', ' ') ?> FCFA — Base: 50L = <?= number_format($basePerTrip, 0, ',', ' ') ?> FCFA
        </div>

    </div>
</body>

</html>
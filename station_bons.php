<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssenceRepository.php';

// Mot de passe très simple (pas de login). Idéalement le stocker en env.
// Changez la valeur via la variable d'environnement STATION_BONS_PASS en production.
$STATION_PASS = getenv('STATION_BONS_PASS') ?: 'station2025';

// Protection: si non authentifié, afficher formulaire mot de passe
if (!isset($_SESSION['station_ok']) || $_SESSION['station_ok'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pass'])) {
        if (hash('sha256', $_POST['pass']) === hash('sha256', $STATION_PASS)) {
            $_SESSION['station_ok'] = true;
            header('Location: station_bons.php');
            exit;
        } else {
            $err = 'Mot de passe incorrect';
        }
    }
?>
    <!doctype html>
    <html lang="fr">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Station – Connexion</title>
        <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
        <meta name="robots" content="noindex,nofollow">
    </head>

    <body class="p-4">
        <div class="container" style="max-width:420px;">
            <h3 class="mb-3">Accès Station – Bons d'essence</h3>
            <?php if (!empty($err)): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" class="form-control" name="pass" required>
                </div>
                <button class="btn btn-primary w-100">Entrer</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';
$code_bon = $_GET['code_bon'] ?? '';
$receipt_status = $_GET['receipt_status'] ?? '';
$include_disabled = isset($_GET['include_disabled']) && $_GET['include_disabled'] == '1';

$pdo = Database::getConnection();
$repo = new DemandeEssenceRepository($pdo);
$rows = $repo->searchForStation([
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'demandeur' => $demandeur,
    'motif' => $motif,
    'num_fiche' => $num_fiche,
    'code_bon' => $code_bon,
    'include_disabled' => $include_disabled,
    'receipt_status' => $receipt_status,
]);

// Récap global
$totalCount = count($rows);
$totalMontant = 0;
$servedCount = 0; // reçu joint
foreach ($rows as $r) {
    $totalMontant += (float)($r['montant'] ?? 0);
    $img = isset($r['img_recu_station']) ? trim((string)$r['img_recu_station']) : '';
    if ($img !== '') $servedCount++;
}
$pendingCount = max(0, $totalCount - $servedCount);

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Station – Liste des Bons Essence</title>
    <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <meta name="robots" content="noindex,nofollow">
    <style>
        body {
            padding: 16px
        }

        .table thead th {
            white-space: nowrap
        }

        .sticky-actions {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 3;
            padding: 8px 0
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Station – Bons d'essence</h3>
            <div>
                <a class="btn btn-outline-secondary btn-sm" href="?logout=1">Déconnexion</a>
            </div>
        </div>

        <?php if (isset($_GET['logout'])) {
            unset($_SESSION['station_ok']);
            header('Location: station_bons.php');
            exit;
        } ?>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2">
                    <div class="col-6 col-md-2">
                        <label class="form-label">Début</label>
                        <input type="date" class="form-control form-control-sm" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Fin</label>
                        <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Demandeur</label>
                        <input type="text" class="form-control form-control-sm" name="demandeur" value="<?= htmlspecialchars($demandeur) ?>" placeholder="Nom bénéficiaire">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">N° Fiche</label>
                        <input type="text" class="form-control form-control-sm" name="num_fiche" value="<?= htmlspecialchars($num_fiche) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Code bon</label>
                        <input type="text" class="form-control form-control-sm" name="code_bon" value="<?= htmlspecialchars($code_bon) ?>" placeholder="BE-...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut reçu</label>
                        <select class="form-select form-select-sm" name="receipt_status">
                            <option value="">Tous</option>
                            <option value="with" <?= $receipt_status === 'with' ? 'selected' : '' ?>>Avec reçu</option>
                            <option value="pending" <?= $receipt_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="include_disabled" value="1" <?= $include_disabled ? 'checked' : '' ?>>
                            <span class="form-check-label">Inclure les bons désactivés</span>
                        </label>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary btn-sm">Rechercher</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="sticky-actions d-flex gap-2 mb-2">
            <a class="btn btn-outline-success btn-sm" href="export_carburant_excel.php?<?= http_build_query($_GET) ?>">Export Excel</a>
            <a class="btn btn-outline-danger btn-sm" href="export_carburant_pdf.php?<?= http_build_query(array_merge($_GET, ['mode' => 'global'])) ?>">PDF Global</a>
            <a class="btn btn-outline-secondary btn-sm" href="export_carburant_csv.php?<?= http_build_query($_GET) ?>">Export CSV</a>
        </div>

        <!-- Récapitulatif global des résultats -->
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
                        <div class="text-muted small">Reçus joints</div>
                        <div class="h5 mb-0"><?= number_format($servedCount, 0, ',', ' ') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">En attente de reçu</div>
                        <div class="h5 mb-0"><?= number_format($pendingCount, 0, ',', ' ') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste sous forme de cards (meilleur sur mobile) -->
        <div class="row g-3">
            <?php foreach ($rows as $r): ?>
                <?php
                $isActive = (empty($r['desactive']) || $r['desactive'] == 0);
                $img = isset($r['img_recu_station']) ? trim((string)$r['img_recu_station']) : '';
                $hasReceipt = ($img !== '');
                ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small text-muted">N° Bon</div>
                                    <div class="fw-bold font-monospace"><?= htmlspecialchars($r['code_bon']) ?></div>
                                </div>
                                <div>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Désactivé</span>
                                    <?php endif; ?>
                                    <?php if ($hasReceipt): ?>
                                        <span class="badge bg-info text-dark ms-1">Reçu joint</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary ms-1">En attente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row mt-2 small">
                                <div class="col-6 col-md-3"><span class="text-muted">Date:</span> <?= htmlspecialchars(date('d/m/Y', strtotime($r['date_demande']))) ?></div>
                                <div class="col-6 col-md-3"><span class="text-muted">Bénéficiaire:</span> <?= htmlspecialchars($r['nom_beneficiaire']) ?></div>
                                <div class="col-6 col-md-3"><span class="text-muted">Montant:</span> <?= number_format((float)($r['montant'] ?? 0), 0, ',', ' ') ?> FCFA</div>
                                <div class="col-6 col-md-3"><span class="text-muted">N° Fiche:</span> <span class="font-monospace"><?= htmlspecialchars($r['num_fiche']) ?></span></div>
                            </div>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <a class="btn btn-outline-primary btn-sm" target="_blank" href="bon/bon_essence.php?id_bon=<?= urlencode($r['code_bon']) ?>">Voir</a>
                                <a class="btn btn-outline-warning btn-sm" target="_blank" href="bon/servir_essence.php?id_bon=<?= urlencode($r['code_bon']) ?>">Joindre reçu</a>
                                <?php if ($hasReceipt): ?>
                                    <a class="btn btn-outline-success btn-sm" target="_blank" href="<?= htmlspecialchars($img) ?>">Voir reçu</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($rows)): ?>
            <div class="text-center text-muted py-4">Aucun bon trouvé</div>
        <?php endif; ?>
    </div>
</body>

</html>
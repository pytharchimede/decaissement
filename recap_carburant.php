<?php
session_start();

require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssence.php';

$pdo = (new Database())->getConnection();
$demandeEssenceObj = new DemandeEssence($pdo);

// Récupération des filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';

// Construction de la requête dynamique (on joint fiche pour accéder à precision_fiche)
$conditions = [];
$params = [];
$scope = $_GET['scope'] ?? '';

// Filtre spécifique demandé: uniquement la dotation 50 l/j purge
$vehiculeFilter = "Dotation carburant (50 l/j) purge";
$conditions[] = "e.vehicule LIKE :vehiculeFilter";
$params[':vehiculeFilter'] = $vehiculeFilter . '%';

// Si scope=batch, restreindre aux num_fiche du SQL de la page batch
if ($scope === 'batch' && !empty($_SESSION['batch_custom_sql']) && is_string($_SESSION['batch_custom_sql'])) {
    $batchSql = $_SESSION['batch_custom_sql'];
    // Sécurité minimale: doit être un SELECT sur fiche
    $body = trim(rtrim($batchSql, ";\r\n\t "));
    $isSelect = stripos($body, 'select') === 0 && stripos(strtolower($body), ' from fiche') !== false;
    if ($isSelect) {
        try {
            $pdoF = (new Database())->getConnection();
            $stmtF = $pdoF->query($body);
            $ficheRows = $stmtF ? $stmtF->fetchAll(PDO::FETCH_ASSOC) : [];
            $nums = [];
            foreach ($ficheRows as $fr) {
                if (isset($fr['num_fiche'])) {
                    $nums[] = (string)$fr['num_fiche'];
                }
            }
            $nums = array_values(array_unique(array_filter($nums, fn($v) => $v !== '')));
            if (!empty($nums)) {
                $ph = [];
                foreach ($nums as $i => $n) {
                    $k = ":nf$i";
                    $ph[] = $k;
                    $params[$k] = $n;
                }
                $conditions[] = 'e.num_fiche IN (' . implode(',', $ph) . ')';
            } else {
                // Forcer aucune donnée si aucun num_fiche
                $conditions[] = '1=0';
            }
        } catch (Throwable $e) {
            // En cas d'erreur, mieux vaut ne rien filtrer par batch que planter la page
        }
    }
}

if ($date_debut) {
    $conditions[] = "e.date_demande >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if ($date_fin) {
    $conditions[] = "e.date_demande <= :date_fin";
    $params[':date_fin'] = $date_fin;
}
if ($demandeur) {
    $conditions[] = "e.nom_beneficiaire LIKE :demandeur";
    $params[':demandeur'] = "%$demandeur%";
}
if ($motif) {
    $conditions[] = "e.motif LIKE :motif";
    $params[':motif'] = "%$motif%";
}
if ($num_fiche) {
    $conditions[] = "e.num_fiche = :num_fiche";
    $params[':num_fiche'] = $num_fiche;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
// Jointure pour récupérer precision_fiche depuis fiche
$sql = "SELECT e.*, f.precision_fiche 
        FROM demande_essence e 
        LEFT JOIN fiche f ON f.num_fiche = e.num_fiche 
        $where 
        ORDER BY e.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: parsing combiné depuis motif et precision_fiche
function parseFieldsCombined(?string $motif, ?string $precision): array
{
    $res = [
        'nom' => null,
        'matricule' => null,
        'telephone' => null,
        'quantite' => null,
        'frais_route' => null,
        'solde' => null,
        'carburant' => null,
    ];
    $sources = [];
    if ($motif) $sources[] = (string)$motif;
    if ($precision) $sources[] = (string)$precision;
    if (empty($sources)) return $res;
    $t = implode("\n", $sources);
    if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) {
        $res['nom'] = trim($m[1]);
    }
    if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['matricule'] = trim($m[1]);
    }
    if (preg_match('/T[ée]l[ée]?phone\s*:\s*([^\r\n]+)/mi', $t, $m) || preg_match('/Tel\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['telephone'] = trim($m[1]);
    }
    if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)/mi', $t, $m)) {
        $res['quantite'] = (float) str_replace(',', '.', $m[1]);
    }
    if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $res['frais_route'] = (int) preg_replace('/\D+/', '', $m[1]);
    }
    if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $res['solde'] = (int) preg_replace('/\D+/', '', $m[1]);
    }
    if (preg_match('/(Carburant|Type)\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['carburant'] = trim($m[2]);
    }
    return $res;
}

// Backward compat: quantity depuis motif/precision
function parseQtyFromSources(?string $motif, ?string $precision): float
{
    $parts = [];
    if ($motif) $parts[] = (string)$motif;
    if ($precision) $parts[] = (string)$precision;
    if (empty($parts)) return 0.0;
    $t = implode("\n", $parts);
    if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)/mi', $t, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return 0.0;
}

// Calculs agrégés
$totalMontant = 0;
$totalBons = count($demandes);
$totalQuantite = 0.0;
$totalLitresCarburant = $totalBons * 50; // règle métier
$byDayMontant = [];
$byDayQuantite = [];
$byBenefMontant = [];
foreach ($demandes as $d) {
    $m = (float)($d['montant'] ?? 0);
    $q = isset($d['quantite']) && $d['quantite'] !== null && $d['quantite'] !== ''
        ? (float)$d['quantite']
        : parseQtyFromSources($d['motif'] ?? '', $d['precision_fiche'] ?? '');
    $totalMontant += $m;
    $totalQuantite += $q;
    $day = date('Y-m-d', strtotime($d['date_demande']));
    if (!isset($byDayMontant[$day])) $byDayMontant[$day] = 0;
    if (!isset($byDayQuantite[$day])) $byDayQuantite[$day] = 0;
    $byDayMontant[$day] += $m;
    $byDayQuantite[$day] += $q;
    $bn = trim((string)($d['nom_beneficiaire'] ?? 'Inconnu'));
    if (!isset($byBenefMontant[$bn])) $byBenefMontant[$bn] = 0;
    $byBenefMontant[$bn] += $m;
}
ksort($byDayMontant);
ksort($byDayQuantite);
// Top bénéficiaires
arsort($byBenefMontant);
$topBenef = array_slice($byBenefMontant, 0, 7, true);

// Prépare données charts (labels + datasets)
$chartDays = array_keys($byDayMontant);
$chartMontants = array_values($byDayMontant);
$chartQuantites = array_values($byDayQuantite);
$chartBenefLabels = array_keys($topBenef);
$chartBenefMontants = array_values($topBenef);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Récap Carburant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #fff 100%);
        }

        .wow {
            animation: fadeIn 1s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .card {
            box-shadow: 0 2px 12px #facc15aa;
        }

        .btn-action {
            transition: background 0.2s;
        }

        .btn-action:hover {
            background: #fde68a;
        }

        .card-bon {
            border-left: 6px solid #facc15;
        }

        @media (min-width: 640px) {
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
                gap: 1.5rem;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="min-h-screen p-2 sm:p-4 wow">
    <div class="max-w-6xl mx-auto">
        <div class="mb-4">
            <div class="text-2xl font-extrabold text-yellow-600 mb-1">Récap Chantier Dépollution — Transport</div>
            <div class="text-xs text-gray-500">Dotation carburant (50 l/j) purge — uniquement les bons liés aux fiches filtrées</div>
        </div>
        <form method="get" class="flex flex-wrap gap-2 mb-4">
            <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" class="border rounded px-2 py-1 text-xs flex-1" placeholder="Début">
            <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" class="border rounded px-2 py-1 text-xs flex-1" placeholder="Fin">
            <input type="text" name="demandeur" value="<?= htmlspecialchars($demandeur) ?>" class="border rounded px-2 py-1 text-xs flex-1" placeholder="Demandeur">
            <input type="text" name="motif" value="<?= htmlspecialchars($motif) ?>" class="border rounded px-2 py-1 text-xs flex-1" placeholder="Motif">
            <input type="text" name="num_fiche" value="<?= htmlspecialchars($num_fiche) ?>" class="border rounded px-2 py-1 text-xs flex-1" placeholder="N° Fiche">
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-1 rounded font-bold text-xs">Rechercher</button>
        </form>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full">
                <div class="bg-white rounded-xl p-3 shadow border border-yellow-100">
                    <div class="text-xs text-gray-500">Bons</div>
                    <div class="text-xl font-extrabold text-yellow-700"><?= $totalBons ?></div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow border border-yellow-100">
                    <div class="text-xs text-gray-500">Montant total</div>
                    <div class="text-xl font-extrabold text-yellow-700"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow border border-yellow-100">
                    <div class="text-xs text-gray-500">Carburant estimé</div>
                    <div class="text-xl font-extrabold text-yellow-700"><?= number_format($totalLitresCarburant, 0, ',', ' ') ?> L</div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow border border-yellow-100">
                    <div class="text-xs text-gray-500">Terre transportée</div>
                    <div class="text-xl font-extrabold text-yellow-700"><?= rtrim(rtrim(number_format($totalQuantite, 2, ',', ' '), '0'), ',') ?> m³</div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="export_carburant_excel.php?<?= http_build_query($_GET) ?>"
                    class="bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1 rounded text-xs font-bold flex items-center gap-1 shadow">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </a>
                <a href="export_carburant_pdf.php?<?= http_build_query($_GET) ?>"
                    class="bg-red-100 hover:bg-red-200 text-red-800 px-3 py-1 rounded text-xs font-bold flex items-center gap-1 shadow">
                    <i class="fa-solid fa-file-pdf"></i> Export PDF
                </a>
                <a href="export_carburant_csv.php?<?= http_build_query($_GET) ?>"
                    class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded text-xs font-bold flex items-center gap-1 shadow">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Mini-charts -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow md:col-span-2">
                <div class="font-semibold mb-2 text-gray-700">Montants par jour</div>
                <div style="height:160px">
                    <canvas id="chartMontants"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="font-semibold mb-2 text-gray-700">Top bénéficiaires</div>
                <div style="height:160px">
                    <canvas id="chartBenef"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="font-semibold mb-2 text-gray-700">Quantités par jour</div>
                <div style="height:160px">
                    <canvas id="chartQuantites"></canvas>
                </div>
            </div>
        </div>
        <div class="cards-grid">
            <?php foreach ($demandes as $d):
                $bonUrl = "https://fidest.ci/decaissement/bon/bon_essence.php?id_bon=" . urlencode($d['code_bon']);
                $waMsg = rawurlencode("Bonjour, voici votre bon d'essence : $bonUrl");
                $waLink = "https://wa.me/?text=$waMsg";
            ?>
                <div class="card card-bon bg-white rounded-xl p-4 mb-4 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-bold text-yellow-600 text-lg"><?= htmlspecialchars($d['code_bon']) ?></div>
                        <?php if (empty($d['desactive']) || $d['desactive'] == 0): ?>
                            <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 font-bold">Actif</span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 font-bold">Désactivé</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <div class="text-xs text-gray-500">N° Fiche : <span class="font-mono"><?= htmlspecialchars($d['num_fiche']) ?></span></div>
                        <div class="text-xs text-gray-500">Date : <span><?= date('d/m/Y', strtotime($d['date_demande'])) ?></span></div>
                    </div>
                    <div class="mb-2">
                        <div class="font-semibold text-gray-700">Bénéficiaire :</div>
                        <div class="text-gray-900 font-bold"><?= htmlspecialchars($d['nom_beneficiaire']) ?></div>
                    </div>
                    <?php $pf = parseFieldsCombined($d['motif'] ?? '', $d['precision_fiche'] ?? ''); ?>
                    <div class="mb-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <div><span class="text-gray-500">Chauffeur:</span> <span class="font-semibold"><?= htmlspecialchars($pf['nom'] ?? '-') ?></span></div>
                        <div><span class="text-gray-500">Matricule:</span> <span class="font-mono"><?= htmlspecialchars($pf['matricule'] ?? '-') ?></span></div>
                        <div><span class="text-gray-500">Frais route:</span> <span class="font-semibold"><?= isset($pf['frais_route']) ? number_format((int)$pf['frais_route'], 0, ',', ' ') . ' FCFA' : '-' ?></span></div>
                        <div><span class="text-gray-500">Solde:</span> <span class="font-semibold"><?= isset($pf['solde']) ? number_format((int)$pf['solde'], 0, ',', ' ') . ' FCFA' : '-' ?></span></div>
                        <div><span class="text-gray-500">Terre (m³):</span> <span class="font-semibold"><?= rtrim(rtrim(number_format(isset($pf['quantite']) ? (float)$pf['quantite'] : 0, 2, ',', ' '), '0'), ',') ?></span></div>
                    </div>
                    <div class="mb-2">
                        <div class="font-semibold text-gray-700">Motif :</div>
                        <div class="text-gray-800"><?= htmlspecialchars($d['motif']) ?></div>
                    </div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <div class="font-semibold text-gray-700">Montant :</div>
                        <div class="text-yellow-700 font-bold"><?= number_format($d['montant'], 0, ',', ' ') ?> FCFA</div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <a href="<?= $bonUrl ?>" target="_blank"
                            class="btn-action flex-1 bg-yellow-200 px-2 py-2 rounded text-xs font-bold text-yellow-900 shadow hover:underline text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-eye"></i>
                            Visualiser
                        </a>
                        <a href="<?= $waLink ?>" target="_blank"
                            class="btn-action flex-1 bg-green-100 px-2 py-2 rounded text-xs font-bold text-green-700 shadow text-center flex items-center justify-center gap-2">
                            <i class="fa-brands fa-whatsapp"></i>
                            Partager
                        </a>
                        <?php if (empty($d['desactive']) || $d['desactive'] == 0): ?>
                            <form method="post" action="desactiver_bon.php" onsubmit="return confirm('Désactiver ce bon ?');" style="display:inline;flex:1;">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn-action w-full bg-red-100 px-2 py-2 rounded text-xs font-bold text-red-700 shadow flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-ban"></i>
                                    Désactiver
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($demandes)): ?>
                <div class="text-center text-gray-400 py-6">Aucune demande trouvée.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tableau détaillé filtrable -->
    <div class="max-w-6xl mx-auto mt-6 p-4">
        <div class="bg-white rounded-xl p-4 shadow">
            <div class="flex items-center justify-between mb-3">
                <div class="font-semibold text-gray-700">Tableau détaillé</div>
                <input id="tableFilter" type="text" placeholder="Rechercher..." class="border rounded px-2 py-1 text-xs" />
            </div>
            <div class="overflow-x-auto">
                <table id="tbl" class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-600">
                            <th class="px-2 py-1">Code bon</th>
                            <th class="px-2 py-1">N° fiche</th>
                            <th class="px-2 py-1">Date</th>
                            <th class="px-2 py-1">Bénéficiaire</th>
                            <th class="px-2 py-1">Chauffeur</th>
                            <th class="px-2 py-1">Matricule</th>
                            <th class="px-2 py-1">Quantité</th>
                            <th class="px-2 py-1">Frais route</th>
                            <th class="px-2 py-1">Solde</th>
                            <th class="px-2 py-1">Montant</th>
                            <th class="px-2 py-1">Motif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $d): $pf = parseFieldsCombined($d['motif'] ?? '', $d['precision_fiche'] ?? '');
                            $qval = isset($d['quantite']) && $d['quantite'] !== null && $d['quantite'] !== '' ? (float)$d['quantite'] : parseQtyFromSources($d['motif'] ?? '', $d['precision_fiche'] ?? ''); ?>
                            <tr class="border-t">
                                <td class="px-2 py-1 font-mono text-gray-700"><?= htmlspecialchars($d['code_bon']) ?></td>
                                <td class="px-2 py-1 font-mono text-gray-700"><?= htmlspecialchars($d['num_fiche']) ?></td>
                                <td class="px-2 py-1"><?= htmlspecialchars(date('d/m/Y', strtotime($d['date_demande']))) ?></td>
                                <td class="px-2 py-1"><?= htmlspecialchars($d['nom_beneficiaire']) ?></td>
                                <td class="px-2 py-1"><?= htmlspecialchars($pf['nom'] ?? '') ?></td>
                                <td class="px-2 py-1 font-mono"><?= htmlspecialchars($pf['matricule'] ?? '') ?></td>
                                <td class="px-2 py-1 text-right"><?= rtrim(rtrim(number_format($qval, 2, ',', ' '), '0'), ',') ?></td>
                                <td class="px-2 py-1 text-right"><?= isset($pf['frais_route']) ? number_format((int)$pf['frais_route'], 0, ',', ' ') : '' ?></td>
                                <td class="px-2 py-1 text-right"><?= isset($pf['solde']) ? number_format((int)$pf['solde'], 0, ',', ' ') : '' ?></td>
                                <td class="px-2 py-1 text-right"><?= number_format((float)($d['montant'] ?? 0), 0, ',', ' ') ?> FCFA</td>
                                <td class="px-2 py-1"><?= htmlspecialchars($d['motif']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($demandes)): ?>
                    <div class="text-center text-gray-400 py-6">Aucune donnée.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Charts
        const days = <?= json_encode($chartDays) ?>;
        const montants = <?= json_encode($chartMontants) ?>;
        const quantites = <?= json_encode($chartQuantites) ?>;
        const benefLabels = <?= json_encode($chartBenefLabels) ?>;
        const benefMontants = <?= json_encode($chartBenefMontants) ?>;

        function fmtF(x) {
            return (x || 0).toLocaleString('fr-FR');
        }

        function mkGradient(ctx, c1, c2) {
            const g = ctx.createLinearGradient(0, 0, 0, 200);
            g.addColorStop(0, c1);
            g.addColorStop(1, c2);
            return g;
        }

        const cm1 = document.getElementById('chartMontants');
        if (cm1 && days.length) {
            const ctx = cm1.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: days,
                    datasets: [{
                        label: 'Montants (FCFA)',
                        data: montants,
                        borderColor: '#ca8a04',
                        backgroundColor: 'rgba(250, 204, 21, 0.2)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: v => fmtF(v)
                            }
                        }
                    }
                }
            });
        }

        const cm2 = document.getElementById('chartQuantites');
        if (cm2 && days.length) {
            const ctx = cm2.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: days,
                    datasets: [{
                        label: 'Quantités',
                        data: quantites,
                        backgroundColor: '#fde68a',
                        borderColor: '#eab308',
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: v => fmtF(v)
                            }
                        }
                    }
                }
            });
        }

        const cm3 = document.getElementById('chartBenef');
        if (cm3 && benefLabels.length) {
            const ctx = cm3.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: benefLabels,
                    datasets: [{
                        data: benefMontants,
                        backgroundColor: ['#fde68a', '#86efac', '#93c5fd', '#fca5a5', '#ddd6fe', '#67e8f9', '#f9a8d4']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        // Simple client-side filter for the table
        const filterInput = document.getElementById('tableFilter');
        const tbl = document.getElementById('tbl');
        if (filterInput && tbl) {
            filterInput.addEventListener('input', () => {
                const q = filterInput.value.toLowerCase();
                tbl.querySelectorAll('tbody tr').forEach(tr => {
                    const txt = tr.innerText.toLowerCase();
                    tr.style.display = txt.includes(q) ? '' : 'none';
                });
            });
        }
    </script>
</body>

</html>
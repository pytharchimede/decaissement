<?php
// Copie adaptée pour dépollution basée sur depollution_voyage (61 voyages)
// Design strictement conservé depuis la version racine.
session_start();
require_once __DIR__ . '/../../model/Database.php';
$pdo = (new Database())->getConnection();

// Filtre dates optionnel
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$where = [];
$params = [];
if ($date_debut) {
    $where[] = 'v.date_voyage >= :d1';
    $params[':d1'] = $date_debut;
}
if ($date_fin) {
    $where[] = 'v.date_voyage <= :d2';
    $params[':d2'] = $date_fin;
}
// Filtres entités
$prestataire_filter = $_GET['prestataire'] ?? '';
$chauffeur_filter = $_GET['chauffeur'] ?? '';
$camion_filter = $_GET['camion'] ?? '';
if ($prestataire_filter !== '') {
    $where[] = 'p.nom = :prest_nom';
    $params[':prest_nom'] = $prestataire_filter;
}
if ($chauffeur_filter !== '') {
    $where[] = 'ch.nom = :chauff_nom';
    $params[':chauff_nom'] = $chauffeur_filter;
}
if ($camion_filter !== '') {
    $where[] = 'c.matricule = :camion_mat';
    $params[':camion_mat'] = $camion_filter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Récupération voyages (statut SAISI ou CLOS)
$sql = "SELECT v.*, p.nom AS prestataire, c.matricule, ch.nom AS chauffeur, ch.telephone, b.numero AS bon
        FROM depollution_voyage v
        LEFT JOIN depollution_prestataire p ON p.id=v.prestataire_id
        LEFT JOIN depollution_camion c ON c.id=v.camion_id
        LEFT JOIN depollution_chauffeur ch ON ch.id=v.chauffeur_id
        LEFT JOIN depollution_bon_sortie b ON b.id=v.bon_sortie_id
        $whereSql
        ORDER BY v.date_voyage ASC, v.id ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$voyages = $st->fetchAll(PDO::FETCH_ASSOC);

$totalVoyages = count($voyages);
$totalMontantOrigine = 0;
$totalFrais = 0;
$totalCarb = 0;
$totalReel = 0;
$totalLitres = 0;
$totalCubage = 0;
$totalNbVoy = 0;
foreach ($voyages as $v) {
    $totalMontantOrigine += (float)$v['montant_origine'];
    $totalFrais += (float)$v['frais_route'];
    $totalCarb += (float)$v['carburant_montant'];
    $totalReel += (float)$v['reel_recu'];
    $totalLitres += (float)$v['carburant_litre'];
    $totalCubage += (float)$v['cubage'];
    $totalNbVoy += (int)$v['nombre_voyage'];
}
$prixLitre = ($totalLitres > 0 ? ($totalCarb / $totalLitres) : 0);
// Préparation séries pour graphiques + prix moyen par litre + cubage quotidien
$byDate = [];
foreach ($voyages as $v) {
    $d = $v['date_voyage'];
    if (!isset($byDate[$d])) $byDate[$d] = ['montant' => 0, 'carb' => 0, 'reel' => 0, 'voy' => 0, 'litre' => 0, 'cubage' => 0];
    $byDate[$d]['montant'] += (float)$v['montant_origine'];
    $byDate[$d]['carb'] += (float)$v['carburant_montant'];
    $byDate[$d]['reel'] += (float)$v['reel_recu'];
    $byDate[$d]['voy'] += (int)$v['nombre_voyage'];
    $byDate[$d]['litre'] += (float)$v['carburant_litre'];
    $byDate[$d]['cubage'] += (float)$v['cubage'];
}
ksort($byDate);
$labels = array_keys($byDate);
$serieMontant = array_map(fn($d) => $d['montant'], $byDate);
$serieCarb = array_map(fn($d) => $d['carb'], $byDate);
$serieReel = array_map(fn($d) => $d['reel'], $byDate);
$serieVoy = array_map(fn($d) => $d['voy'], $byDate);
$serieLitre = array_map(fn($d) => $d['litre'], $byDate);
$seriePrixLitre = array_map(fn($d) => $d['litre'] > 0 ? round($d['carb'] / $d['litre'], 2) : 0, $byDate);
$serieCubage = array_map(fn($d) => $d['cubage'], $byDate);

// Agrégats par prestataire + Top bénéficiaires (cubage)
$byPrest = [];
foreach ($voyages as $v) {
    $pr = $v['prestataire'] ?: 'N/A';
    if (!isset($byPrest[$pr])) {
        $byPrest[$pr] = [
            'voyages' => 0,
            'nb_saisi' => 0,
            'cubage' => 0,
            'montant' => 0,
            'frais' => 0,
            'carb' => 0,
            'litre' => 0,
            'reel' => 0
        ];
    }
    $byPrest[$pr]['voyages'] += 1;
    $byPrest[$pr]['nb_saisi'] += (int)$v['nombre_voyage'];
    $byPrest[$pr]['cubage'] += (float)$v['cubage'];
    $byPrest[$pr]['montant'] += (float)$v['montant_origine'];
    $byPrest[$pr]['frais'] += (float)$v['frais_route'];
    $byPrest[$pr]['carb'] += (float)$v['carburant_montant'];
    $byPrest[$pr]['litre'] += (float)$v['carburant_litre'];
    $byPrest[$pr]['reel'] += (float)$v['reel_recu'];
}
ksort($byPrest);
$topBenefList = [];
foreach ($byPrest as $name => $vals) {
    $topBenefList[] = ['name' => $name] + $vals;
}
usort($topBenefList, fn($a, $b) => $b['cubage'] <=> $a['cubage']);
$topBenefList = array_slice($topBenefList, 0, 5);
$topBenefLabels = array_column($topBenefList, 'name');
$topBenefCubage = array_map(fn($r) => round($r['cubage'], 2), $topBenefList);
// Objectifs paramétrables
$target_voyages = isset($_GET['target_voyages']) ? (int)$_GET['target_voyages'] : 218; // valeur business
if ($target_voyages <= 0) $target_voyages = 218;
$target_volume = isset($_GET['target_volume']) ? (float)str_replace(',', '.', $_GET['target_volume']) : 0.0; // m3 objectif global
if ($target_volume <= 0) $target_volume = 4182.1; // exemple business
$target_carb = isset($_GET['target_carb']) ? (int)$_GET['target_carb'] : ($totalLitres > 0 && $totalVoyages > 0 ? (int)round(($totalLitres / $totalVoyages) * $target_voyages) : 0);
function _pct($v, $t)
{
    return $t > 0 ? min(100, round($v / $t * 100, 1)) : 0;
}
$pVoy = _pct($totalVoyages, $target_voyages);
$pVol = _pct($totalCubage, $target_volume);
$pCar = _pct($totalCarb, $target_carb);
$remainingVoyages = max(0, $target_voyages - $totalVoyages);
$remainingVolume = max(0, $target_volume - $totalCubage);
$avgVolActuel = $totalVoyages > 0 ? $totalCubage / $totalVoyages : 0;
$avgVolRequis = ($remainingVoyages > 0 && $remainingVolume > 0) ? $remainingVolume / $remainingVoyages : 0;
$avgM3ParVoy = number_format($avgVolActuel, 2, ',', ' ');
$avgM3Requis = number_format($avgVolRequis, 2, ',', ' ');
$estimatedCarbLitres = $totalLitres > 0 && $totalVoyages > 0 ? round(($totalLitres / $totalVoyages) * $target_voyages, 0) : 0;
$m3ParVoyActuel = $avgVolActuel;
$remainingVolumeFmt = number_format($remainingVolume, 1, ',', ' ');
$remainingVoyagesFmt = number_format($remainingVoyages, 0, ',', ' ');
$targetVolumeFmt = number_format($target_volume, 1, ',', ' ');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Récap Carburant Dépollution</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
                opacity: 0
            }

            to {
                opacity: 1
            }
        }

        .card {
            box-shadow: 0 2px 12px #facc15aa;
        }

        .btn-action {
            transition: background .2s;
        }

        .btn-action:hover {
            background: #fde68a;
        }

        .card-bon {
            border-left: 6px solid #facc15;
        }

        table td,
        table th {
            font-size: .65rem;
            padding: 4px 6px;
        }

        table thead th {
            background: #facc15;
            color: #000;
        }

        .stat-box {
            background: #fff;
            border: 1px solid #fde68a;
            border-radius: .75rem;
            padding: .75rem;
        }
    </style>
</head>

<?php
// Filtres supplémentaires (prestataire, chauffeur, camion)
$prestataire_filter = $_GET['prestataire'] ?? '';
$chauffeur_filter = $_GET['chauffeur'] ?? '';
$camion_filter = $_GET['camion'] ?? '';
// Pour les listes déroulantes (on charge toutes les valeurs distinctes)
$prestataires = $pdo->query("SELECT DISTINCT nom FROM depollution_prestataire ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
$chauffeurs = $pdo->query("SELECT DISTINCT nom FROM depollution_chauffeur ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
$camions = $pdo->query("SELECT DISTINCT matricule FROM depollution_camion ORDER BY matricule")->fetchAll(PDO::FETCH_COLUMN);
?>

<body class="min-h-screen p-2 sm:p-4 wow">
    <div class="max-w-6xl mx-auto">
        <div class="mb-4">
            <div class="text-2xl font-extrabold text-yellow-600 mb-1">Récap Carburant — Dépollution</div>
            <div class="text-xs text-gray-500">Basé sur les voyages importés (<?= "$totalVoyages" ?> enregistrements)</div>
        </div>
        <form method="get" class="flex flex-wrap gap-2 mb-4">
            <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" class="border rounded px-2 py-1 text-xs" />
            <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" class="border rounded px-2 py-1 text-xs" />
            <input type="number" name="target_voyages" value="<?= htmlspecialchars($target_voyages) ?>" class="border rounded px-2 py-1 text-xs w-24" placeholder="Obj Voy" />
            <input type="number" step="0.1" name="target_volume" value="<?= htmlspecialchars($target_volume) ?>" class="border rounded px-2 py-1 text-xs w-28" placeholder="Obj Volume m3" />
            <input type="number" name="target_carb" value="<?= htmlspecialchars($target_carb) ?>" class="border rounded px-2 py-1 text-xs w-28" placeholder="Obj Carb (L)" />
            <select name="prestataire" class="border rounded px-2 py-1 text-xs">
                <option value="">Prestataire</option>
                <?php foreach ($prestataires as $pNom): ?>
                    <option value="<?= htmlspecialchars($pNom) ?>" <?= $prestataire_filter === $pNom ? 'selected' : '' ?>><?= htmlspecialchars($pNom) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="chauffeur" class="border rounded px-2 py-1 text-xs">
                <option value="">Chauffeur</option>
                <?php foreach ($chauffeurs as $cNom): ?>
                    <option value="<?= htmlspecialchars($cNom) ?>" <?= $chauffeur_filter === $cNom ? 'selected' : '' ?>><?= htmlspecialchars($cNom) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="camion" class="border rounded px-2 py-1 text-xs">
                <option value="">Camion</option>
                <?php foreach ($camions as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $camion_filter === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-1 rounded font-bold text-xs">Filtrer</button>
            <button type="button" id="btnExportCsv" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded text-xs hover:bg-yellow-50">Export CSV</button>
            <button type="button" id="btnExportXlsx" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded text-xs hover:bg-yellow-50">Export XLSX</button>
            <a href="../../index.php" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded text-xs hover:bg-yellow-100">Retour Admin</a>
            <button type="button" id="btnPrint" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded text-xs hover:bg-yellow-50"><i class="fa fa-print"></i></button>
        </form>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-3 rounded card">
                <div class="text-[10px] text-gray-500 mb-1">Progression Voyages (<?= $totalVoyages ?>/<?= $target_voyages ?>)</div>
                <div class="w-full h-3 bg-gray-200 rounded">
                    <div style="width:<?= $pVoy ?>%" class="h-3 rounded bg-gradient-to-r from-yellow-400 to-yellow-600"></div>
                </div>
                <div class="text-right text-[10px] mt-1 font-semibold text-yellow-700"><?= $pVoy ?>%</div>
            </div>
            <div class="bg-white p-3 rounded card">
                <div class="text-[10px] text-gray-500 mb-1">Progression Volume (<?= number_format($totalCubage, 1, ',', ' ') ?> / <?= $targetVolumeFmt ?> m³)</div>
                <div class="w-full h-3 bg-gray-200 rounded">
                    <div style="width:<?= $pVol ?>%" class="h-3 rounded bg-gradient-to-r from-yellow-400 to-yellow-600"></div>
                </div>
                <div class="text-right text-[10px] mt-1 font-semibold text-yellow-700"><?= $pVol ?>%</div>
            </div>
            <div class="bg-white p-3 rounded card">
                <div class="text-[10px] text-gray-500 mb-1">Progression Carburant (<?= number_format($totalCarb, 0, ',', ' ') ?>/<?= number_format($target_carb, 0, ',', ' ') ?>)</div>
                <div class="w-full h-3 bg-gray-200 rounded">
                    <div style="width:<?= $pCar ?>%" class="h-3 rounded bg-gradient-to-r from-yellow-400 to-yellow-600"></div>
                </div>
                <div class="text-right text-[10px] mt-1 font-semibold text-yellow-700"><?= $pCar ?>%</div>
            </div>
            <div class="bg-white p-3 rounded card text-[10px] leading-4">
                <div class="font-semibold mb-1">Objectifs / Moyennes</div>
                <div>Reste Voy.: <span class="font-bold text-yellow-700"><?= $remainingVoyagesFmt ?></span></div>
                <div>Reste Vol.: <span class="font-bold text-yellow-700"><?= $remainingVolumeFmt ?> m³</span></div>
                <div>Moy. Actuelle: <span class="font-bold text-yellow-700"><?= $avgM3ParVoy ?> m³/v</span></div>
                <div>Moy. Requise: <span class="font-bold text-yellow-700"><?= $avgM3Requis ?> m³/v</span></div>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
            <div class="stat-box">
                <div class="text-xs text-gray-500">Voyages</div>
                <div class="text-lg font-bold text-yellow-700"><?= $totalVoyages ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Nb (saisi)</div>
                <div class="text-lg font-bold text-yellow-700"><?= $totalNbVoy ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Cubage total</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalCubage, 2, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Montant Origine</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalMontantOrigine, 0, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Frais Route</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalFrais, 0, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Carburant</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalCarb, 0, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Litres</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalLitres, 0, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Prix/Litre</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($prixLitre, 0, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Réel Reçu</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($totalReel, 0, ',', ' ') ?></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow card p-4 mb-6">
            <?php if (count($labels) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <h3 class="text-xs font-semibold mb-2">Montants / Carburant / Réel</h3>
                        <canvas id="chartMontant" height="140"></canvas>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold mb-2">Voyages & Litres</h3>
                        <canvas id="chartVoy" height="140"></canvas>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold mb-2">Cubage quotidien</h3>
                        <canvas id="chartCubage" height="140"></canvas>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <h3 class="text-xs font-semibold mb-2">Top bénéficiaires (Cubage)</h3>
                        <canvas id="chartTopBenef" height="140"></canvas>
                    </div>
                    <div class="hidden md:block"></div>
                    <div class="hidden md:block"></div>
                </div>
            <?php endif; ?>
            <div class="text-sm font-semibold mb-2">Liste des voyages</div>
            <div class="overflow-auto" style="max-height:480px;">
                <table id="tableVoyages" class="min-w-full border">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Prestataire</th>
                            <th>Chauffeur</th>
                            <th>Tel</th>
                            <th>Camion</th>
                            <th>Bon</th>
                            <th>Nb</th>
                            <th>Cubage</th>
                            <th>Montant Orig</th>
                            <th>Frais</th>
                            <th>Carb</th>
                            <th>Litre</th>
                            <th>Réel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($voyages as $v): ?>
                            <tr class="border-b">
                                <td><?= htmlspecialchars($v['date_voyage']) ?></td>
                                <td><?= htmlspecialchars($v['prestataire'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['chauffeur'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['matricule'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['bon'] ?? '') ?></td>
                                <td class="text-right"><?= (int)$v['nombre_voyage'] ?></td>
                                <td class="text-right"><?= number_format($v['cubage'], 2, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($v['montant_origine'], 0, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($v['frais_route'], 0, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($v['carburant_montant'], 0, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($v['carburant_litre'], 0, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($v['reel_recu'], 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                <div class="text-sm font-semibold mb-2">Agrégats par prestataire</div>
                <div class="overflow-auto" style="max-height:300px;">
                    <table id="tablePrest" class="min-w-full border">
                        <thead>
                            <tr>
                                <th>Prestataire</th>
                                <th>Voyages</th>
                                <th>Nb (saisi)</th>
                                <th>Cubage</th>
                                <th>Montant Orig</th>
                                <th>Frais</th>
                                <th>Carb</th>
                                <th>Litres</th>
                                <th>Prix/L</th>
                                <th>Réel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byPrest as $pr => $ag): $prixL = $ag['litre'] > 0 ? $ag['carb'] / $ag['litre'] : 0; ?>
                                <tr class="border-b">
                                    <td><?= htmlspecialchars($pr) ?></td>
                                    <td class="text-right"><?= $ag['voyages'] ?></td>
                                    <td class="text-right"><?= $ag['nb_saisi'] ?></td>
                                    <td class="text-right"><?= number_format($ag['cubage'], 2, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['montant'], 0, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['frais'], 0, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['carb'], 0, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['litre'], 0, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($prixL, 0, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['reel'], 0, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    const labels = <?= json_encode($labels) ?>;
    const dataMontant = <?= json_encode($serieMontant) ?>;
    const dataCarb = <?= json_encode($serieCarb) ?>;
    const dataReel = <?= json_encode($serieReel) ?>;
    const dataVoy = <?= json_encode($serieVoy) ?>;
    const dataLit = <?= json_encode($serieLitre) ?>;
    const dataPrix = <?= json_encode($seriePrixLitre) ?>;
    const dataCubage = <?= json_encode($serieCubage) ?>;
    const topBenefLabels = <?= json_encode($topBenefLabels) ?>;
    const topBenefCubage = <?= json_encode($topBenefCubage) ?>;

    function fmt(n) {
        return new Intl.NumberFormat('fr-FR').format(n);
    }
    if (labels.length) {
        const ctxM = document.getElementById('chartMontant').getContext('2d');
        new Chart(ctxM, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                        label: 'Montant Orig.',
                        data: dataMontant,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217,119,6,.15)',
                        tension: .25,
                        fill: true
                    },
                    {
                        label: 'Carburant',
                        data: dataCarb,
                        borderColor: '#15803d',
                        backgroundColor: 'rgba(21,128,61,.15)',
                        tension: .25,
                        fill: true
                    },
                    {
                        label: 'Réel Reçu',
                        data: dataReel,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29,78,216,.15)',
                        tension: .25,
                        fill: true
                    },
                    {
                        label: 'Prix/L (moyen)',
                        data: dataPrix,
                        type: 'line',
                        yAxisID: 'y2',
                        borderColor: '#6d28d9',
                        backgroundColor: 'rgba(109,40,217,.15)',
                        tension: .25,
                        fill: false
                    }
                ]
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: c => c.dataset.label + ': ' + fmt(c.parsed.y)
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: v => fmt(v)
                        }
                    },
                    y2: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: v => fmt(v)
                        }
                    }
                }
            }
        });
        const ctxV = document.getElementById('chartVoy').getContext('2d');
        new Chart(ctxV, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                        label: 'Voyages',
                        data: dataVoy,
                        backgroundColor: '#facc15'
                    },
                    {
                        label: 'Litres',
                        data: dataLit,
                        backgroundColor: '#2563eb'
                    }
                ]
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: c => c.dataset.label + ': ' + fmt(c.parsed.y)
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: v => fmt(v)
                        }
                    }
                }
            }
        });
        const ctxC = document.getElementById('chartCubage').getContext('2d');
        new Chart(ctxC, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Cubage (m³)',
                    data: dataCubage,
                    backgroundColor: '#f59e0b'
                }]
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: v => fmt(v)
                        }
                    }
                }
            }
        });
        const ctxTB = document.getElementById('chartTopBenef').getContext('2d');
        new Chart(ctxTB, {
            type: 'bar',
            data: {
                labels: topBenefLabels,
                datasets: [{
                    label: 'Cubage (m³)',
                    data: topBenefCubage,
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: {
                    legend: {
                        labels: {
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            font: {
                                size: 9
                            },
                            callback: v => fmt(v)
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 9
                            }
                        }
                    }
                }
            }
        });
    }
    document.getElementById('btnExportCsv')?.addEventListener('click', () => {
        const table = document.getElementById('tableVoyages');
        const header = [...table.querySelectorAll('thead th')].map(th => th.innerText.trim());
        const rows = [...table.querySelectorAll('tbody tr')].map(tr => [...tr.children].map(td => td.innerText.trim()));
        const csv = [header.join(';')].concat(rows.map(r => r.map(v => `"${v.replace(/"/g,'""')}"`).join(';'))).join('\n');
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'recap_carburant.csv';
        a.click();
    });
    document.getElementById('btnExportXlsx')?.addEventListener('click', () => {
        const wb = XLSX.utils.book_new();
        // Voyages sheet
        const table1 = document.getElementById('tableVoyages');
        const headers1 = [...table1.querySelectorAll('thead th')].map(th => th.innerText.trim());
        const rows1 = [...table1.querySelectorAll('tbody tr')].map(tr => [...tr.children].map(td => td.innerText.trim()));
        const sheet1 = XLSX.utils.aoa_to_sheet([headers1, ...rows1]);
        XLSX.utils.book_append_sheet(wb, sheet1, 'Voyages');
        // Prestataires sheet
        const table2 = document.getElementById('tablePrest');
        if (table2) {
            const headers2 = [...table2.querySelectorAll('thead th')].map(th => th.innerText.trim());
            const rows2 = [...table2.querySelectorAll('tbody tr')].map(tr => [...tr.children].map(td => td.innerText.trim()));
            const sheet2 = XLSX.utils.aoa_to_sheet([headers2, ...rows2]);
            XLSX.utils.book_append_sheet(wb, sheet2, 'Prestataires');
        }
        XLSX.writeFile(wb, 'recap_carburant.xlsx');
    });
    document.getElementById('btnPrint')?.addEventListener('click', () => window.print());
</script>

</html>
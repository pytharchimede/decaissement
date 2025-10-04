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
$prestataire_filters = isset($_GET['prestataire']) ? (array)$_GET['prestataire'] : [];
$chauffeur_filter = $_GET['chauffeur'] ?? '';
$camion_filter = $_GET['camion'] ?? '';
if ($prestataire_filters) {
    $inParts = [];
    foreach ($prestataire_filters as $i => $val) {
        $ph = ":prest_$i";
        $inParts[] = $ph;
        $params[$ph] = $val;
    }
    $where[] = 'p.nom IN (' . implode(',', $inParts) . ')';
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
$initialLimit = 50;
$initialRows = array_slice($voyages, 0, $initialLimit);

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
// Productivité & ratios globaux
$productiviteGlobal = $totalCubage > 0 ? ($totalLitres / $totalCubage) : 0; // L/m3
$ratioCarbMontant = $totalMontantOrigine > 0 ? ($totalCarb / $totalMontantOrigine * 100) : 0;
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
    <!-- Librairies nécessaires avant initialisation des graphiques et exports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
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

        /* Ajustements Choices.js pour style compact */
        .choices {
            font-size: 10px;
        }

        .choices__inner {
            min-height: 32px;
            padding: 2px 6px;
            border: 1px solid #facc15;
            background: #fff;
        }

        .choices__list--multiple .choices__item {
            background: #facc15;
            border: 1px solid #d4af0d;
            color: #111;
            font-size: 10px;
        }

        .choices__list--dropdown,
        .choices__list[role=listbox] {
            font-size: 11px;
        }

        .choices__placeholder {
            opacity: .7;
        }

        .choices[data-type*=select-one] .choices__button {
            display: none;
        }

        .choices__list--dropdown .choices__item--selectable.is-highlighted {
            background: #fde68a;
            color: #111;
        }
    </style>
</head>

<body class="min-h-screen p-2 sm:p-4 wow">

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

    <div class="max-w-6xl mx-auto">
        <div class="mb-4">
            <div class="text-2xl font-extrabold text-yellow-600 mb-1">Récap Carburant — Dépollution</div>
            <div class="text-xs text-gray-500">Basé sur les voyages importés (<?= "$totalVoyages" ?> enregistrements)</div>
        </div>
        <div class="filter-card mb-3 border border-yellow-400 rounded shadow-sm bg-white p-3">
            <form method="get" id="filtersForm" class="space-y-3">
                <!-- Ligne 1 -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Date début</label>
                        <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" class="border rounded px-2 py-1 text-xs w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Date fin</label>
                        <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" class="border rounded px-2 py-1 text-xs w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Obj. Voyages</label>
                        <input type="number" name="target_voyages" value="<?= htmlspecialchars($target_voyages) ?>" class="border rounded px-2 py-1 text-xs w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Obj. Volume (m³)</label>
                        <input type="number" step="0.1" name="target_volume" value="<?= htmlspecialchars($target_volume) ?>" class="border rounded px-2 py-1 text-xs w-full" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Obj. Carb (L)</label>
                        <input type="number" name="target_carb" value="<?= htmlspecialchars($target_carb) ?>" class="border rounded px-2 py-1 text-xs w-full" />
                    </div>
                    <div class="flex gap-2 md:justify-end">
                        <button class="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-1 rounded font-bold text-xs h-8 self-end">Filtrer</button>
                        <button type="button" id="btnResetFilters" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded text-xs h-8 self-end hover:bg-yellow-50">Reset</button>
                    </div>
                </div>
                <!-- Ligne 2 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Prestataires</label>
                        <select name="prestataire[]" multiple class="border rounded px-2 py-1 text-xs w-full" title="Prestataires">
                            <?php foreach ($prestataires as $pNom): $sel = in_array($pNom, $prestataire_filters) ? 'selected' : ''; ?>
                                <option value="<?= htmlspecialchars($pNom) ?>" <?= $sel ?>><?= htmlspecialchars($pNom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Chauffeur</label>
                        <select name="chauffeur" class="border rounded px-2 py-1 text-xs w-full">
                            <option value="">Tous</option>
                            <?php foreach ($chauffeurs as $cNom): ?>
                                <option value="<?= htmlspecialchars($cNom) ?>" <?= $chauffeur_filter === $cNom ? 'selected' : '' ?>><?= htmlspecialchars($cNom) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold mb-1">Camion</label>
                        <select name="camion" class="border rounded px-2 py-1 text-xs w-full">
                            <option value="">Tous</option>
                            <?php foreach ($camions as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= $camion_filter === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="export-bar flex flex-wrap items-center gap-2 mb-5 border border-yellow-400 bg-white rounded px-3 py-2 text-xs shadow-sm">
            <span class="font-semibold text-yellow-700 mr-2">Exports :</span>
            <button type="button" id="btnExportCsv" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-50">CSV</button>
            <button type="button" id="btnExportXlsx" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-50">XLSX</button>
            <a id="btnExportPdf" href="#" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-50" title="Export PDF (avec graphiques)">PDF</a>
            <button type="button" id="btnPrint" class="bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-50"><i class="fa fa-print"></i></button>
            <a href="../../index.php" class="ml-auto bg-white border border-yellow-400 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-100">Retour Admin</a>
        </div>
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
        <div class="grid grid-cols-2 md:grid-cols-8 gap-3 mb-6">
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
            <div class="stat-box">
                <div class="text-xs text-gray-500">Prod. L/m³</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($productiviteGlobal, 2, ',', ' ') ?></div>
            </div>
            <div class="stat-box">
                <div class="text-xs text-gray-500">Carb/Mont (%)</div>
                <div class="text-lg font-bold text-yellow-700"><?= number_format($ratioCarbMontant, 1, ',', ' ') ?></div>
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
                    <tbody id="voyagesBody" data-total="<?= $totalVoyages ?>" data-initial="<?= count($initialRows) ?>">
                        <?php foreach ($initialRows as $v): ?>
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
                                <th>L/m³</th>
                                <th>%Carb/Mont</th>
                                <th>Réel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($byPrest as $pr => $ag): $prixL = $ag['litre'] > 0 ? $ag['carb'] / $ag['litre'] : 0;
                                $lm3 = $ag['cubage'] > 0 ? ($ag['litre'] / $ag['cubage']) : 0;
                                $ratioPct = $ag['montant'] > 0 ? ($ag['carb'] / $ag['montant'] * 100) : 0; ?>
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
                                    <td class="text-right"><?= number_format($lm3, 2, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ratioPct, 1, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($ag['reel'], 0, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        // Initialisation des sélecteurs modernes (Choices.js) + logique page
        document.addEventListener('DOMContentLoaded', function() {
            const prestSel = document.querySelector('select[name="prestataire[]"]');
            if (prestSel) {
                new Choices(prestSel, {
                    removeItemButton: true,
                    placeholder: true,
                    placeholderValue: 'Prestataire(s)',
                    searchPlaceholderValue: 'Rechercher…',
                    shouldSort: true,
                    allowHTML: false,
                    duplicateItemsAllowed: false
                });
            }
            const chSel = document.querySelector('select[name="chauffeur"]');
            if (chSel) {
                new Choices(chSel, {
                    placeholder: true,
                    placeholderValue: 'Chauffeur',
                    searchEnabled: true,
                    searchPlaceholderValue: 'Rechercher chauffeur…',
                    allowHTML: false,
                    shouldSort: true
                });
            }
            const camSel = document.querySelector('select[name="camion"]');
            if (camSel) {
                new Choices(camSel, {
                    placeholder: true,
                    placeholderValue: 'Camion',
                    searchEnabled: true,
                    searchPlaceholderValue: 'Rechercher camion…',
                    allowHTML: false,
                    shouldSort: true
                });
            }
        });
        // Export PDF avec intégration des graphiques (fallback local si util.js absent)
        function exportPdfWithCharts(endpoint, baseQuery) {
            try {
                if (!window.Chart) {
                    window.open(endpoint + '?' + baseQuery, '_blank');
                    return;
                }
                const out = {};
                if (Chart.instances && typeof Chart.instances.forEach === 'function') {
                    Chart.instances.forEach(inst => {
                        try {
                            out[inst.canvas.id] = inst.toBase64Image('image/png', 1);
                        } catch (e) {}
                    });
                }
                const fd = new FormData();
                fd.append('charts_json', JSON.stringify(out));
                fd.append('filters', baseQuery);
                fetch(endpoint, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => {
                        if (!r.ok) throw new Error(r.status);
                        return r.blob();
                    })
                    .then(blob => {
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'recap_voyages.pdf';
                        a.click();
                        setTimeout(() => URL.revokeObjectURL(url), 3000);
                    })
                    .catch(() => window.open(endpoint + '?' + baseQuery, '_blank'));
            } catch (e) {
                window.open(endpoint + '?' + baseQuery, '_blank');
            }
        }
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
        const initialLoaded = <?= count($initialRows) ?>;
        const totalVoyages = <?= $totalVoyages ?>;
        let offsetVoy = initialLoaded;
        const pageSize = 50;
        let loading = false;

        function buildQueryBase() {
            const form = document.querySelector('form');
            const params = new URLSearchParams();
            [...form.elements].forEach(el => {
                if (!el.name || el.disabled) return;
                if (el.tagName === 'SELECT' && el.multiple) {
                    [...el.options].forEach(o => {
                        if (o.selected) params.append(el.name.replace('[]', ''), o.value);
                    });
                } else if (el.type !== 'button' && el.type !== 'submit') {
                    if (el.value !== '') params.append(el.name.replace('[]', ''), el.value);
                }
            });
            return params.toString();
        }
        const baseQuery = buildQueryBase();

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
        document.getElementById('btnExportPdf')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.DecapUtil && DecapUtil.exportPdfWithCharts) {
                DecapUtil.exportPdfWithCharts('export_pdf_voyages.php');
            } else {
                window.open('export_pdf_voyages.php?' + baseQuery, '_blank');
            }
        });
        const scrollContainer = document.querySelector('#tableVoyages').parentElement;
        async function loadMore() {
            if (loading) return;
            if (offsetVoy >= totalVoyages) return;
            loading = true;
            try {
                const resp = await fetch('voyages_api.php?' + baseQuery + '&offset=' + offsetVoy + '&limit=' + pageSize);
                if (!resp.ok) return;
                const data = await resp.json();
                const tbody = document.getElementById('voyagesBody');
                data.rows.forEach(v => {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b';
                    const nfInt = n => new Intl.NumberFormat('fr-FR').format(n);
                    tr.innerHTML = `<td>${v.date_voyage||''}</td>
                <td>${v.prestataire||''}</td>
                <td>${v.chauffeur||''}</td>
                <td>${v.telephone||''}</td>
                <td>${v.matricule||''}</td>
                <td>${v.bon||''}</td>
                <td class='text-right'>${nfInt(v.nombre_voyage||0)}</td>
                <td class='text-right'>${nfInt(parseFloat(v.cubage).toFixed(2))}</td>
                <td class='text-right'>${nfInt(v.montant_origine||0)}</td>
                <td class='text-right'>${nfInt(v.frais_route||0)}</td>
                <td class='text-right'>${nfInt(v.carburant_montant||0)}</td>
                <td class='text-right'>${nfInt(v.carburant_litre||0)}</td>
                <td class='text-right'>${nfInt(v.reel_recu||0)}</td>`;
                    tbody.appendChild(tr);
                });
                offsetVoy += data.rows.length;
            } finally {
                loading = false;
            }
        }
        scrollContainer.addEventListener('scroll', () => {
            if (scrollContainer.scrollTop + scrollContainer.clientHeight + 60 >= scrollContainer.scrollHeight) {
                loadMore();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</body>

</html>
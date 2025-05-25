<?php

require_once 'model/Database.php';
require_once 'model/DemandeEssence.php';

$pdo = (new Database())->getConnection();
$demandeEssenceObj = new DemandeEssence($pdo);

// Récupération des filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';

// Construction de la requête dynamique
$conditions = [];
$params = [];

if ($date_debut) {
    $conditions[] = "date_demande >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if ($date_fin) {
    $conditions[] = "date_demande <= :date_fin";
    $params[':date_fin'] = $date_fin;
}
if ($demandeur) {
    $conditions[] = "nom_beneficiaire LIKE :demandeur";
    $params[':demandeur'] = "%$demandeur%";
}
if ($motif) {
    $conditions[] = "motif LIKE :motif";
    $params[':motif'] = "%$motif%";
}
if ($num_fiche) {
    $conditions[] = "num_fiche = :num_fiche";
    $params[':num_fiche'] = $num_fiche;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql = "SELECT * FROM demande_essence $where ORDER BY date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du total montant
$totalMontant = 0;
foreach ($demandes as $d) {
    $totalMontant += floatval($d['montant']);
}
$totalBons = count($demandes);
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
</head>

<body class="min-h-screen p-2 sm:p-4 wow">
    <div class="max-w-3xl mx-auto">
        <div class="mb-4">
            <div class="text-2xl font-extrabold text-yellow-600 mb-1">Récapitulatif Carburant</div>
            <div class="text-xs text-gray-500">Filtrez vos demandes et consultez vos bons</div>
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
            <div>
                <span class="font-bold text-yellow-700"><?= $totalBons ?></span> bon(s) trouvé(s)
                <span class="mx-2">|</span>
                <span class="font-bold text-yellow-700"><?= number_format($totalMontant, 0, ',', ' ') ?> FCFA</span> au total
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
            </div>
        </div>
        <div class="cards-grid">
            <?php foreach ($demandes as $d):
                $bonUrl = "https://fidest.ci/decaissement/bon/bon_essence.php?id_bon=" . urlencode($d['num_fiche']);
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
</body>

</html>
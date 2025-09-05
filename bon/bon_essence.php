<?php
require_once '../model/Database.php';
require_once '../model/Fiche.php';
require_once '../model/DemandeEssence.php';
require_once '../../phpqrcode/qrlib.php';

$pdo = (new Database())->getConnection();
$ficheObj = new Fiche($pdo);
$demandeObj = new DemandeEssence($pdo);

$id = $_GET['id_bon'] ?? null;

if (!$id) {
    echo "Numéro de bon manquant.";
    exit;
}

$demandeEssence = $demandeObj->getByCodeBon($id);
if (!$id) {
    echo "Bon inexistant.";
    exit;
}

$num_fiche = $demandeEssence['num_fiche'];

// Récupération de la fiche via la classe
$fiche = $ficheObj->getByNumFiche($num_fiche);

if (!$fiche) {
    echo "Bon introuvable.";
    exit;
}

// Préparation des données pour l'affichage
$demande = [
    'nom_beneficiaire' => $fiche['beficiaire_fiche'],
    'montant' => $fiche['montant_fiche'],
    'date_demande' => $fiche['date_creat_fiche'],
    'motif' => $fiche['designation_fiche'],
    'dg_nom' => 'M. Alex Braud'
];
$id = $fiche['num_fiche'];

// Génération du QR code dans le dossier local /bon/qr/
$qrDir = __DIR__ . '/qr/';
if (!is_dir($qrDir)) {
    mkdir($qrDir, 0777, true);
}
if (!is_writable($qrDir)) {
    die("Erreur : le dossier $qrDir n'est pas accessible en écriture.");
}
$qrFileName = 'qr_' . $id . '_' . time() . '.png';
$qrFile = $qrDir . $qrFileName;
$qrData = "https://fidest.ci/decaissement/bon/bon_essence.php?id_bon=" . urlencode($id);

// Vérifie que la librairie QRcode est bien incluse
if (!class_exists('QRcode')) {
    die('Erreur : la librairie QRcode n\'est pas chargée.');
}

// Génération du QR code
QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 4);

// Vérification de la création
if (!file_exists($qrFile)) {
    die("Erreur : le fichier QR code n'a pas été créé ($qrFile).");
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Bon d'essence</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }

            body {
                background: #fff !important;
            }

            .shadow-lg,
            .shadow {
                box-shadow: none !important;
            }
        }

        .bon-bg {
            background: linear-gradient(135deg, #f0f4ff 0%, #fff 100%);
        }

        .qr-border {
            border: 2px dashed #6366f1;
            border-radius: 12px;
            padding: 8px;
            background: #f8fafc;
        }
    </style>
</head>

<body class="bon-bg p-4">
    <div class="max-w-lg mx-auto border-2 border-yellow-400 rounded-2xl p-4 shadow-lg bg-white relative">
        <!-- Entête horizontal -->
        <div class="flex items-center mb-3" style="min-height:70px;">
            <img src="../../logo/default_logo.jpg" alt="Logo" class="h-16 w-auto mr-3">
            <div class="flex-1 text-xs text-gray-700 leading-tight">
                <div class="font-semibold text-yellow-600">BANAMUR INDUSTRIES</div>
                <div>S.A.R.L au Capital de 100 000 000 FCFA</div>
                <div>Abidjan, Koumassi Bd. du Gabon prolongé – 01 BP 1642 Abidjan 01</div>
                <div>RCCM : CI-ABJ-03-2022-B13-02828 – Tél. : +225 27 21 36 27 27</div>
            </div>
        </div>
        <!-- Titre et numéro -->
        <div class="flex justify-between items-center mb-2">
            <div class="font-extrabold text-lg tracking-wider text-yellow-600">BON D'ESSENCE</div>
            <?php
            // Génération du numéro de bon codifié
            $date = new DateTime($demande['date_demande']);
            $code_bon = 'BE-' . $date->format('ym') . '-' . substr(str_pad($id, 5, '0', STR_PAD_LEFT), -5);
            ?>
            <div class="text-xs text-gray-400">N° <?= $code_bon ?></div>
        </div>
        <!-- Infos du bon -->
        <div class="mb-3">
            <div class="mb-1"><span class="font-semibold text-gray-700">Bénéficiaire :</span>
                <span class="text-gray-900 font-bold"><?= htmlspecialchars($demande['nom_beneficiaire']) ?></span>
            </div>
            <div class="mb-1"><span class="font-semibold text-gray-700">Montant :</span>
                <span class="text-yellow-700 font-bold"><?= number_format($demande['montant'], 0, ',', ' ') ?> FCFA</span>
            </div>
            <div class="mb-1"><span class="font-semibold text-gray-700">Date :</span>
                <span class="text-gray-800"><?= date('d/m/Y', strtotime($demande['date_demande'])) ?></span>
            </div>
            <div class="mb-1"><span class="font-semibold text-gray-700">Motif :</span>
                <span class="text-gray-800"><?= htmlspecialchars($demande['motif']) ?></span>
            </div>
        </div>
        <!-- Signature et QR -->
        <div class="flex justify-between items-end mt-2 relative" style="z-index:2;">
            <div class="text-center">
                <img src="../../signatures/sign_braud.jpg"
                    alt="Signature DG"
                    class="h-48 mb-1 mx-auto opacity-60 pointer-events-none select-none"
                    style="z-index:0; filter: drop-shadow(0 2px 6px #8882); max-width:100%;">
                <div class="font-semibold text-gray-700"><?= $demande['dg_nom'] ?? 'Directeur Général' ?></div>
                <div class="text-xs text-gray-500">Directeur Général</div>
            </div>
            <div class="qr-border flex flex-col items-center border-yellow-400" style="border-color:#FFD600;">
                <img src="qr/<?= htmlspecialchars($qrFileName) ?>" alt="QR Code" class="h-28 w-28 mb-1">
                <div class="text-xs text-gray-500 text-center">Vérification QR</div>
            </div>
        </div>
        <div class="mt-3 text-center text-gray-400 text-xs italic">
            Ce bon doit être présenté à la station-service partenaire.<br>
            <span class="text-gray-300">Imprimé le <?= date('d/m/Y à H:i') ?></span>
        </div>
        <button onclick="window.print()" class="no-print mt-3 mx-auto block bg-yellow-400 hover:bg-yellow-500 text-black px-8 py-2 rounded-lg font-bold shadow text-base tracking-wide">
            Imprimer le bon
        </button>
    </div>
</body>

</html>
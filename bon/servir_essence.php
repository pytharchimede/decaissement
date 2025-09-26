<?php
require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/Fiche.php';
require_once __DIR__ . '/../model/DemandeEssence.php';
require_once __DIR__ . '/../model/WhatsAppSMS.php';

$pdo = (new Database())->getConnection();
$ficheObj = new Fiche($pdo);
$demandeObj = new DemandeEssence($pdo);

$code_bon = $_GET['id_bon'] ?? null; // id_bon == code_bon

if (!$code_bon) {
    echo "Numéro de bon manquant.";
    exit;
}

$demandeEssence = $demandeObj->getByCodeBon($code_bon);
if (!$demandeEssence) {
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

// Traitement du formulaire (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num_recu = trim($_POST['num_recu'] ?? '');
    $img_name_saved = $demandeEssence['img_recu_station'] ?? null;

    // Validation basique
    if ($num_recu === '') {
        $error = "Le numéro de reçu est requis.";
    }

    // Gestion upload
    if (!isset($error) && isset($_FILES['img_recu']) && $_FILES['img_recu']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/recu_station/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $tmp = $_FILES['img_recu']['tmp_name'];
        $orig = basename($_FILES['img_recu']['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) {
            $error = "Format d'image non supporté.";
        } else {
            $img_name_saved = 'recu_' . preg_replace('/[^A-Za-z0-9_-]/', '', $code_bon) . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $img_name_saved;
            if (!move_uploaded_file($tmp, $dest)) {
                $error = "Échec de l'upload de l'image du reçu.";
            }
        }
    }

    if (!isset($error)) {
        // Update BDD
        $demandeObj->updateRecuByCodeBon($code_bon, $num_recu, $img_name_saved);

        // Envoi WhatsApp confirmation à la station (ou au même numéro que l’initiant) – adapter le numéro cible si besoin
        $sid = "ACded19f6cd55b2ba3d18c13f438f1e878";
        $token = "7f1136b112e6d8cb4a6af94223d0872e";
        $from = "whatsapp:+2250711048002";
        $wh = new WhatsAppSMS($sid, $token, $from);

        // Déterminer un numéro destinataire: par défaut le demandeur (station)
        $to = "+2250544577666";
        $benef = $fiche['beficiaire_fiche'];
        $montant = (string)$fiche['montant_fiche'];
        $date_str = (new DateTime($fiche['date_creat_fiche']))->format('d/m/Y');
        $wh->sendConfirmServirCarburant($to, $code_bon, $benef, $num_recu, $montant, $date_str);

        // Redirection simple
        header('Location: bon_essence.php?id_bon=' . urlencode($code_bon));
        exit;
    }
}

// Préparation des données pour l'affichage
$demande = [
    'nom_beneficiaire' => $fiche['beficiaire_fiche'],
    'montant' => $fiche['montant_fiche'],
    'date_demande' => $fiche['date_creat_fiche'],
    'motif' => $fiche['designation_fiche'],
    'dg_nom' => 'M. Alex Braud'
];

// UI mobile simple (numéro reçu + upload)
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servir carburant</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 16px;
        }

        .card {
            max-width: 480px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
        }

        .row {
            margin-bottom: 12px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 6px;
            color: #333;
        }

        input[type=text],
        input[type=file] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .meta {
            font-size: 14px;
            color: #555;
        }

        .error {
            color: #b00020;
            margin-bottom: 10px;
        }
    </style>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="data:,">
    <meta http-equiv="Cache-Control" content="no-store" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0d6efd">
</head>

<body>
    <div class="card">
        <h2 style="margin-top:0;">Servir le carburant</h2>
        <div class="meta">Bon: <strong><?= htmlspecialchars($code_bon) ?></strong></div>
        <div class="meta">Bénéficiaire: <strong><?= htmlspecialchars($demande['nom_beneficiaire']) ?></strong></div>
        <div class="meta">Montant: <strong><?= number_format((float)$demande['montant'], 0, ',', ' ') ?> FCFA</strong></div>
        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <label for="num_recu">Numéro du reçu</label>
                <input type="text" id="num_recu" name="num_recu" inputmode="numeric" placeholder="Ex: 001" required>
            </div>
            <div class="row">
                <label for="img_recu">Photo du reçu (JPG/PNG/WEBP/GIF)</label>
                <input type="file" id="img_recu" name="img_recu" accept="image/*" capture="environment" required>
            </div>
            <button type="submit">Confirmer le service</button>
        </form>
    </div>
</body>

</html>
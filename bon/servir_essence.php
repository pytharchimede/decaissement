<?php
require_once __DIR__ . '/../model/Database.php';
require_once __DIR__ . '/../model/Fiche.php';
require_once __DIR__ . '/../model/DemandeEssence.php';
require_once __DIR__ . '/../model/WhatsAppSMS.php';
require_once __DIR__ . '/../model/Config.php';
require_once __DIR__ . '/../model/OcrReceiptAnalyzer.php';

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

// Vérifier si un reçu est déjà enregistré pour ce numéro de bon (toutes lignes confondues)
$existingRecu = null;
$uploadedDest = null; // suivra le chemin du fichier uploadé pour nettoyage si nécessaire
try {
    $stmtChk = $pdo->prepare("SELECT num_recu, img_recu_station, date_demande FROM demande_essence WHERE code_bon = :cb AND img_recu_station IS NOT NULL AND TRIM(img_recu_station) <> '' ORDER BY id ASC LIMIT 1");
    $stmtChk->execute([':cb' => $code_bon]);
    $row = $stmtChk->fetch(PDO::FETCH_ASSOC);
    if ($row && trim((string)$row['img_recu_station']) !== '') {
        $existingRecu = $row; // ['num_recu' => ..., 'img_recu_station' => ..., 'date_demande' => ...]
    }
} catch (Throwable $e) {
    // Ne pas bloquer la page si l'inspection échoue
}

// Traitement du formulaire (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si un reçu existe déjà pour ce code_bon, bloquer toute nouvelle tentative d'upload
    if ($existingRecu) {
        $error = "Un reçu est déjà enregistré pour ce numéro de bon. L'upload est bloqué.";
    }
    $num_recu = trim($_POST['num_recu'] ?? '');
    $img_name_saved = $demandeEssence['img_recu_station'] ?? null;

    // Validation basique
    if ($num_recu === '') {
        $error = "Le numéro de reçu est requis.";
    }

    // Unicité du numéro de reçu dans le SI (ne peut être utilisé que pour un seul bon)
    if (!isset($error) && $num_recu !== '') {
        try {
            // Tente de vérifier sur les deux colonnes possibles (num_recu et num_recu_station)
            // et exclure explicitement le bon courant pour éviter les faux négatifs avec LIMIT 1
            $stmtDup = $pdo->prepare("SELECT code_bon FROM demande_essence WHERE (num_recu = :nr OR num_recu_station = :nr) AND code_bon <> :cb LIMIT 1");
            $stmtDup->execute([':nr' => $num_recu, ':cb' => $code_bon]);
        } catch (Throwable $e) {
            // Si la colonne num_recu_station n'existe pas, se rabattre sur num_recu uniquement
            try {
                $stmtDup = $pdo->prepare("SELECT code_bon FROM demande_essence WHERE num_recu = :nr AND code_bon <> :cb LIMIT 1");
                $stmtDup->execute([':nr' => $num_recu, ':cb' => $code_bon]);
            } catch (Throwable $e2) {
                $stmtDup = null; // ne pas bloquer si l'inspection échoue
            }
        }
        if ($stmtDup) {
            $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
            if ($dup && isset($dup['code_bon'])) {
                $dupCode = (string)$dup['code_bon'];
                // Trouvé pour un autre bon que celui en cours: bloquer
                $error = "Ce numéro de reçu est déjà utilisé pour le bon " . htmlspecialchars($dupCode) . ".";
            }
        }
    }

    // Exiger la photo du reçu côté serveur si aucun reçu n'est déjà enregistré
    if (!isset($error) && !$existingRecu) {
        if (!isset($_FILES['img_recu']) || !is_array($_FILES['img_recu']) || $_FILES['img_recu']['error'] !== UPLOAD_ERR_OK) {
            $error = "La photo du reçu est requise.";
        }
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
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        // Taille max 5 Mo
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['img_recu']['size'] > $maxSize) {
            $error = "L'image dépasse la taille maximale autorisée (5 Mo).";
        }

        // Vérification extension
        if (!isset($error) && !in_array($ext, $allowedExt, true)) {
            $error = "Format d'image non supporté.";
        }

        // Vérification MIME si possible
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!isset($error) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                if ($mime === false || !in_array($mime, $allowedMime, true)) {
                    $error = "Le type de fichier n'est pas valide (JPEG/PNG/WEBP/GIF uniquement).";
                }
            }
        }

        // Vérification de conformité du document (modèle VINKO)
        if (!isset($error)) {
            $verify = OcrReceiptAnalyzer::verifyDocument('vinko_receipt', $tmp);
            if (!($verify['ok'] ?? false)) {
                $msg = 'Reçu non conforme au modèle VINKO.';
                if (!empty($verify['errors'])) {
                    $msg .= ' Détails: ' . implode('; ', (array)$verify['errors']);
                }
                $error = $msg;
            }
        }

        if (!isset($error)) {
            $safeBon = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$code_bon);
            $img_name_saved = 'recu_' . $safeBon . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $img_name_saved;
            if (!@move_uploaded_file($tmp, $dest)) {
                $error = "Échec de l'upload de l'image du reçu.";
            } else {
                $uploadedDest = $dest;
            }
        }
    }

    if (!isset($error)) {
        // Update BDD
        $ok = $demandeObj->updateRecuByCodeBon($code_bon, $num_recu, $img_name_saved);
        if (!$ok) {
            // rollback fichier si uploadé
            if ($uploadedDest && is_file($uploadedDest)) {
                @unlink($uploadedDest);
            }
            $error = "Une erreur est survenue lors de l'enregistrement du reçu. Veuillez réessayer.";
        } else {
            // Envoi WhatsApp confirmation à la station (ou au même numéro que l’initiant)
            $sid = AppConfig::twilioSid();
            $token = AppConfig::twilioToken();
            $from = AppConfig::whatsappFrom();
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
}

// Préparation des données pour l'affichage
$demande = [
    'nom_beneficiaire' => $fiche['beficiaire_fiche'],
    'montant' => $fiche['montant_fiche'],
    'date_demande' => $fiche['date_creat_fiche'],
    'motif' => $fiche['designation_fiche'],
    'dg_nom' => 'M. Alex Braud'
];
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servir carburant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg: #0a0f0c;
            --bg-card: #0f1713;
            --bg-soft: #0c1410;
            --text: #d4f7df;
            --muted: #93b89e;
            --accent: #00ff88;
            --accent-2: #00cc6a;
            --danger: #ff4d4f;
            --danger-soft: rgba(255, 77, 79, .15);
            --ring: rgba(0, 255, 136, .35);
            --border: #123a2a;
            --shadow: 0 8px 30px rgba(0, 255, 136, .08);
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial;
            background: radial-gradient(1200px 600px at 50% -10%, rgba(0, 255, 136, .13), rgba(0, 0, 0, 0)), var(--bg);
            color: var(--text);
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .container {
            width: 100%;
            max-width: 520px
        }

        .card {
            background: linear-gradient(180deg, rgba(0, 255, 136, .03), rgba(0, 0, 0, 0)), var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            box-shadow: var(--shadow)
        }

        .title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: .3px
        }

        .title .spark {
            color: var(--accent);
            text-shadow: 0 0 8px rgba(0, 255, 136, .6)
        }

        .subtitle {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px
        }

        .pill {
            display: inline-block;
            border: 1px solid var(--border);
            background: var(--bg-soft);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            color: var(--muted)
        }

        .stack {
            display: grid;
            gap: 8px;
            margin-top: 12px
        }

        .meta {
            font-size: 13px;
            color: var(--muted)
        }

        .alert {
            display: flex;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--danger);
            background: var(--danger-soft);
            color: #ffd6d6;
            padding: 10px 12px;
            border-radius: 12px;
            margin-top: 12px
        }

        .alert .ic {
            color: var(--danger)
        }

        .field {
            margin-top: 14px
        }

        label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px
        }

        .input {
            width: 100%;
            background: var(--bg-soft);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px 14px;
            border-radius: 12px;
            outline: none;
            transition: box-shadow .2s, border-color .2s, transform .05s
        }

        .input:focus {
            border-color: var(--accent-2);
            box-shadow: 0 0 0 6px var(--ring)
        }

        .dropzone {
            border: 1.5px dashed var(--accent-2);
            background: rgba(0, 255, 136, .05);
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            color: var(--muted);
            transition: border-color .2s, background .2s, transform .1s;
            position: relative
        }

        .dropzone:hover {
            border-color: var(--accent);
            background: rgba(0, 255, 136, .08)
        }

        .dropzone.dragover {
            border-color: var(--accent);
            background: rgba(0, 255, 136, .12);
            transform: scale(.997)
        }

        .dropzone .dz-icon {
            font-size: 28px;
            color: var(--accent);
            margin-bottom: 6px;
            text-shadow: 0 0 10px rgba(0, 255, 136, .5)
        }

        .dropzone input[type=file] {
            display: none
        }

        .dz-preview {
            margin-top: 10px;
            display: none
        }

        .dz-preview img {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border)
        }

        .dz-filename {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
            word-break: break-all
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--accent-2);
            background: linear-gradient(90deg, rgba(0, 255, 136, .12), rgba(0, 255, 136, .06));
            color: var(--text);
            border-radius: 12px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
            box-shadow: 0 8px 18px rgba(0, 255, 136, .12);
            transition: transform .06s, box-shadow .2s, background .2s
        }

        .btn:hover {
            background: linear-gradient(90deg, rgba(0, 255, 136, .2), rgba(0, 255, 136, .08));
            box-shadow: 0 10px 24px rgba(0, 255, 136, .18)
        }

        .btn:active {
            transform: translateY(1px)
        }

        .btn[disabled] {
            opacity: .6;
            cursor: not-allowed
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap
        }

        .topbar .lhs {
            display: grid;
            gap: 2px
        }

        .topbar .rhs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap
        }

        .view-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600
        }

        .view-link:hover {
            text-decoration: underline
        }

        @media (max-width:380px) {
            .title {
                font-size: 18px
            }

            .btn {
                padding: 12px
            }
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
    <meta name="theme-color" content="#0a0f0c">
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="topbar">
                <div class="lhs">
                    <div class="title"><i class="fa-solid fa-gas-pump spark"></i> Servir le carburant</div>
                    <div class="subtitle">Mode station — Scanner et joindre le reçu</div>
                </div>
                <div class="rhs">
                    <span class="pill">Bon <strong style="color:var(--text)">#<?= htmlspecialchars($code_bon) ?></strong></span>
                </div>
            </div>

            <div class="stack">
                <div class="meta">Bénéficiaire: <strong style="color:var(--text)"><?= htmlspecialchars($demande['nom_beneficiaire']) ?></strong></div>
                <div class="meta">Montant: <strong style="color:var(--text)"><?= number_format((float)$demande['montant'], 0, ',', ' ') ?> FCFA</strong></div>
            </div>

            <?php if ($existingRecu): ?>
                <div class="alert" role="alert">
                    <i class="fa-solid fa-circle-exclamation ic"></i>
                    <div>
                        <div style="font-weight:700;">Un reçu est déjà enregistré pour ce bon.</div>
                        <div>N° reçu: <strong><?= htmlspecialchars((string)($existingRecu['num_recu'] ?? '')) ?></strong>
                            <?php if (!empty($existingRecu['img_recu_station'])): ?> —
                                <a class="view-link" target="_blank" href="https://fidest.ci/decaissement/uploads/recu_station/<?= htmlspecialchars($existingRecu['img_recu_station']) ?>">Voir le reçu existant</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert" role="alert">
                    <i class="fa-solid fa-triangle-exclamation ic"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!$existingRecu): ?>
                <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
                    <div class="field">
                        <label for="num_recu">Numéro du reçu</label>
                        <input class="input" type="text" id="num_recu" name="num_recu" inputmode="numeric" placeholder="Ex: 001" value="<?= isset($_POST['num_recu']) ? htmlspecialchars($_POST['num_recu']) : '' ?>" required>
                    </div>

                    <div class="field">
                        <label>Photo du reçu (JPG/PNG/WEBP/GIF)</label>
                        <div id="dz" class="dropzone" tabindex="0">
                            <div class="dz-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                            <div><strong style="color:var(--text)">Glissez-déposez</strong> votre photo ici</div>
                            <div style="font-size:12px; margin-top:4px;">ou touchez pour ouvrir la caméra</div>
                            <input type="file" id="img_recu" name="img_recu" accept="image/*" capture="environment" required>
                            <div class="dz-preview" id="dzPreview">
                                <img id="dzImg" alt="Aperçu reçu" />
                                <div class="dz-filename" id="dzName"></div>
                            </div>
                        </div>
                    </div>

                    <button class="btn" type="submit"><i class="fa-solid fa-paper-plane" style="color:var(--accent);"></i> Confirmer le service</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Drag & Drop + Preview
        const dz = document.getElementById('dz');
        const fileInput = document.getElementById('img_recu');
        const prev = document.getElementById('dzPreview');
        const img = document.getElementById('dzImg');
        const nameLbl = document.getElementById('dzName');

        function setFile(f) {
            if (!f) return;
            const dt = new DataTransfer();
            dt.items.add(f);
            fileInput.files = dt.files;
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                prev.style.display = 'block';
                nameLbl.textContent = f.name;
            };
            reader.readAsDataURL(f);
        }

        if (dz && fileInput) {
            dz.addEventListener('click', () => fileInput.click());
            dz.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInput.click();
                }
            });
            dz.addEventListener('dragover', e => {
                e.preventDefault();
                dz.classList.add('dragover');
            });
            dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
            dz.addEventListener('drop', e => {
                e.preventDefault();
                dz.classList.remove('dragover');
                const f = e.dataTransfer.files && e.dataTransfer.files[0];
                if (f) setFile(f);
            });
            fileInput.addEventListener('change', () => {
                const f = fileInput.files && fileInput.files[0];
                if (f) setFile(f);
            });
        }
    </script>
</body>

</html>
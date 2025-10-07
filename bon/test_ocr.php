<?php
session_start();
// Debug local sur cette page de test uniquement
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);
require_once __DIR__ . '/../model/Config.php';
require_once __DIR__ . '/../model/OcrReceiptAnalyzer.php';
require_once __DIR__ . '/../model/DemandeEssence.php';
require_once __DIR__ . '/../model/Fiche.php';
require_once __DIR__ . '/../model/Database.php';

// Page de test isolée pour analyser un reçu avec l'OCR
$pdo = (new Database())->getConnection();
$demandeObj = new DemandeEssence($pdo);
$ficheObj = new Fiche($pdo);
$analyzer = new OcrReceiptAnalyzer();

$code_bon = $_GET['id_bon'] ?? null;
$expected_amount = null;
$ocrResult = null;
$validation = null;
$extracted = null;
$error = null;
$uploadedPath = null;
$ocrTextFileWeb = null;
$preprocessedWeb = null;
$iniUpload = ini_get('upload_max_filesize') ?: '2M';
$iniPost = ini_get('post_max_size') ?: '8M';

function describeUploadError(int $code): string
{
    global $iniUpload;
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return "Le fichier dépasse la limite upload_max_filesize ($iniUpload) de PHP.";
        case UPLOAD_ERR_FORM_SIZE:
            return "Le fichier dépasse la limite autorisée par le formulaire (MAX_FILE_SIZE).";
        case UPLOAD_ERR_PARTIAL:
            return "Le fichier n'a été que partiellement uploadé.";
        case UPLOAD_ERR_NO_FILE:
            return "Aucun fichier n'a été uploadé.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Dossier temporaire manquant sur le serveur.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Échec de l'écriture du fichier sur le disque.";
        case UPLOAD_ERR_EXTENSION:
            return "Upload stoppé par une extension PHP.";
        default:
            return "Erreur d'upload inconnue (code $code).";
    }
}

if ($code_bon) {
    $demande = $demandeObj->getByCodeBon($code_bon);
    if ($demande) {
        $fiche = $ficheObj->getByNumFiche($demande['num_fiche']);
        if ($fiche) {
            $expected_amount = (int)$fiche['montant_fiche'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['recu'])) {
            throw new RuntimeException('Aucun champ fichier reçu par le serveur.');
        }
        if ($_FILES['recu']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(describeUploadError((int)$_FILES['recu']['error']) . " (post_max_size: $iniPost)");
        }
        $tmp = $_FILES['recu']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException("Le fichier uploadé est invalide (non trouvé en tmp).");
        }
        $orig = basename($_FILES['recu']['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        // Taille max conseillée (8 Mo), à vérifier aussi côté php.ini
        $maxSize = 8 * 1024 * 1024;
        if (($_FILES['recu']['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('Fichier trop volumineux (>8 Mo). Compressez l\'image ou prenez une photo moins lourde.');
        }
        if (!in_array($ext, $allowed, true)) {
            $extra = $ext === 'heic' ? ' (HEIC non supporté, merci de convertir en JPG/PNG).' : '';
            throw new RuntimeException('Format non supporté.' . $extra);
        }
        $uploads = __DIR__ . '/../uploads/ocr_tests/';
        if (!is_dir($uploads)) @mkdir($uploads, 0777, true);
        $safe = 'test_' . time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '', $orig);
        $dest = $uploads . $safe;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException("Échec de l'upload.");
        }
        $uploadedPath = $dest;

        $ocr = $analyzer->ocrImage($dest);
        $ocrResult = $ocr;
        // construire chemin web de l'image prétraitée si présent
        if (!empty($ocr['preprocessed']) && is_string($ocr['preprocessed'])) {
            $preprocessedWeb = str_replace(__DIR__ . '/..', '', $ocr['preprocessed']);
        }
        if ($ocr['ok'] ?? false) {
            $extracted = $analyzer->extractStructured($ocr['text']);
            $rules = [
                'expected_amount' => isset($_POST['expected_amount']) && $_POST['expected_amount'] !== '' ? (int)$_POST['expected_amount'] : $expected_amount,
                'check_station' => isset($_POST['check_station']) ? (bool)$_POST['check_station'] : true,
            ];
            $validation = $analyzer->validate($ocr['text'], $rules);

            // Écrire un fichier .txt à côté de l'image pour télécharger le texte brut
            $txtDest = null;
            if ($uploadedPath) {
                $txtDest = preg_replace('/\.[A-Za-z0-9]+$/', '', $uploadedPath) . '.txt';
                @file_put_contents($txtDest, $ocr['text']);
                // Calculer le chemin web
                $ocrTextFileWeb = $txtDest ? str_replace(__DIR__ . '/..', '', $txtDest) : null;
            }

            // Stocker en session pour les pages succès/échec
            $webPath = str_replace(__DIR__ . '/..', '', $uploadedPath);
            $_SESSION['ocr_result'] = [
                'ok' => $validation['ok'] ?? false,
                'extracted' => $extracted,
                'validation' => $validation,
                'ocr_text' => $ocr['text'],
                'image_web' => $webPath,
                'code_bon' => $_POST['code_bon'] ?? $code_bon,
                'expected_amount' => $rules['expected_amount'] ?? null,
                'check_station' => $rules['check_station'] ?? true,
                'timestamp' => time(),
            ];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test OCR Reçu (IA)</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            margin: 20px;
            background: #0a0f0c;
            color: #d4f7df;
        }

        .card {
            max-width: 900px;
            margin: auto;
            background: #0f1713;
            border: 1px solid #123a2a;
            border-radius: 12px;
            padding: 16px;
        }

        .row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .col {
            flex: 1 1 280px;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #123a2a;
            background: #0c1410;
            color: #d4f7df;
        }

        .btn {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #00cc6a;
            color: #d4f7df;
            background: linear-gradient(90deg, rgba(0, 255, 136, .12), rgba(0, 255, 136, .06));
            cursor: pointer;
        }

        .alert {
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .alert.error {
            color: #ffd6d6;
            border: 1px solid #ff4d4f;
            background: rgba(255, 77, 79, .15);
        }

        .alert.ok {
            color: #c7ffda;
            border: 1px solid #00cc6a;
            background: rgba(0, 255, 136, .12);
        }

        pre {
            background: #0c1410;
            border: 1px solid #123a2a;
            padding: 12px;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        a {
            color: #00ff88;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #0c1410;
            border: 1px solid #123a2a;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Test OCR/IA sur reçu</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col">
                    <label>Image du reçu</label>
                    <input type="file" name="recu" accept="image/*" required>
                </div>
                <div class="col">
                    <label>Code bon (optionnel)</label>
                    <input type="text" name="code_bon" placeholder="Ex: BON-123" value="<?= htmlspecialchars($code_bon ?? '') ?>">
                </div>
                <div class="col">
                    <label>Montant attendu (optionnel)</label>
                    <input type="number" name="expected_amount" placeholder="Ex: 50000" value="<?= htmlspecialchars((string)($expected_amount ?? '')) ?>">
                </div>
                <div class="col">
                    <label>Contrôler que la station est VINKO</label>
                    <select name="check_station">
                        <option value="1" selected>Oui</option>
                        <option value="0">Non</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:12px;">
                <button class="btn" type="submit">Analyser</button>
                <span class="badge">Tesseract: <?= htmlspecialchars(AppConfig::ocrTesseractPath()) ?></span>
                <span class="badge">upload_max_filesize: <?= htmlspecialchars($iniUpload) ?></span>
                <span class="badge">post_max_size: <?= htmlspecialchars($iniPost) ?></span>
                <span class="badge">Imagick: <?= class_exists('Imagick') ? 'OK' : 'Non' ?></span>
                <span class="badge">GD: <?= function_exists('imagecreatetruecolor') ? 'OK' : 'Non' ?></span>
                <span class="badge">fileinfo: <?= function_exists('mime_content_type') ? 'OK' : 'Non' ?></span>
                <span class="badge">exif: <?= function_exists('exif_read_data') ? 'OK' : 'Non' ?></span>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert error">Erreur: <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($uploadedPath): ?>
            <?php $uploadedWeb = str_replace(__DIR__ . '/..', '', $uploadedPath); ?>
            <div class="row" style="margin-top:10px; align-items:flex-start;">
                <div class="col">
                    <div style="margin-bottom:6px;">Original</div>
                    <a href="<?= htmlspecialchars($uploadedWeb) ?>" target="_blank"><img src="<?= htmlspecialchars($uploadedWeb) ?>" alt="original" style="max-width:100%; border:1px solid #123a2a; border-radius:8px;"></a>
                </div>
                <?php if (!empty($preprocessedWeb)): ?>
                    <div class="col">
                        <div style="margin-bottom:6px;">Pré-traitée</div>
                        <a href="<?= htmlspecialchars($preprocessedWeb) ?>" target="_blank"><img src="<?= htmlspecialchars($preprocessedWeb) ?>" alt="preprocessed" style="max-width:100%; border:1px solid #123a2a; border-radius:8px;"></a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($ocrResult): ?>
            <h3>Résultat OCR</h3>
            <?php if ($ocrResult['ok']): ?>
                <div style="display:flex; gap:10px; align-items:center; margin-bottom:8px;">
                    <button class="btn" type="button" id="btnCopyOcr">Copier le texte</button>
                    <?php if (!empty($ocrTextFileWeb)): ?>
                        <a class="btn" href="<?= htmlspecialchars($ocrTextFileWeb) ?>" download>Télécharger .txt</a>
                    <?php endif; ?>
                </div>
                <pre id="ocrTextBlock"><?= htmlspecialchars($ocrResult['text']) ?></pre>
            <?php else: ?>
                <div class="alert error">OCR KO: <?= htmlspecialchars($ocrResult['error'] ?? 'inconnu') ?></div>
                <?php if (!empty($ocrResult['suggestions']) && is_array($ocrResult['suggestions'])): ?>
                    <div class="alert error" style="margin-top:8px;">
                        <strong>Pistes de résolution :</strong>
                        <ul style="margin:6px 0 0 18px;">
                            <?php foreach ($ocrResult['suggestions'] as $s): ?>
                                <li><?= htmlspecialchars($s) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($ocrResult['cmd'])): ?>
                    <details>
                        <summary style="cursor:pointer;">Commande exécutée (debug)</summary>
                        <pre><?= htmlspecialchars($ocrResult['cmd']) ?></pre>
                    </details>
                <?php endif; ?>
                <details open>
                    <summary style="cursor:pointer;">Diagnostics (debug)</summary>
                    <pre><?php
                            $diag = [];
                            $diag['tesseract'] = $ocrResult['tesseract'] ?? AppConfig::ocrTesseractPath();
                            if (!empty($ocrResult['candidates_tested'])) $diag['candidates_tested'] = $ocrResult['candidates_tested'];
                            if (isset($ocrResult['shell_exec_disabled'])) $diag['shell_exec_disabled'] = $ocrResult['shell_exec_disabled'];
                            if (!empty($ocrResult['disable_functions'])) $diag['disable_functions'] = $ocrResult['disable_functions'];
                            $diag['tessdata_dir'] = $ocrResult['tessdata_dir'] ?? '(non détecté)';
                            $diag['lang_config'] = AppConfig::ocrLang();
                            if (!empty($ocrResult['langs_missing']) && is_array($ocrResult['langs_missing'])) {
                                $diag['langs_missing'] = implode(', ', $ocrResult['langs_missing']);
                            }
                            $diag['psm'] = (string)AppConfig::ocrPsm();
                            $diag['image'] = $ocrResult['image'] ?? ($uploadedPath ? realpath($uploadedPath) : '(n/a)');
                            echo htmlspecialchars(json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            ?></pre>
                    <?php if (!empty($ocrResult['langs_missing'])): ?>
                        <div class="alert error" style="margin-top:8px;">Astuce: installez les fichiers traineddata pour les langues manquantes indiquées ci-dessus dans le dossier tessdata, ou réglez temporairement la langue sur "eng" dans l'admin OCR.</div>
                    <?php endif; ?>
                </details>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($extracted): ?>
            <h3>Données extraites</h3>
            <ul>
                <li>Montant détecté: <strong><?= $extracted['amount'] !== null ? number_format((int)$extracted['amount'], 0, ',', ' ') . ' FCFA' : '—' ?></strong></li>
                <li>Station VINKO: <strong><?= ($extracted['station']['matched'] ?? false) ? 'Oui' : 'Non' ?></strong>
                    <?php if ($extracted['station']['matched'] ?? false): ?>
                        <small>(match: <?= htmlspecialchars((string)($extracted['station']['match'] ?? '')) ?>, méthode: <?= htmlspecialchars((string)($extracted['station']['method'] ?? '')) ?>)</small>
                    <?php endif; ?>
                </li>
                <li>Chauffeur/Conducteur: <strong><?= !empty($extracted['chauffeur_name']) ? htmlspecialchars((string)$extracted['chauffeur_name']) : '—' ?></strong></li>
                <li>Bénéficiaire: <strong><?= !empty($extracted['beneficiaire_name']) ? htmlspecialchars((string)$extracted['beneficiaire_name']) : '—' ?></strong></li>
                <li>Signature présente: <strong><?= !empty($extracted['signature_present']) ? 'Oui' : 'Non' ?></strong></li>
                <li>Numéro de reçu: <strong><?= !empty($extracted['receipt_number']) ? htmlspecialchars((string)$extracted['receipt_number']) : '—' ?></strong></li>
                <li>Date: <strong><?= !empty($extracted['date']) ? htmlspecialchars((string)$extracted['date']) : '—' ?></strong></li>
                <li>Quantité (L): <strong><?= $extracted['quantity_liters'] !== null ? htmlspecialchars((string)$extracted['quantity_liters']) : '—' ?></strong></li>
                <li>Véhicule/Immat.: <strong><?= !empty($extracted['vehicle']) ? htmlspecialchars((string)$extracted['vehicle']) : '—' ?></strong></li>
            </ul>
            <pre><?= htmlspecialchars(json_encode($extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>

        <?php if ($validation): ?>
            <h3>Validation</h3>
            <?php if ($validation['ok']): ?>
                <div class="alert ok">Succès: toutes les vérifications sont OK.</div>
                <p><a href="success_ocr.php" class="btn">Aller à la page de succès</a></p>
            <?php else: ?>
                <div class="alert error">Échec:
                    <ul>
                        <?php foreach ($validation['errors'] as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <p><a href="/bon/failure_ocr.php" class="btn">Aller à la page d'échec</a></p>
            <?php endif; ?>
            <h4>Détails</h4>
            <pre><?= htmlspecialchars(json_encode($validation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php endif; ?>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('btnCopyOcr');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var pre = document.getElementById('ocrTextBlock');
                if (!pre) return;
                var text = pre.textContent || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        btn.textContent = 'Copié !';
                        setTimeout(function() {
                            btn.textContent = 'Copier le texte';
                        }, 1500);
                    }).catch(function() {
                        // fallback
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        try {
                            document.execCommand('copy');
                        } catch (e) {}
                        document.body.removeChild(ta);
                        btn.textContent = 'Copié !';
                        setTimeout(function() {
                            btn.textContent = 'Copier le texte';
                        }, 1500);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy');
                    } catch (e) {}
                    document.body.removeChild(ta);
                    btn.textContent = 'Copié !';
                    setTimeout(function() {
                        btn.textContent = 'Copier le texte';
                    }, 1500);
                }
            });
        })();
    </script>
</body>

</html>
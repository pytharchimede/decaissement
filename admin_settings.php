<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/SettingsRepository.php';
require_once __DIR__ . '/model/Config.php';

// Protection ultra-simple (à améliorer si besoin) : mot de passe d'une variable d'env ou fixe
$ADMIN_PASS = getenv('DECAISSEMENT_ADMIN_PASS') ?: 'admin';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (isset($_POST['admin_pass'])) {
        if (hash('sha256', $_POST['admin_pass']) === hash('sha256', $ADMIN_PASS)) {
            $_SESSION['is_admin'] = true;
            header('Location: admin_settings.php');
            exit;
        } else {
            $login_error = 'Mot de passe invalide';
        }
    }
?>
    <!doctype html>
    <html lang="fr">

    <head>
        <meta charset="utf-8">
        <title>Admin - Connexion</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    </head>

    <body class="p-4">
        <div class="container" style="max-width:420px;">
            <h3 class="mb-3">Administration – Connexion</h3>
            <?php if (!empty($login_error)): ?><div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mot de passe admin</label>
                    <input type="password" class="form-control" name="admin_pass" required>
                </div>
                <button class="btn btn-primary w-100">Se connecter</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

$pdo = Database::getConnection();
$repo = new SettingsRepository($pdo);

$saved = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        AppConfig::setTwilioSid(trim($_POST['twilio_sid'] ?? ''));
        AppConfig::setTwilioToken(trim($_POST['twilio_token'] ?? ''));
        AppConfig::setWhatsappFrom(trim($_POST['whatsapp_from'] ?? ''));
        AppConfig::setGeranteWhatsapp(trim($_POST['gerante_whatsapp'] ?? ''));
        AppConfig::setGeranteSms(trim($_POST['gerante_sms'] ?? ''));
        // OCR / IA
        AppConfig::setOcrTesseractPath(trim($_POST['ocr_tesseract_path'] ?? ''));
        AppConfig::setOcrLang(trim($_POST['ocr_lang'] ?? ''));
        AppConfig::setOcrPsm(trim($_POST['ocr_psm'] ?? ''));
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Test de la configuration OCR (tesseract --version)
$test_output = null;
$test_cmd = null;
$test_err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_ocr'])) {
    try {
        $path = trim($_POST['ocr_tesseract_path'] ?? AppConfig::ocrTesseractPath());
        $lang = trim($_POST['ocr_lang'] ?? AppConfig::ocrLang());
        $psm  = trim($_POST['ocr_psm'] ?? AppConfig::ocrPsm());
        if ($path === '') {
            throw new RuntimeException('Chemin Tesseract vide');
        }
        $test_cmd = '"' . $path . '" --version';
        $out = shell_exec($test_cmd . ' 2>&1');
        if ($out === null) {
            $test_err = 'Execution null (droits/chemin).';
        } else {
            $test_output = $out;
        }
    } catch (Throwable $e) {
        $test_err = $e->getMessage();
    }
}

$vals = [
    'twilio_sid' => AppConfig::twilioSid(),
    'twilio_token' => AppConfig::twilioToken(),
    'whatsapp_from' => AppConfig::whatsappFrom(),
    'gerante_whatsapp' => AppConfig::geranteWhatsapp(),
    'gerante_sms' => AppConfig::geranteSms(),
    // OCR / IA
    'ocr_tesseract_path' => AppConfig::ocrTesseractPath(),
    'ocr_lang' => AppConfig::ocrLang(),
    'ocr_psm' => AppConfig::ocrPsm(),
];
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration – Paramètres</title>
    <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 16px;
        }

        .container {
            max-width: 820px;
        }
    </style>
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="data:,">
</head>

<body>
    <?php include __DIR__ . '/headers/top_nav.php'; ?>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Paramètres WhatsApp / SMS</h3>
            <div>
                <a class="btn btn-secondary btn-sm" href="formulaire_demande_decaissement.php">Retour</a>
                <a class="btn btn-warning btn-sm" href="batch_generate_bons_express.php">Batch EXP-DEP</a>
            </div>
        </div>
        <?php if ($saved): ?><div class="alert alert-success">Paramètres enregistrés.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger">Erreur: <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="save_settings" value="1">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Twilio SID</label>
                            <input type="text" class="form-control" name="twilio_sid" value="<?= htmlspecialchars($vals['twilio_sid']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Twilio Token</label>
                            <input type="text" class="form-control" name="twilio_token" value="<?= htmlspecialchars($vals['twilio_token']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">WhatsApp From (E.164)</label>
                            <input type="text" class="form-control" name="whatsapp_from" value="<?= htmlspecialchars($vals['whatsapp_from']) ?>" placeholder="whatsapp:+22507xxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gérante WhatsApp (E.164)</label>
                            <input type="text" class="form-control" name="gerante_whatsapp" value="<?= htmlspecialchars($vals['gerante_whatsapp']) ?>" placeholder="+22507xxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gérante SMS (225...)</label>
                            <input type="text" class="form-control" name="gerante_sms" value="<?= htmlspecialchars($vals['gerante_sms']) ?>" placeholder="22507xxxxxxx" required>
                        </div>
                    </div>
                </div>
            </div>
            <h3 class="mt-4">Paramètres OCR / IA</h3>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Chemin Tesseract (tesseract.exe)</label>
                            <input type="text" class="form-control" name="ocr_tesseract_path" value="<?= htmlspecialchars($vals['ocr_tesseract_path']) ?>" placeholder="C:\\Program Files\\Tesseract-OCR\\tesseract.exe" required>
                            <div class="form-text">Assurez-vous que ce chemin existe et est exécutable par le service web.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Langue(s)</label>
                            <input type="text" class="form-control" name="ocr_lang" value="<?= htmlspecialchars($vals['ocr_lang']) ?>" placeholder="fra+eng">
                            <div class="form-text">Langues installées dans Tesseract (ex: fra, eng, fra+eng).</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">PSM</label>
                            <input type="number" min="1" max="13" class="form-control" name="ocr_psm" value="<?= htmlspecialchars($vals['ocr_psm']) ?>" placeholder="6">
                            <div class="form-text">Page Segmentation Mode (par ex. 6).</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-end">
                <button class="btn btn-primary">Enregistrer</button>
            </div>
        </form>

        <form method="post" class="mt-3">
            <input type="hidden" name="test_ocr" value="1">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="m-0">Tester la configuration OCR</h5>
                        <div>
                            <button class="btn btn-outline-secondary btn-sm">Lancer le test (--version)</button>
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
                            <label class="form-label">Chemin Tesseract</label>
                            <input type="text" class="form-control" name="ocr_tesseract_path" value="<?= htmlspecialchars($vals['ocr_tesseract_path']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Langue(s)</label>
                            <input type="text" class="form-control" name="ocr_lang" value="<?= htmlspecialchars($vals['ocr_lang']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">PSM</label>
                            <input type="number" min="1" max="13" class="form-control" name="ocr_psm" value="<?= htmlspecialchars($vals['ocr_psm']) ?>">
                        </div>
                    </div>
                    <?php if ($test_output): ?>
                        <div class="alert alert-success mt-3"><strong>OK</strong>
                            <pre class="m-0" style="white-space:pre-wrap;"><?= htmlspecialchars($test_output) ?></pre>
                        </div>
                    <?php elseif ($test_err): ?>
                        <div class="alert alert-danger mt-3"><strong>Erreur</strong>: <?= htmlspecialchars($test_err) ?></div>
                    <?php endif; ?>
                    <?php if ($test_cmd): ?>
                        <div class="mt-2"><small class="text-muted">Commande exécutée: <code><?= htmlspecialchars($test_cmd) ?></code></small></div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</body>

</html>
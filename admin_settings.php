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
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$vals = [
    'twilio_sid' => AppConfig::twilioSid(),
    'twilio_token' => AppConfig::twilioToken(),
    'whatsapp_from' => AppConfig::whatsappFrom(),
    'gerante_whatsapp' => AppConfig::geranteWhatsapp(),
    'gerante_sms' => AppConfig::geranteSms(),
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
            <div class="text-end">
                <button class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</body>

</html>
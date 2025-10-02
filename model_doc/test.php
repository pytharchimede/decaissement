<?php
require_once __DIR__ . '/../model/DocumentVerifier.php';
require_once __DIR__ . '/../model/DocumentTemplateFileRepository.php';
require_once __DIR__ . '/../model/Config.php';

$msg = null;
$err = null;
$res = null;
$tplKey = '';
$fs = new DocumentTemplateFileRepository();
$tpls = array_map(fn($t) => $t['key'], $fs->listAll());

// Bandeau d'état OCR
$engine = method_exists('AppConfig', 'ocrEngine') ? AppConfig::ocrEngine() : 'tesseract';
$tessPath = AppConfig::ocrTesseractPath();
$lang = AppConfig::ocrLang();
$psm = AppConfig::ocrPsm();
$paddleEndpoint = getenv('PADDLE_OCR_ENDPOINT') ?: 'http://localhost:8080';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tplKey = trim($_POST['template'] ?? '');
    if ($tplKey === '') {
        $err = 'Sélectionnez un modèle';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Image requise';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $verifier = new DocumentVerifier();
        $res = $verifier->verify($tplKey, $tmp, []);
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Tester un modèle</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <h1>Tester un modèle</h1>
        <div class="card" style="margin-bottom:12px">
            <div class="row" style="justify-content:space-between">
                <div>
                    <strong>Moteur:</strong> <?= htmlspecialchars($engine) ?>
                    &nbsp;|&nbsp; <strong>Lang:</strong> <?= htmlspecialchars($lang) ?>
                    &nbsp;|&nbsp; <strong>PSM:</strong> <?= htmlspecialchars($psm) ?>
                </div>
                <div>
                    <strong>Tesseract:</strong> <?= htmlspecialchars($tessPath) ?>
                    &nbsp;|&nbsp; <strong>Paddle:</strong> <?= htmlspecialchars($paddleEndpoint) ?>
                </div>
            </div>
        </div>
        <?php if ($msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
        <?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>
        <div class="card">
            <form method="post" enctype="multipart/form-data" class="row">
                <select name="template" required>
                    <option value="">-- Modèle --</option>
                    <?php foreach ($tpls as $k): ?>
                        <option <?= $tplKey === $k ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="file" accept="image/*">
                <button class="btn">Tester</button>
                <a class="btn" href="index.php">Retour</a>
            </form>
        </div>
        <?php if ($res): ?>
            <div class="card" style="margin-top:12px">
                <div class="row" style="align-items:flex-start; gap:12px">
                    <div style="flex:2; min-width:300px">
                        <pre><?= htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                    <?php if (!empty($res['details']['roi'])): ?>
                        <div style="flex:1; min-width:280px">
                            <h3>Aperçu des ROI</h3>
                            <?php foreach ($res['details']['roi'] as $r): ?>
                                <div class="card" style="margin-bottom:10px">
                                    <div><strong><?= htmlspecialchars($r['name'] ?? 'roi') ?></strong> (psm <?= (int)($r['psm'] ?? 7) ?>)</div>
                                    <?php if (!empty($r['image']) && is_file($r['image'])): ?>
                                        <?php
                                        $mime = function_exists('mime_content_type') ? @mime_content_type($r['image']) : 'image/png';
                                        if (!$mime) $mime = 'image/png';
                                        $data = @file_get_contents($r['image']);
                                        $b64 = $data !== false ? base64_encode($data) : null;
                                        ?>
                                        <?php if ($b64): ?>
                                            <div style="margin:6px 0"><img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= $b64 ?>" alt="roi" style="max-width:100%"></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div style="font-size:12px"><em>Texte:</em> <?= htmlspecialchars((string)($r['text'] ?? '')) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
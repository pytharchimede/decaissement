<?php
require_once __DIR__ . '/../model/DocumentVerifier.php';
require_once __DIR__ . '/../model/DocumentTemplateFileRepository.php';
require_once __DIR__ . '/../model/DocumentTemplateRepository.php';
require_once __DIR__ . '/../model/Config.php';

$msg = null;
$err = null;
$results = [];
$match = null; // ['key' => ..., 'name' => ..., 'result' => ...]

// Bandeau d'état OCR
$engine = method_exists('AppConfig', 'ocrEngine') ? AppConfig::ocrEngine() : 'tesseract';
$tessPath = AppConfig::ocrTesseractPath();
$lang = AppConfig::ocrLang();
$psm = AppConfig::ocrPsm();
$paddleEndpoint = getenv('PADDLE_OCR_ENDPOINT') ?: 'http://localhost:8080';

// Charger tous les modèles (fichiers JSON prioritaire, puis DB sans doublons)
$fs = new DocumentTemplateFileRepository();
$db = new DocumentTemplateRepository();
$templates = [];
$seen = [];
foreach ($fs->listAll() as $t) {
    $templates[] = $t;
    $seen[$t['key']] = true;
}
foreach ($db->listAll() as $t) {
    if (!isset($seen[$t['key']])) $templates[] = $t;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Image requise';
    } elseif (empty($templates)) {
        $err = 'Aucun modèle disponible.';
    } else {
        $tmp = $_FILES['file']['tmp_name'];
        $verifier = new DocumentVerifier();
        $best = null; // ['tpl' => $tpl, 'res' => $res, 'errors' => n]
        foreach ($templates as $tpl) {
            $res = $verifier->verify($tpl['key'], $tmp, []);
            $ok = (bool)($res['ok'] ?? false);
            $count = is_array($res['errors'] ?? null) ? count($res['errors']) : ($ok ? 0 : 9999);
            $results[] = [
                'key' => $tpl['key'],
                'name' => $tpl['name'],
                'ok' => $ok,
                'errorsCount' => $count,
                'errors' => $res['errors'] ?? [],
                'details' => $res['details'] ?? [],
            ];
            if ($ok && $match === null) {
                $match = ['key' => $tpl['key'], 'name' => $tpl['name'], 'result' => $res];
            }
            if ($best === null || $count < $best['errors']) {
                $best = ['tpl' => $tpl, 'res' => $res, 'errors' => $count];
            }
        }
        if ($match === null && $best !== null) {
            // Pas de match parfait, on garde le "plus proche" pour l'explication
            $msg = 'Aucun modèle reconnu. Le plus proche est: ' . $best['tpl']['key'] . ' (' . $best['tpl']['name'] . ') avec ' . (int)$best['errors'] . ' erreur(s).';
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Détecter le modèle d’un document</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <h1>Détecter le modèle d’un document</h1>
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
                <input type="file" name="file" accept="image/*" required>
                <button class="btn">Détecter</button>
                <a class="btn" href="index.php">Retour</a>
            </form>
            <div class="muted" style="margin-top:6px">Modèles disponibles: <?= count($templates) ?></div>
        </div>

        <?php if ($match): ?>
            <div class="card" style="margin-top:12px; border-color:#0a0">
                <h2 style="margin-top:0">Modèle reconnu</h2>
                <p><strong>Clé:</strong> <?= htmlspecialchars($match['key']) ?> &nbsp;|&nbsp; <strong>Nom:</strong> <?= htmlspecialchars($match['name']) ?></p>
                <details>
                    <summary>Voir le détail brut</summary>
                    <pre><?= htmlspecialchars(json_encode($match['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </details>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err): ?>
            <div class="card" style="margin-top:12px; border-color:#a00">
                <h2 style="margin-top:0">Aucun modèle reconnu</h2>
                <p>Le document ne correspond à aucun de nos modèles. Ci-dessous, le résumé par modèle testé.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($results)): ?>
            <div class="card" style="margin-top:12px">
                <h3>Résumé par modèle</h3>
                <table style="width:100%">
                    <thead>
                        <tr>
                            <th style="text-align:left">Modèle (clé)</th>
                            <th style="text-align:left">Nom</th>
                            <th>OK</th>
                            <th>Erreurs</th>
                            <th>Détail (mots-clés manquants, ROI, regex)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['key']) ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td style="text-align:center; color:<?= $r['ok'] ? '#0a0' : '#a00' ?>"><?= $r['ok'] ? '✔' : '✖' ?></td>
                                <td style="text-align:center"><?= (int)$r['errorsCount'] ?></td>
                                <td>
                                    <?php if (!$r['ok'] && !empty($r['details'])): ?>
                                        <?php
                                        $missing = $r['details']['keywords_missing'] ?? [];
                                        $rx = $r['details']['regex_matches'] ?? [];
                                        $roi = $r['details']['roi'] ?? [];
                                        ?>
                                        <?php if (!empty($missing)): ?>
                                            <div><strong>Mots-clés manquants:</strong> <?= htmlspecialchars(implode(', ', $missing)) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($rx)): ?>
                                            <div><strong>Regex:</strong>
                                                <?php foreach ($rx as $label => $ok): ?>
                                                    <span class="tag" style="border-color: <?= $ok ? '#0a0' : '#a00' ?>; color: <?= $ok ? '#0a0' : '#a00' ?>;">
                                                        <?= htmlspecialchars($label) ?>: <?= $ok ? 'ok' : 'ko' ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($roi)): ?>
                                            <div><strong>ROI:</strong>
                                                <?php foreach ($roi as $i => $rr): ?>
                                                    <span class="tag" title="<?= htmlspecialchars((string)($rr['text'] ?? '')) ?>">
                                                        <?= htmlspecialchars($rr['name'] ?? ('roi#' . ($i + 1))) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
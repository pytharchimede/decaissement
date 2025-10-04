<?php
require_once __DIR__ . '/../model/Config.php';
require_once __DIR__ . '/../model/DocumentTemplateFileRepository.php';

$msg = null;
$err = null;
$sessionId = isset($_GET['session']) ? trim((string)$_GET['session']) : null; // ex: sess_20251002_abc123
$sessionDir = $sessionId ? (__DIR__ . '/uploads/' . $sessionId) : null; // c:\...\model_doc\uploads\sess_*
$manifestPath = $sessionDir ? ($sessionDir . '/manifest.json') : null;
$docs = []; // each: ['path'=>..., 'w'=>..., 'h'=>..., 'boxes' (only in suggest step)]
$suggest = ['keywords' => [], 'rois' => [], 'regex_required' => []];
$existingTemplates = [];

function run_tesseract_tsv_learn(string $img, int $psm = null): array
{
    $tess = AppConfig::ocrTesseractPath();
    if (!is_file($tess)) return [];
    $lang = AppConfig::ocrLang();
    $psm = $psm ?? AppConfig::ocrPsm();
    $psm = (int)$psm;
    if ($psm <= 0 || $psm > 13) $psm = 6;
    $lang = preg_replace('/[^a-zA-Z+_]/', '', (string)$lang) ?: 'eng';
    $tessdata = rtrim(dirname($tess), "\\/") . DIRECTORY_SEPARATOR . 'tessdata';
    $cmd = '"' . $tess . '" "' . $img . '" stdout --psm ' . $psm . ' -l ' . $lang . ' tsv';
    if (is_dir($tessdata)) $cmd .= ' --tessdata-dir ' . '"' . $tessdata . '"';
    $out = shell_exec($cmd . ' 2>&1');
    if ($out === null || trim($out) === '') return [];
    $lines = preg_split('/\R/u', str_replace("\r", '', $out));
    if (!$lines) return [];
    $header = array_map('trim', explode("\t", array_shift($lines)));
    $idx = array_flip($header);
    $boxes = [];
    foreach ($lines as $ln) {
        if ($ln === '') continue;
        $parts = explode("\t", $ln);
        if (count($parts) < count($header)) continue;
        $text  = (string)($parts[$idx['text']] ?? '');
        $conf  = (int)($parts[$idx['conf']] ?? -1);
        $l = (int)($parts[$idx['left']] ?? 0);
        $t = (int)($parts[$idx['top']] ?? 0);
        $w = (int)($parts[$idx['width']] ?? 0);
        $h = (int)($parts[$idx['height']] ?? 0);
        if ($w > 0 && $h > 0 && trim($text) !== '') {
            $boxes[] = ['left' => $l, 'top' => $t, 'width' => $w, 'height' => $h, 'text' => $text, 'conf' => $conf];
        }
    }
    return $boxes;
}

function normalize_token(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    if (class_exists('Normalizer') && function_exists('normalizer_normalize')) {
        $s = normalizer_normalize($s, Normalizer::FORM_D);
        $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string)$s);
    }
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return $s;
}

function extract_tokens_doc(array $boxes): array
{
    $tokens = [];
    foreach ($boxes as $b) {
        $norm = normalize_token((string)$b['text']);
        if ($norm === '') continue;
        foreach (explode(' ', $norm) as $tk) {
            if ($tk === '' || mb_strlen($tk, 'UTF-8') < 3) continue;
            $tokens[] = $tk;
        }
    }
    return array_values(array_unique($tokens));
}

function is_stopword(string $tk): bool
{
    static $stop = ['bon', 'pour', 'de', 'la', 'le', 'les', 'du', 'des', 'au', 'aux', 'et', 'par', 'a', 'en', 'sur', 'un', 'une', 'dun', 'dune', 'total', 'montant', 'n', 'no', 'numero', 'numéro'];
    return in_array($tk, $stop, true);
}

function list_templates_fs(): array
{
    $dir = __DIR__ . '/../config/document_templates';
    $out = [];
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.json') ?: [] as $f) {
            $raw = @file_get_contents($f);
            $j = $raw ? (json_decode($raw, true) ?: null) : null;
            if (!$j) continue;
            $key = (string)($j['key'] ?? '');
            $name = (string)($j['name'] ?? $key);
            if ($key !== '') $out[] = ['key' => $key, 'name' => $name, 'path' => $f];
        }
    }
    return $out;
}

function crop_preview_datauri(string $imagePath, float $x, float $y, float $w, float $h): ?string
{
    $outImg = null;
    // Imagick preferred
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImage($imagePath);
            if (method_exists($im, 'autoOrient')) $im->autoOrient();
            $W = $im->getImageWidth();
            $H = $im->getImageHeight();
            $cx = max(0, (int)round($W * $x));
            $cy = max(0, (int)round($H * $y));
            $cw = max(1, (int)round($W * $w));
            $ch = max(1, (int)round($H * $h));
            if (method_exists($im, 'cropImage')) {
                $im->cropImage($cw, $ch, $cx, $cy);
                if (method_exists($im, 'setImagePage')) $im->setImagePage(0, 0, 0, 0);
            }
            if (method_exists($im, 'setImageColorspace')) $im->setImageColorspace(Imagick::COLORSPACE_GRAY);
            if (method_exists($im, 'enhanceImage')) @$im->enhanceImage();
            $im->setImageFormat('png');
            // Write a temp file and read back (broadest compatibility)
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'roi_' . uniqid('', true) . '.png';
            $im->writeImage($tmp);
            $blob = @file_get_contents($tmp);
            if ($blob !== false) {
                $outImg = 'data:image/png;base64,' . base64_encode((string)$blob);
            }
            @unlink($tmp);
            $im->clear();
            $im->destroy();
            return $outImg;
        } catch (Throwable $e) { /* fallback GD */
        }
    }
    // GD fallback
    $mime = function_exists('mime_content_type') ? strtolower((string)@mime_content_type($imagePath)) : null;
    $src = null;
    if (function_exists('imagecreatefromjpeg') && (($mime && strpos($mime, 'jpeg') !== false) || preg_match('/\.(jpe?g)$/i', $imagePath))) $src = @imagecreatefromjpeg($imagePath);
    elseif (function_exists('imagecreatefrompng') && (($mime && strpos($mime, 'png') !== false) || preg_match('/\.(png)$/i', $imagePath))) $src = @imagecreatefrompng($imagePath);
    elseif (function_exists('imagecreatefromwebp') && (($mime && strpos($mime, 'webp') !== false) || preg_match('/\.(webp)$/i', $imagePath))) $src = @imagecreatefromwebp($imagePath);
    if ($src) {
        $W = imagesx($src);
        $H = imagesy($src);
        $cx = max(0, (int)round($W * $x));
        $cy = max(0, (int)round($H * $y));
        $cw = max(1, (int)round($W * $w));
        $ch = max(1, (int)round($H * $h));
        $dst = imagecreatetruecolor($cw, $ch);
        imagecopy($dst, $src, 0, 0, $cx, $cy, $cw, $ch);
        @imagefilter($dst, IMG_FILTER_GRAYSCALE);
        @imagefilter($dst, IMG_FILTER_CONTRAST, -10);
        ob_start();
        imagepng($dst, null, 6);
        $blob = ob_get_clean();
        imagedestroy($dst);
        imagedestroy($src);
        return 'data:image/png;base64,' . base64_encode((string)$blob);
    }
    return null;
}

// Aide: si POST sans données (ex: post_max_size dépassé), prévenir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $err = 'Aucun fichier reçu. La taille totale dépasse peut-être post_max_size (' . ini_get('post_max_size') . ').';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        if (!isset($_FILES['files'])) {
            $err = 'Sélectionnez des images';
        } else {
            // Crée une session dédiée et enregistre seulement les chemins + dimensions (OCR reporté à l'étape suivante)
            $sessionId = 'sess_' . date('Ymd_His') . '_' . substr(md5((string)mt_rand()), 0, 6);
            $sessionDir = __DIR__ . '/uploads/' . $sessionId;
            $manifestPath = $sessionDir . '/manifest.json';
            @mkdir($sessionDir, 0777, true);

            $maxUploads = (int)ini_get('max_file_uploads');
            $selected = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 0;
            if ($selected > $maxUploads && $maxUploads > 0) {
                $msg = 'Attention: vous avez sélectionné ' . $selected . ' fichiers, la limite PHP max_file_uploads est ' . $maxUploads . '. Tous ne seront peut-être pas pris en compte.';
            }

            $count = 0;
            $list = [];
            foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = basename((string)$_FILES['files']['name'][$i]);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = 'png';
                $dest = $sessionDir . DIRECTORY_SEPARATOR . ($i + 1) . '_' . time() . '.' . $ext;
                if (@move_uploaded_file($tmp, $dest)) {
                    $size = @getimagesize($dest);
                    $w = $size ? ($size[0] ?? 0) : 0;
                    $h = $size ? ($size[1] ?? 0) : 0;
                    $list[] = ['path' => $dest, 'w' => $w, 'h' => $h];
                    $count++;
                }
            }
            if ($count === 0) {
                $err = 'Aucun fichier importé';
            } else {
                // Écrire le manifeste et rediriger (PRG) pour éviter le re-submit et préserver l'état
                @file_put_contents($manifestPath, json_encode(['docs' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                header('Location: learn.php?session=' . urlencode($sessionId) . '&imported=' . $count);
                exit;
            }
        }
    } elseif ($action === 'suggest') {
        $sessionId = trim((string)($_POST['session'] ?? ''));
        if ($sessionId !== '') {
            $sessionDir = __DIR__ . '/uploads/' . $sessionId;
            $manifestPath = $sessionDir . '/manifest.json';
        }
        $minRatio = max(0.1, min(1.0, (float)($_POST['min_ratio'] ?? 0.6)));
        $limit = (int)($_POST['limit'] ?? 50);
        if ($limit <= 0) $limit = 50;

        // Charger manifeste
        $manifest = is_file($manifestPath ?? '') ? (json_decode((string)@file_get_contents($manifestPath), true) ?: []) : [];
        $baseDocs = $manifest['docs'] ?? [];
        $N = count($baseDocs);
        $N = count($docs);
        if ($N <= 0 && count($baseDocs) <= 0) {
            $err = 'Téléversez des images d’abord';
        } else {
            // Construire $docs avec OCR (échantillonnage limité pour éviter les timeouts)
            if ($N === 0) $N = count($baseDocs);
            $toProcess = array_slice($baseDocs, 0, $limit);
            $docs = [];
            foreach ($toProcess as $d) {
                $boxes = run_tesseract_tsv_learn($d['path']);
                $docs[] = ['path' => $d['path'], 'w' => $d['w'], 'h' => $d['h'], 'boxes' => $boxes];
            }
            $N = count($docs);
            // 1) Mots-clés fréquents (par document)
            $df = [];
            foreach ($docs as $d) {
                $tokens = extract_tokens_doc($d['boxes'] ?? []);
                $seen = [];
                foreach ($tokens as $tk) {
                    if (is_stopword($tk)) continue;
                    if (isset($seen[$tk])) continue;
                    $df[$tk] = ($df[$tk] ?? 0) + 1;
                    $seen[$tk] = true;
                }
            }
            arsort($df);
            $keywords = [];
            foreach ($df as $tk => $c) {
                if ($c >= ceil($minRatio * $N)) $keywords[] = $tk;
                if (count($keywords) >= 12) break;
            }

            // 2) ROI pour ancres typiques
            $anchors = [
                ['name' => 'numero', 'pattern' => '~\b(n[°o]|num[eé]ro)\b~iu', 'regex' => '~\b([0-9]{3,8})\b~'],
                ['name' => 'vinko', 'pattern' => '~\bvinko\b~iu', 'regex' => '~\bvinko\b~iu'],
                ['name' => 'montant', 'pattern' => '~\b(montant|total|valeur|bon\s+pour)\b~iu', 'regex' => '~([0-9]{1,3}(?:[ .][0-9]{3})+|[0-9]{4,})\s*(fcfa|cfa)?~iu'],
            ];
            $rois = [];
            foreach ($anchors as $a) {
                $hits = [];
                $hitDocs = 0;
                foreach ($docs as $d) {
                    $found = [];
                    foreach ($d['boxes'] as $b) {
                        if (@preg_match($a['pattern'], (string)$b['text']) === 1) {
                            $found[] = $b;
                        }
                    }
                    if (!empty($found)) {
                        $hitDocs++;
                        // Union des boxes trouvées pour ce doc
                        $minL = min(array_column($found, 'left'));
                        $minT = min(array_column($found, 'top'));
                        $maxR = max(array_map(fn($bb) => $bb['left'] + $bb['width'], $found));
                        $maxB = max(array_map(fn($bb) => $bb['top'] + $bb['height'], $found));
                        $pad = 6; // quelques pixels
                        $l = max(0, $minL - $pad);
                        $t = max(0, $minT - $pad);
                        $r = $maxR + $pad;
                        $b = $maxB + $pad;
                        $W = max(1, (int)$d['w']);
                        $H = max(1, (int)$d['h']);
                        $hits[] = [
                            'x' => $l / $W,
                            'y' => $t / $H,
                            'w' => max(1, $r - $l) / $W,
                            'h' => max(1, $b - $t) / $H,
                        ];
                    }
                }
                if ($hitDocs >= ceil($minRatio * $N) && !empty($hits)) {
                    // Moyenne des positions
                    $mx = array_sum(array_column($hits, 'x')) / count($hits);
                    $my = array_sum(array_column($hits, 'y')) / count($hits);
                    $mw = array_sum(array_column($hits, 'w')) / count($hits);
                    $mh = array_sum(array_column($hits, 'h')) / count($hits);
                    $rois[] = ['name' => $a['name'], 'x' => $mx, 'y' => $my, 'w' => $mw, 'h' => $mh, 'psm' => 7, 'regex' => $a['regex']];
                }
            }

            $suggest = [
                'keywords' => $keywords,
                'rois' => $rois,
                'regex_required' => ['numero' => '~\b(n[°o]|num[eé]ro)\b~iu'],
            ];
            $msg = 'Suggestions générées. Documents analysés: ' . $N . ' / ' . count($baseDocs);
        }
    } elseif ($action === 'save' && isset($_POST['key'])) {
        $key = trim($_POST['key'] ?? '');
        $name = trim($_POST['name'] ?? $key);
        $config = json_decode($_POST['config_json'] ?? '{}', true);
        if ($key === '' || !is_array($config)) {
            $err = 'Clé ou configuration invalide';
        } else {
            $fs = new DocumentTemplateFileRepository();
            $ok = $fs->upsert($key, $name, $config);
            $msg = $ok ? ('Modèle enregistré: ' . htmlspecialchars($key)) : 'Échec enregistrement';
        }
    } elseif ($action === 'save_form') {
        $mode = $_POST['save_mode'] ?? 'new'; // 'new' | 'overwrite'
        $key = trim((string)($_POST['key'] ?? ''));
        $name = trim((string)($_POST['name'] ?? $key));
        $ovw = trim((string)($_POST['overwrite_key'] ?? ''));
        // Keywords
        $kw = $_POST['kw'] ?? [];
        if (!is_array($kw)) $kw = [];
        $kw = array_values(array_unique(array_filter(array_map('strval', $kw))));
        // ROI
        $roiNames = $_POST['roi_name'] ?? [];
        $roiX = $_POST['roi_x'] ?? [];
        $roiY = $_POST['roi_y'] ?? [];
        $roiW = $_POST['roi_w'] ?? [];
        $roiH = $_POST['roi_h'] ?? [];
        $roiPSM = $_POST['roi_psm'] ?? [];
        $roiRegex = $_POST['roi_regex'] ?? [];
        $rois = [];
        $n = max(count($roiNames), count($roiX), count($roiY), count($roiW), count($roiH));
        for ($i = 0; $i < $n; $i++) {
            $nameR = (string)($roiNames[$i] ?? 'roi');
            $x = (float)($roiX[$i] ?? 0);
            $y = (float)($roiY[$i] ?? 0);
            $w = (float)($roiW[$i] ?? 0);
            $h = (float)($roiH[$i] ?? 0);
            $psm = (int)($roiPSM[$i] ?? 7);
            $regex = (string)($roiRegex[$i] ?? '');
            if ($w <= 0 || $h <= 0) continue;
            $rois[] = ['name' => $nameR, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'psm' => $psm, 'regex' => $regex];
        }
        // Regex required (simple champ libre; tu peux étendre si besoin)
        $rx_required = $_POST['rx_required'] ?? ['numero' => '~\b(n[°o]|num[eé]ro)\b~iu'];
        if (!is_array($rx_required)) $rx_required = [];
        // Cible
        if ($mode === 'overwrite') {
            $key = $ovw !== '' ? $ovw : $key;
        }
        if ($key === '') {
            $err = 'Clé requise';
        } else {
            $cfg = ['keywords_required' => $kw, 'regex_required' => $rx_required, 'rois' => $rois];
            $fs = new DocumentTemplateFileRepository();
            $ok = $fs->upsert($key, $name ?: $key, $cfg);
            $msg = $ok ? ('Modèle enregistré: ' . htmlspecialchars($key)) : 'Échec enregistrement';
        }
    }
}

// Charger docs depuis le manifeste si session fournie en GET
if (!$err && !$msg && is_file($manifestPath ?? '')) {
    $manifest = json_decode((string)@file_get_contents($manifestPath), true) ?: [];
    $docs = $manifest['docs'] ?? [];
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Apprentissage à partir d’un lot</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        textarea {
            width: 100%;
            height: 260px;
            font-family: ui-monospace, monospace
        }

        .chip {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid var(--border);
            border-radius: 16px;
            margin: 2px;
            background: var(--bg-soft)
        }

        .grid3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px
        }
    </style>
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <h1>Créer un modèle à partir d’un lot de reçus</h1>
        <?php if (isset($_GET['imported'])): ?><p class="ok">Importé: <?= (int)$_GET['imported'] ?> fichier(s). Session: <?= htmlspecialchars($sessionId ?? '') ?></p><?php endif; ?>
        <?php if ($msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
        <?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

        <div class="card">
            <h3>1) Importer les reçus (positifs)</h3>
            <form method="post" enctype="multipart/form-data" class="row">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="files[]" accept="image/*" multiple required>
                <button class="btn">Importer</button>
            </form>
            <div class="muted" style="margin-top:6px">
                Astuces: si vous importez > 50 images, l'étape suivante n’en traitera qu’un échantillon (modifiable).
                Pensez à vérifier dans php.ini: upload_max_filesize, post_max_size, max_file_uploads, max_execution_time.
            </div>
            <?php if (!empty($docs)): ?>
                <div style="margin-top:8px">
                    <strong>Fichiers importés:</strong> <?= count($docs) ?>
                    <details style="margin-top:6px">
                        <summary>Voir la liste</summary>
                        <ul>
                            <?php foreach (array_slice($docs, 0, 50) as $d): ?>
                                <li class="muted" style="font-size:12px"><?= htmlspecialchars(basename($d['path'])) ?> (<?= (int)$d['w'] ?>×<?= (int)$d['h'] ?>)</li>
                            <?php endforeach; ?>
                            <?php if (count($docs) > 50): ?><li>…</li><?php endif; ?>
                        </ul>
                    </details>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($docs) || $sessionId): ?>
            <div class="card" style="margin-top:12px">
                <h3>2) Générer des suggestions</h3>
                <form method="post" class="row">
                    <input type="hidden" name="action" value="suggest">
                    <input type="hidden" name="session" value="<?= htmlspecialchars($sessionId ?? '') ?>">
                    <label>Seuil présence (par ex. 0.6)</label>
                    <input name="min_ratio" value="0.6" style="width:100px">
                    <label>Limite de docs</label>
                    <input name="limit" value="50" style="width:90px">
                    <button class="btn">Suggérer</button>
                </form>
                <?php if ($sessionId): ?>
                    <div class="muted">Session: <?= htmlspecialchars($sessionId) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $existingTemplates = list_templates_fs(); ?>
        <?php if (!empty($suggest['keywords']) || !empty($suggest['rois'])): ?>
            <div class="card" style="margin-top:12px">
                <h3>3) Prévisualiser et enregistrer</h3>
                <div class="row" style="gap:12px; align-items:flex-start">
                    <div class="col" style="flex:1">
                        <h4>Éditeur simple</h4>
                        <form method="post">
                            <input type="hidden" name="action" value="save_form">
                            <div class="grid2">
                                <div>
                                    <label>Nom</label>
                                    <input name="name" placeholder="Nom du modèle">
                                </div>
                                <div>
                                    <label>Mode d’enregistrement</label>
                                    <div>
                                        <label><input type="radio" name="save_mode" value="new" checked> Créer un nouveau</label>
                                        <label style="margin-left:12px"><input type="radio" name="save_mode" value="overwrite"> Remplacer existant</label>
                                    </div>
                                </div>
                            </div>
                            <div class="grid2" style="margin-top:6px">
                                <div>
                                    <label>Clé (si nouveau)</label>
                                    <input name="key" placeholder="ex: vinko_receipt">
                                </div>
                                <div>
                                    <label>Modèle à remplacer</label>
                                    <select name="overwrite_key">
                                        <option value="">-- choisir --</option>
                                        <?php foreach ($existingTemplates as $t): ?>
                                            <option value="<?= htmlspecialchars($t['key']) ?>"><?= htmlspecialchars($t['key'] . ' — ' . $t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <h5 style="margin-top:12px">Mots-clés (sélectionnez ce qui revient souvent)</h5>
                            <div>
                                <?php foreach ($suggest['keywords'] as $kw): ?>
                                    <label class="chip"><input type="checkbox" name="kw[]" value="<?= htmlspecialchars($kw) ?>" checked> <?= htmlspecialchars($kw) ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:6px">
                                <input name="kw[]" placeholder="Ajouter un mot-clé" style="width:240px">
                            </div>

                            <h5 style="margin-top:12px">Zones (ROI)</h5>
                            <div class="grid3">
                                <?php $previewDoc = $docs[0]['path'] ?? null; ?>
                                <?php foreach ($suggest['rois'] as $i => $r): ?>
                                    <?php
                                    $dataUri = ($previewDoc && isset($r['w'], $r['h']) && $r['w'] > 0 && $r['h'] > 0)
                                        ? crop_preview_datauri($previewDoc, (float)$r['x'], (float)$r['y'], (float)$r['w'], (float)$r['h'])
                                        : null;
                                    ?>
                                    <div class="card" style="padding:8px">
                                        <?php if ($dataUri): ?>
                                            <img src="<?= $dataUri ?>" alt="roi" style="max-width:100%">
                                        <?php endif; ?>
                                        <div style="margin-top:6px">
                                            <label>Nom</label>
                                            <input name="roi_name[]" value="<?= htmlspecialchars($r['name'] ?? ('roi' . ($i + 1))) ?>">
                                        </div>
                                        <div class="grid2">
                                            <div><label>x</label><input name="roi_x[]" value="<?= htmlspecialchars((string)($r['x'] ?? 0)) ?>"></div>
                                            <div><label>y</label><input name="roi_y[]" value="<?= htmlspecialchars((string)($r['y'] ?? 0)) ?>"></div>
                                        </div>
                                        <div class="grid2">
                                            <div><label>w</label><input name="roi_w[]" value="<?= htmlspecialchars((string)($r['w'] ?? 0)) ?>"></div>
                                            <div><label>h</label><input name="roi_h[]" value="<?= htmlspecialchars((string)($r['h'] ?? 0)) ?>"></div>
                                        </div>
                                        <div class="grid2">
                                            <div><label>psm</label><input name="roi_psm[]" value="<?= htmlspecialchars((string)($r['psm'] ?? 7)) ?>"></div>
                                            <div><label>regex</label><input name="roi_regex[]" value="<?= htmlspecialchars((string)($r['regex'] ?? '')) ?>" placeholder="ex: ~\\bvinko\\b~iu"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="row" style="margin-top:10px">
                                <label>Regex requises (clé => motif, JSON)</label>
                                <input type="text" name="rx_required[numero]" value="<?= htmlspecialchars($suggest['regex_required']['numero'] ?? '~\\b(n[°o]|num[eé]ro)\\b~iu') ?>" style="flex:1">
                            </div>
                            <div class="row" style="margin-top:10px">
                                <button class="btn">Enregistrer (mode choisi)</button>
                            </div>
                        </form>
                    </div>
                    <div class="col" style="flex:1">
                        <h4>JSON brut</h4>
                        <?php
                        $config = [
                            'keywords_required' => $suggest['keywords'],
                            'regex_required' => $suggest['regex_required'],
                            'rois' => $suggest['rois'],
                        ];
                        ?>
                        <form method="post">
                            <input type="hidden" name="action" value="save">
                            <div class="grid2">
                                <div>
                                    <label>Clé du modèle</label>
                                    <input name="key" placeholder="ex: vinko_receipt" required>
                                </div>
                                <div>
                                    <label>Nom</label>
                                    <input name="name" placeholder="Nom du modèle">
                                </div>
                            </div>
                            <div>
                                <label>Configuration (JSON)</label>
                                <textarea name="config_json"><?= htmlspecialchars(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            </div>
                            <button class="btn">Enregistrer le modèle</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
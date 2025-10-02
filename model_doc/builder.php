<?php
require_once __DIR__ . '/../model/Config.php';
require_once __DIR__ . '/../model/DocumentTemplateFileRepository.php';

$msg = null;
$err = null;
$imagePath = null;
$boxes = [];

function run_tesseract_tsv(string $img): array
{
    $tess = AppConfig::ocrTesseractPath();
    if (!is_file($tess)) return [];
    $lang = AppConfig::ocrLang();
    $psm  = AppConfig::ocrPsm();
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
        $level = (int)($parts[$idx['level']] ?? 0);
        $text  = (string)($parts[$idx['text']] ?? '');
        $conf  = (int)($parts[$idx['conf']] ?? -1);
        $l = (int)($parts[$idx['left']] ?? 0);
        $t = (int)($parts[$idx['top']] ?? 0);
        $w = (int)($parts[$idx['width']] ?? 0);
        $h = (int)($parts[$idx['height']] ?? 0);
        if ($w > 0 && $h > 0 && trim($text) !== '') {
            $boxes[] = ['level' => $level, 'left' => $l, 'top' => $t, 'width' => $w, 'height' => $h, 'text' => $text, 'conf' => $conf];
        }
    }
    return $boxes;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $err = 'Image requise';
        } else {
            $tmp = $_FILES['image']['tmp_name'];
            $dir = __DIR__ . '/uploads/';
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = 'png';
            $dest = $dir . 'sample_' . time() . '.' . $ext;
            if (!@move_uploaded_file($tmp, $dest)) {
                $err = "Échec upload";
            } else {
                $imagePath = $dest;
                $boxes = run_tesseract_tsv($imagePath);
                if (!$boxes) $msg = 'Aucun texte détecté ou TSV vide.';
            }
        }
    } elseif ($action === 'save_model') {
        $key = trim($_POST['key'] ?? '');
        $name = trim($_POST['name'] ?? $key);
        $imgW = (int)($_POST['img_w'] ?? 0);
        $imgH = (int)($_POST['img_h'] ?? 0);
        $keywords = json_decode($_POST['keywords_json'] ?? '[]', true) ?: [];
        $rois = json_decode($_POST['rois_json'] ?? '[]', true) ?: [];
        if ($key === '') {
            $err = 'Clé requise';
        } else if ($imgW <= 0 || $imgH <= 0) {
            $err = 'Dimensions image manquantes';
        } else {
            $normRois = [];
            foreach ($rois as $r) {
                $x = max(0, min(1, ($r['x'] ?? 0)));
                $y = max(0, min(1, ($r['y'] ?? 0)));
                $w = max(0, min(1, ($r['w'] ?? 0)));
                $h = max(0, min(1, ($r['h'] ?? 0)));
                $psm = isset($r['psm']) ? (int)$r['psm'] : 7;
                $regex = (string)($r['regex'] ?? '');
                $nameR = (string)($r['name'] ?? 'roi');
                $normRois[] = ['name' => $nameR, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'psm' => $psm, 'regex' => $regex];
            }
            $cfg = [
                'keywords_required' => array_values(array_unique(array_filter(array_map('strval', $keywords)))),
                'regex_required' => [],
                'rois' => $normRois,
            ];
            $fs = new DocumentTemplateFileRepository();
            $ok = $fs->upsert($key, $name, $cfg);
            if ($ok) $msg = 'Modèle enregistré: ' . htmlspecialchars($key);
            else $err = 'Échec écriture du modèle';
        }
    }
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Créer un modèle OCR</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .stage {
            position: relative;
            background: var(--bg-soft);
            border: 1px solid var(--border);
            border-radius: 12px;
            height: 75vh;
            overflow: auto
        }

        .stage-inner {
            position: relative;
            display: inline-block
        }

        #img {
            display: block;
            max-width: none
        }

        .overlay {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            pointer-events: none
        }

        .box {
            position: absolute;
            border: 1px dashed rgba(0, 255, 136, .6);
            background: rgba(0, 255, 136, .12);
            pointer-events: auto;
            cursor: pointer
        }

        .box.sel {
            border-color: #fff;
            background: rgba(255, 255, 255, .15)
        }

        .roi {
            border: 1px solid var(--accent-2);
            background: rgba(0, 255, 136, .08);
            position: absolute
        }
    </style>
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <h1>Créer un modèle à partir d’une image</h1>
        <?php if ($msg): ?><p class="ok"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
        <?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>
        <div class="card" style="margin-bottom:12px">
            <form method="post" enctype="multipart/form-data" class="row">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="image" accept="image/*">
                <button class="btn">Charger l’image</button>
                <a class="btn" href="index.php">Retour dashboard</a>
            </form>
        </div>
        <div class="row">
            <div class="col card">
                <h3>Aperçu et détection</h3>
                <div class="stage" id="stage">
                    <?php if ($imagePath): $rel = str_replace(dirname(__DIR__), '..', $imagePath); ?>
                        <div class="stage-inner">
                            <img id="img" src="<?= htmlspecialchars($rel) ?>" alt="image">
                            <div class="overlay" id="overlay"></div>
                        </div>
                    <?php else: ?>
                        <p>Chargez une image pour commencer.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col card">
                <h3>Modèle</h3>
                <form method="post" id="formSave">
                    <input type="hidden" name="action" value="save_model">
                    <div class="grid2">
                        <div>
                            <label>Clé</label>
                            <input name="key" placeholder="ex: vinko_receipt" required>
                        </div>
                        <div>
                            <label>Nom</label>
                            <input name="name" placeholder="Nom du modèle">
                        </div>
                    </div>
                    <div>
                        <label>Mots-clés sélectionnés</label>
                        <div id="keywords" class="panel"></div>
                        <input type="hidden" name="keywords_json" id="keywords_json">
                    </div>
                    <div>
                        <label>ROI (dessiner sur l’image)</label>
                        <div id="rois" class="panel"></div>
                        <input type="hidden" name="rois_json" id="rois_json">
                    </div>
                    <input type="hidden" name="img_w" id="img_w">
                    <input type="hidden" name="img_h" id="img_h">
                    <div class="row">
                        <button class="btn" <?= $imagePath ? '' : 'disabled' ?>>Enregistrer le modèle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const imagePath = <?= json_encode($imagePath) ?>;
        const boxes = <?= json_encode($boxes, JSON_UNESCAPED_UNICODE) ?>;
        let selectedKeywords = [];
        let rois = [];
        let imgW = 0,
            imgH = 0;

        function renderBoxes() {
            const overlay = document.getElementById('overlay');
            const img = document.getElementById('img');
            if (!overlay || !img) return;
            overlay.innerHTML = '';
            boxes.forEach((b, i) => {
                const el = document.createElement('div');
                el.className = 'box';
                el.style.left = (b.left) + 'px';
                el.style.top = (b.top) + 'px';
                el.style.width = (b.width) + 'px';
                el.style.height = (b.height) + 'px';
                el.title = b.text;
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const idx = selectedKeywords.indexOf(b.text);
                    if (idx >= 0) {
                        selectedKeywords.splice(idx, 1);
                        el.classList.remove('sel');
                    } else {
                        selectedKeywords.push(b.text);
                        el.classList.add('sel');
                    }
                    syncPanel();
                });
                overlay.appendChild(el);
            });
        }

        function syncPanel() {
            const k = document.getElementById('keywords');
            k.innerHTML = '';
            selectedKeywords.forEach(t => {
                const span = document.createElement('span');
                span.className = 'tag';
                span.textContent = t;
                k.appendChild(span);
            });
            document.getElementById('keywords_json').value = JSON.stringify(selectedKeywords);
            document.getElementById('rois_json').value = JSON.stringify(rois);
            document.getElementById('img_w').value = imgW;
            document.getElementById('img_h').value = imgH;
            const r = document.getElementById('rois');
            r.innerHTML = '';
            rois.forEach((rr, ix) => {
                const line = document.createElement('div');
                line.className = 'tag';
                line.textContent = `${rr.name||'roi'} x:${(rr.x*100).toFixed(1)}% y:${(rr.y*100).toFixed(1)}% w:${(rr.w*100).toFixed(1)}% h:${(rr.h*100).toFixed(1)}%`;
                r.appendChild(line);
            });
        }

        function enableRoiDrawing() {
            const stage = document.getElementById('stage');
            const img = document.getElementById('img');
            if (!img) return;
            let start = null;
            let roiDiv = null;
            stage.addEventListener('mousedown', (e) => {
                const rect = img.getBoundingClientRect();
                const within = e.clientX >= rect.left && e.clientX <= rect.right && e.clientY >= rect.top && e.clientY <= rect.bottom;
                if (!within) return;
                start = {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };
                roiDiv = document.createElement('div');
                roiDiv.className = 'roi';
                roiDiv.style.left = start.x + 'px';
                roiDiv.style.top = start.y + 'px';
                roiDiv.style.width = '0px';
                roiDiv.style.height = '0px';
                img.parentElement.appendChild(roiDiv);
            });
            stage.addEventListener('mousemove', (e) => {
                if (!start || !roiDiv) return;
                const rect = img.getBoundingClientRect();
                const x = Math.min(Math.max(0, e.clientX - rect.left), rect.width);
                const y = Math.min(Math.max(0, e.clientY - rect.top), rect.height);
                roiDiv.style.left = Math.min(start.x, x) + 'px';
                roiDiv.style.top = Math.min(start.y, y) + 'px';
                roiDiv.style.width = Math.abs(x - start.x) + 'px';
                roiDiv.style.height = Math.abs(y - start.y) + 'px';
            });
            stage.addEventListener('mouseup', () => {
                if (!start || !roiDiv) return;
                const x = parseFloat(roiDiv.style.left);
                const y = parseFloat(roiDiv.style.top);
                const w = parseFloat(roiDiv.style.width);
                const h = parseFloat(roiDiv.style.height);
                const nx = x / imgW,
                    ny = y / imgH,
                    nw = w / imgW,
                    nh = h / imgH;
                const name = prompt('Nom de la zone (ex: top-right)');
                const regex = prompt('Regex attendue (optionnel)');
                rois.push({
                    name: name || 'roi',
                    x: nx,
                    y: ny,
                    w: nw,
                    h: nh,
                    psm: 7,
                    regex: regex || ''
                });
                roiDiv = null;
                start = null;
                syncPanel();
            });
        }

        window.addEventListener('load', () => {
            const img = document.getElementById('img');
            if (img) {
                img.addEventListener('load', () => {
                    imgW = img.naturalWidth || img.width;
                    imgH = img.naturalHeight || img.height;
                    renderBoxes();
                    syncPanel();
                    enableRoiDrawing();
                });
                if (img.complete) {
                    imgW = img.naturalWidth || img.width;
                    imgH = img.naturalHeight || img.height;
                    renderBoxes();
                    syncPanel();
                    enableRoiDrawing();
                }
            }
        });
    </script>
</body>

</html>
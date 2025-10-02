<?php

require_once __DIR__ . '/OcrReceiptAnalyzer.php';
require_once __DIR__ . '/DocumentTemplateRepository.php';
require_once __DIR__ . '/DocumentTemplateFileRepository.php';

/**
 * Service générique de vérification de documents OCR
 * - Charge un modèle (template) configurable depuis la base
 * - Utilise l'OCR pour extraire du texte
 * - Applique des règles (mots-clés, regex, zones ROI optionnelles, validateurs personnalisés)
 */
class DocumentVerifier
{
    private DocumentTemplateRepository $templatesDb;
    private DocumentTemplateFileRepository $templatesFs;
    private OcrReceiptAnalyzer $ocr;

    public function __construct(?DocumentTemplateRepository $templates = null, ?OcrReceiptAnalyzer $ocr = null)
    {
        $this->templatesDb = $templates ?: new DocumentTemplateRepository();
        $this->templatesFs = new DocumentTemplateFileRepository();
        $this->ocr = $ocr ?: new OcrReceiptAnalyzer();
    }

    /**
     * Vérifie un fichier image contre un modèle.
     * @param string $templateKey: identifiant unique du modèle
     * @param string $imagePath: chemin du fichier uploadé
     * @param array $context: options supplémentaires (ex: valeurs attendues facultatives)
     * @return array{ok: bool, text?: string, details?: array, errors?: array}
     */
    public function verify(string $templateKey, string $imagePath, array $context = []): array
    {
        // Priorité: fichiers JSON si présents, sinon DB
        $tpl = $this->templatesFs->getByKey($templateKey);
        if (!$tpl) {
            $tpl = $this->templatesDb->getByKey($templateKey);
        }
        if (!$tpl) {
            return ['ok' => false, 'errors' => ['Modèle introuvable'], 'details' => []];
        }
        $cfg = is_array($tpl['config'] ?? null) ? $tpl['config'] : (json_decode($tpl['config'] ?? '{}', true) ?: []);

        // 1) OCR texte brut
        $ocr = $this->ocr->ocrImage($imagePath);
        if (!($ocr['ok'] ?? false)) {
            return ['ok' => false, 'errors' => ['OCR échoué', $ocr['error'] ?? ''], 'details' => $ocr];
        }
        $text = (string)$ocr['text'];

        // 2) Règles génériques sur texte complet
        $errors = [];
        $details = [
            'template' => $tpl['key'],
            'name' => $tpl['name'],
            'keywords_missing' => [],
            'regex_matches' => [],
            'roi' => [],
            'signals' => [
                'constraints' => 0,
                'matches' => 0,
                'keywords_total' => 0,
                'keywords_matched' => 0,
                'regex_total' => 0,
                'regex_matched' => 0,
                'roi_regex_total' => 0,
                'roi_regex_matched' => 0,
            ],
        ];

        // Mots-clés obligatoires
        $keywords = $cfg['keywords_required'] ?? [];
        if (!empty($keywords)) {
            $norm = $this->normalize($text);
            $details['signals']['keywords_total'] = count($keywords);
            foreach ($keywords as $kw) {
                $kwn = $this->normalize((string)$kw);
                if ($kwn === '') continue;
                if (mb_strpos($norm, $kwn) === false) {
                    $details['keywords_missing'][] = $kw;
                } else {
                    $details['signals']['keywords_matched']++;
                }
            }
            if (!empty($details['keywords_missing'])) {
                $errors[] = 'Mots-clés manquants: ' . implode(', ', $details['keywords_missing']);
            }
            $details['signals']['constraints'] += count($keywords);
            $details['signals']['matches'] += $details['signals']['keywords_matched'];
        }

        // Expressions régulières devant matcher (au moins une fois chacune)
        $regexes = $cfg['regex_required'] ?? [];
        if (!empty($regexes)) {
            $details['signals']['regex_total'] = count($regexes);
            foreach ($regexes as $label => $pattern) {
                $pattern = (string)$pattern;
                $ok = @preg_match($pattern, $text) === 1;
                $details['regex_matches'][$label] = $ok;
                if ($ok) $details['signals']['regex_matched']++;
                if (!$ok) $errors[] = 'Motif non trouvé: ' . $label;
            }
            $details['signals']['constraints'] += count($regexes);
            $details['signals']['matches'] += $details['signals']['regex_matched'];
        }

        // 3) Règles ROI optionnelles (ex: top-right pour N°)
        $rois = $cfg['rois'] ?? [];
        foreach ($rois as $roiDef) {
            // { name, x, y, w, h } exprimés en pourcentage [0,1] depuis top-left
            $name = (string)($roiDef['name'] ?? 'roi');
            $x = (float)($roiDef['x'] ?? 0.0);
            $y = (float)($roiDef['y'] ?? 0.0);
            $w = (float)($roiDef['w'] ?? 1.0);
            $h = (float)($roiDef['h'] ?? 1.0);
            $psm = isset($roiDef['psm']) ? (int)$roiDef['psm'] : 7;
            $regex = (string)($roiDef['regex'] ?? '');

            $crop = $this->cropPercent($ocr['preprocessed'] ?? $imagePath, $x, $y, $w, $h);
            $roiText = $crop ? $this->ocrCrop($crop, $psm) : null;
            $details['roi'][] = [
                'name' => $name,
                'x' => $x,
                'y' => $y,
                'w' => $w,
                'h' => $h,
                'psm' => $psm,
                'image' => $crop,
                'text' => $roiText,
                'regex' => $regex,
            ];
            if ($regex !== '') {
                $details['signals']['roi_regex_total']++;
                $ok = $roiText ? (@preg_match($regex, $roiText) === 1) : false;
                if ($ok) $details['signals']['roi_regex_matched']++;
                if (!$ok) $errors[] = 'ROI "' . $name . '" ne correspond pas au motif attendu';
            }
        }

        // Consolider les signaux pour éviter les faux positifs: un modèle sans contraintes ne doit pas matcher tout
        $details['signals']['constraints'] += $details['signals']['roi_regex_total'];
        $details['signals']['matches'] += $details['signals']['roi_regex_matched'];

        if ($details['signals']['constraints'] === 0) {
            $errors[] = 'Modèle sans contraintes (aucun mot-clé, regex ou ROI avec regex).';
        } elseif ($details['signals']['matches'] === 0) {
            // Si aucune contrainte n’a été satisfaite, considérer comme non reconnu
            $errors[] = 'Aucune contrainte satisfaite pour ce document.';
        }

        $ok = count($errors) === 0;
        return [
            'ok' => $ok,
            'text' => $text,
            'details' => $details,
            'errors' => $errors,
        ];
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (class_exists('Normalizer') && function_exists('normalizer_normalize')) {
            $s = normalizer_normalize($s, Normalizer::FORM_D);
            $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string)$s);
        }
        return preg_replace('/\s+/', ' ', trim($s));
    }

    private function cropPercent(string $imagePath, float $x, float $y, float $w, float $h): ?string
    {
        $dir = dirname($imagePath);
        $base = pathinfo($imagePath, PATHINFO_FILENAME);
        $out = $dir . DIRECTORY_SEPARATOR . $base . sprintf('_roi_%d_%d_%d_%d.png', (int)round($x * 100), (int)round($y * 100), (int)round($w * 100), (int)round($h * 100));

        // Imagick
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
                $im->writeImage($out);
                $im->clear();
                $im->destroy();
                return is_file($out) ? $out : null;
            } catch (\Throwable $e) { /* fallback GD */
            }
        }
        // GD
        $src = null;
        $mime = function_exists('mime_content_type') ? strtolower((string)@mime_content_type($imagePath)) : null;
        if (function_exists('imagecreatefromjpeg') && (($mime && strpos($mime, 'jpeg') !== false) || preg_match('/\.(jpe?g)$/i', $imagePath))) {
            $src = @imagecreatefromjpeg($imagePath);
        } elseif (function_exists('imagecreatefrompng') && (($mime && strpos($mime, 'png') !== false) || preg_match('/\.(png)$/i', $imagePath))) {
            $src = @imagecreatefrompng($imagePath);
        } elseif (function_exists('imagecreatefromwebp') && (($mime && strpos($mime, 'webp') !== false) || preg_match('/\.(webp)$/i', $imagePath))) {
            $src = @imagecreatefromwebp($imagePath);
        }
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
            @imagepng($dst, $out, 6);
            imagedestroy($dst);
            imagedestroy($src);
            return is_file($out) ? $out : null;
        }
        return null;
    }

    private function ocrCrop(string $imagePath, int $psm = 7): ?string
    {
        $tess = AppConfig::ocrTesseractPath();
        if (!is_file($tess)) return null;
        $lang = AppConfig::ocrLang();
        $lang = preg_replace('/[^a-zA-Z+_]/', '', (string)$lang) ?: 'eng';
        $tessdata = rtrim(dirname($tess), "\\/") . DIRECTORY_SEPARATOR . 'tessdata';
        $cmd = '"' . $tess . '" ' . '"' . $imagePath . '" stdout --psm ' . $psm . ' -l ' . $lang . ' --oem 1 --dpi 300';
        if (is_dir($tessdata)) $cmd .= ' --tessdata-dir ' . '"' . $tessdata . '"';
        $out = shell_exec($cmd . ' 2>&1');
        if ($out === null) return null;
        $text = trim(str_replace("\r", '', $out));
        return $text !== '' ? $text : null;
    }
}

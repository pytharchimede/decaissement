<?php
require_once __DIR__ . '/Config.php';

/**
 * OCR/IA Receipt Analyzer
 * - Fait un OCR via Tesseract (exécutable) et applique des règles scalables.
 * - Extrait des entités clés: montant, nom station.
 * - Vérifie: station doit être VINKO (tolérant aux variantes OCR), montant attendu.
 */
class OcrReceiptAnalyzer
{
    /**
     * Dernière image utilisée pour l'OCR (prétraitée si applicable)
     * Utilisée pour des passes ciblées (ROI) si nécessaire
     */
    private ?string $lastImagePath = null;
    /**
     * Pré-traitement optionnel de l'image pour améliorer l'OCR.
     * - Auto-orientation via EXIF
     * - Niveaux de gris, contraste, débruitage, deskew (si Imagick)
     * - Redimensionnement raisonnable
     * Sauvegarde en PNG à côté du fichier d'origine avec suffixe _prep.png
     * Retourne le chemin du fichier prétraité ou null si échec/non supporté.
     */
    private function preprocessImage(string $imagePath): ?string
    {
        $dir = dirname($imagePath);
        $base = pathinfo($imagePath, PATHINFO_FILENAME);
        $prep = $dir . DIRECTORY_SEPARATOR . $base . '_prep.png';

        // Essayer avec Imagick si disponible
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();
                $im->readImage($imagePath);
                // Auto orientation (EXIF)
                if (method_exists($im, 'autoOrient')) {
                    $im->autoOrient();
                }
                // Convertir en niveaux de gris
                if (method_exists($im, 'setImageColorspace')) {
                    $im->setImageColorspace(Imagick::COLORSPACE_GRAY);
                }
                // Débruitage léger
                if (method_exists($im, 'despeckleImage')) {
                    @$im->despeckleImage();
                }
                // Amélioration/contraste
                if (method_exists($im, 'enhanceImage')) {
                    @$im->enhanceImage();
                }
                if (method_exists($im, 'contrastStretchImage')) {
                    @$im->contrastStretchImage(0.1, 0.9);
                }
                // Deskew si disponible
                if (method_exists($im, 'deskewImage')) {
                    @$im->deskewImage(0.4);
                }
                // Redimensionnement
                $w = $im->getImageWidth();
                $h = $im->getImageHeight();
                $maxDim = 2400;
                $minDim = 900;
                if (max($w, $h) > $maxDim) {
                    $ratio = min($maxDim / $w, $maxDim / $h);
                    $im->resizeImage((int)($w * $ratio), (int)($h * $ratio), Imagick::FILTER_LANCZOS, 1);
                } elseif (max($w, $h) < $minDim) {
                    $ratio = min($minDim / $w, $minDim / $h);
                    $im->resizeImage((int)($w * $ratio), (int)($h * $ratio), Imagick::FILTER_LANCZOS, 1);
                }
                $im->setImageFormat('png');
                $im->writeImage($prep);
                $im->clear();
                $im->destroy();
                if (is_file($prep)) return $prep;
            } catch (\Throwable $e) {
                // fallback GD
            }
        }

        // Fallback GD
        if (function_exists('imagecreatefromjpeg')) {
            $src = null;
            $mime = null;
            if (function_exists('mime_content_type')) {
                $mime = strtolower((string)@mime_content_type($imagePath));
            }
            if (($mime && strpos($mime, 'jpeg') !== false) || preg_match('/\.(jpe?g)$/i', $imagePath)) {
                $src = @imagecreatefromjpeg($imagePath);
                // Auto orientation via EXIF
                if ($src && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($imagePath);
                    if (!empty($exif['Orientation'])) {
                        $orientation = (int)$exif['Orientation'];
                        if ($orientation === 3) $src = imagerotate($src, 180, 0);
                        elseif ($orientation === 6) $src = imagerotate($src, -90, 0);
                        elseif ($orientation === 8) $src = imagerotate($src, 90, 0);
                    }
                }
            } elseif (function_exists('imagecreatefrompng') && (($mime && strpos($mime, 'png') !== false) || preg_match('/\.(png)$/i', $imagePath))) {
                $src = @imagecreatefrompng($imagePath);
            } elseif (function_exists('imagecreatefromwebp') && (($mime && strpos($mime, 'webp') !== false) || preg_match('/\.(webp)$/i', $imagePath))) {
                $src = @imagecreatefromwebp($imagePath);
            }
            if ($src) {
                // Niveaux de gris + contraste
                @imagefilter($src, IMG_FILTER_GRAYSCALE);
                @imagefilter($src, IMG_FILTER_CONTRAST, -10);
                // Redimensionnement raisonnable
                $w = imagesx($src);
                $h = imagesy($src);
                $maxDim = 2400;
                $minDim = 900;
                $dst = $src;
                if (max($w, $h) > $maxDim) {
                    $ratio = min($maxDim / $w, $maxDim / $h);
                    $nw = (int)($w * $ratio);
                    $nh = (int)($h * $ratio);
                    $tmp = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    $dst = $tmp;
                } elseif (max($w, $h) < $minDim) {
                    $ratio = min($minDim / $w, $minDim / $h);
                    $nw = (int)($w * $ratio);
                    $nh = (int)($h * $ratio);
                    $tmp = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    $dst = $tmp;
                }
                // Sauvegarde PNG
                @imagepng($dst, $prep, 6);
                if ($dst !== $src) imagedestroy($dst);
                imagedestroy($src);
                if (is_file($prep)) return $prep;
            }
        }
        return null;
    }

    /**
     * Méthode utilitaire: Vérifie un document via un modèle et retourne ok/erreurs.
     * Usage rapide depuis n'importe quel formulaire: OcrReceiptAnalyzer::verifyDocument($templateKey, $tmpPath)
     * @return array{ok: bool, errors?: array, details?: array, text?: string}
     */
    public static function verifyDocument(string $templateKey, string $imagePath): array
    {
        require_once __DIR__ . '/DocumentVerifier.php';
        $verifier = new DocumentVerifier();
        return $verifier->verify($templateKey, $imagePath, []);
    }

    /**
     * Lance Tesseract en ligne de commande pour extraire le texte brut.
     * @param string $imagePath Chemin du fichier image
     * @return array{ ok: bool, text?: string, error?: string }
     */
    public function ocrImage(string $imagePath): array
    {
        $imgReal = realpath($imagePath) ?: $imagePath;
        if (!is_file($imgReal)) {
            return ['ok' => false, 'error' => 'Image introuvable', 'image' => $imgReal];
        }
        $engine = method_exists('AppConfig', 'ocrEngine') ? AppConfig::ocrEngine() : 'tesseract';
        $lang = AppConfig::ocrLang();
        $psm  = AppConfig::ocrPsm();

        // Pré-traitement image (optionnel)
        $prepPath = $this->preprocessImage($imgReal);
        if ($prepPath && is_file($prepPath)) {
            $imgReal = $prepPath;
        }
        $this->lastImagePath = $imgReal;

        if ($engine === 'paddle') {
            // Paddle via service HTTP (client simple)
            require_once __DIR__ . '/OcrPaddleClient.php';
            $endpoint = getenv('PADDLE_OCR_ENDPOINT') ?: 'http://localhost:8080';
            $client = new OcrPaddleClient($endpoint);
            $res = $client->ocr($imgReal);
            if (!($res['ok'] ?? false)) {
                // fallback Tesseract si Paddle échoue/injoignable
                $engine = 'tesseract';
            } else {
                $this->lastImagePath = $imgReal;
                return ['ok' => true, 'text' => $res['text'] ?? '', 'preprocessed' => $prepPath ?? null, 'engine' => 'paddle'];
            }
        }

        // Tesseract (par défaut) – localisation robuste
        $wanted = AppConfig::ocrTesseractPath();
        $candidates = [];
        if ($wanted) $candidates[] = $wanted;
        // chemins standards Linux / macOS / déploiements custom
        $candidates = array_merge($candidates, [
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/bin/tesseract',
            '/opt/homebrew/bin/tesseract', // mac M1/M2
            dirname(__DIR__) . '/bin/tesseract',
        ]);
        $checked = [];
        $tess = null;
        foreach ($candidates as $cand) {
            $cand = rtrim($cand);
            if ($cand === '' || isset($checked[$cand])) continue;
            $checked[$cand] = true;
            if (is_file($cand)) {
                // Vérifier exécutable si possible
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || @is_executable($cand)) {
                    $tess = $cand;
                    break;
                }
            }
        }
        $disableFns = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $shellDisabled = in_array('shell_exec', $disableFns, true);
        if (!$tess && !$shellDisabled) {
            // tentative via which
            $which = @shell_exec('which tesseract 2>/dev/null');
            if ($which) {
                $which = trim($which);
                if ($which !== '' && is_file($which)) {
                    $tess = $which;
                    $checked[$which] = true;
                }
            }
        }
        if (!$tess) {
            $suggestions = [];
            if ($shellDisabled) {
                $suggestions[] = "La fonction shell_exec est désactivée sur l'hébergement mutualisé (disable_functions).";
            }
            $suggestions[] = "Vérifiez que Tesseract est installé côté serveur (ex: apt install tesseract-ocr).";
            $suggestions[] = "Si installation impossible (mutualisé), déployez un micro-service OCR (Docker) et basculez engine=\"paddle\" (variable 'ocr.engine').";
            $suggestions[] = "Ou uploadez un binaire statique dans /model/../bin/tesseract et mettez le chemin exact dans l'admin (ocr.tesseract.path).";
            $suggestions[] = "Réduisez la config de langue à 'eng' si les fichiers fra.traineddata manquent.";
            return [
                'ok' => false,
                'error' => 'Tesseract introuvable sur le serveur',
                'tesseract' => $wanted,
                'candidates_tested' => array_keys($checked),
                'disable_functions' => $disableFns,
                'shell_exec_disabled' => $shellDisabled,
                'suggestions' => $suggestions,
                'engine' => 'tesseract',
                'image' => $imgReal,
                'preprocessed' => $prepPath ?? null,
            ];
        }
        // Sanitize et commande compatible Windows (guillemets doubles)
        $psm = (int)$psm;
        if ($psm <= 0 || $psm > 13) {
            $psm = 6;
        }
        $lang = preg_replace('/[^a-zA-Z+_]/', '', (string)$lang) ?: 'eng';

        // Déduire tessdata
        $tessdata = rtrim(dirname($tess), "\\/") . DIRECTORY_SEPARATOR . 'tessdata';
        $langsMissing = [];
        if (is_dir($tessdata) && $lang) {
            foreach (explode('+', $lang) as $lg) {
                $lg = trim($lg);
                if ($lg === '') continue;
                $td = $tessdata . DIRECTORY_SEPARATOR . $lg . '.traineddata';
                if (!is_file($td)) $langsMissing[] = $lg;
            }
        }

        $cmd = '"' . $tess . '" ' . '"' . $imgReal . '"' . ' stdout --psm ' . $psm . ' -l ' . $lang . ' --oem 1 --dpi 300';
        if (is_dir($tessdata)) {
            $cmd .= ' --tessdata-dir ' . '"' . $tessdata . '"';
        }
        $out = shell_exec($cmd . ' 2>&1');
        if ($out === null) {
            return [
                'ok' => false,
                'error' => 'Échec OCR (shell_exec null). Vérifiez le chemin Tesseract et les permissions.',
                'cmd' => $cmd,
                'tesseract' => $tess,
                'tessdata_dir' => is_dir($tessdata) ? $tessdata : null,
                'langs_missing' => $langsMissing,
                'image' => $imgReal,
                'preprocessed' => $prepPath ?? null,
            ];
        }
        $text = trim(str_replace("\r", '', $out));
        $lower = mb_strtolower($text, 'UTF-8');
        if ($text === '' || strpos($lower, 'the system cannot find the path specified') !== false || strpos($lower, "n'est pas reconnu en tant que commande interne") !== false || strpos($lower, 'is not recognized as an internal or external command') !== false || strpos($lower, 'error opening data file') !== false || strpos($lower, 'le chemin d') !== false) {
            return [
                'ok' => false,
                'error' => $text !== '' ? $text : 'OCR vide',
                'cmd' => $cmd,
                'tesseract' => $tess,
                'tessdata_dir' => is_dir($tessdata) ? $tessdata : null,
                'langs_missing' => $langsMissing,
                'image' => $imgReal,
                'preprocessed' => $prepPath ?? null,
            ];
        }
        return ['ok' => true, 'text' => $text, 'preprocessed' => $prepPath ?? null, 'engine' => 'tesseract'];
    }

    /**
     * Normalise du texte OCR pour améliorer la robustesse (diacritiques, espaces, case).
     */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (class_exists('Normalizer') && function_exists('normalizer_normalize')) {
            $s = normalizer_normalize($s, Normalizer::FORM_D);
            $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string)$s);
        }
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /**
     * Extrait le montant (en FCFA) du texte OCR.
     * - Cherche des patterns de nombres avec milliers/espaces et mots clé (montant, total, fcfa, cfa)
     */
    public function extractAmount(string $text): ?int
    {
        $t = $this->normalize($text);
        // patterns de montants typiques
        $patterns = [
            '/montant[^0-9]{0,10}([0-9]{1,3}(?:[ .][0-9]{3})+)/u',
            '/total[^0-9]{0,10}([0-9]{1,3}(?:[ .][0-9]{3})+)/u',
            '/valeur[^0-9]{0,10}([0-9]{1,3}(?:[ .][0-9]{3})+)/u',
            '/bon\s+pour[^0-9]{0,10}([0-9]{1,3}(?:[ .][0-9]{3})+)/u',
            '/([0-9]{1,3}(?:[ .][0-9]{3})+)\s*(?:fcfa|cfa)/u',
            '/([0-9]{3,})\s*(?:fcfa|cfa)/u',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $t, $m)) {
                $raw = preg_replace('/[^0-9]/', '', $m[1]);
                if ($raw !== '' && ctype_digit($raw)) {
                    return (int)$raw;
                }
            }
        }
        // fallback: premier gros nombre à 4+ chiffres
        if (preg_match('/\b([0-9]{4,})\b/u', $t, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Détecte si la station semble être VINKO (avec tolérance OCR)
     */
    public function isStationVinko(string $text): bool
    {
        $t = $this->normalize($text);
        // Variantes tolérées: vinko, v1nko, vinco, vink0, viniko, etc.
        $candidates = [
            'vinko',
            'vinco',
            'v1nko',
            'vink0',
            'viniko',
            'vinko station',
            'station vinko',
        ];
        foreach ($candidates as $w) {
            if (mb_strpos($t, $w) !== false) return true;
        }
        // distance d’édition pour robustesse
        $tokens = preg_split('/[^a-z0-9]+/u', $t) ?: [];
        foreach ($tokens as $tk) {
            if ($tk === '') continue;
            if ($this->levenshteinUtf8($tk, 'vinko') <= 1) return true;
        }
        return false;
    }

    /**
     * Détecte la station avec détail de match pour affichage.
     * @return array{ matched: bool, match?: string, method?: string }
     */
    public function detectStationInfo(string $text): array
    {
        $t = $this->normalize($text);
        $candidates = [
            'vinko',
            'vinco',
            'v1nko',
            'vink0',
            'viniko',
            'vinko station',
            'station vinko'
        ];
        foreach ($candidates as $w) {
            if (mb_strpos($t, $w) !== false) {
                return ['matched' => true, 'match' => $w, 'method' => 'candidate'];
            }
        }
        $tokens = preg_split('/[^a-z0-9]+/u', $t) ?: [];
        foreach ($tokens as $tk) {
            if ($tk === '') continue;
            if ($this->levenshteinUtf8($tk, 'vinko') <= 1) {
                return ['matched' => true, 'match' => $tk, 'method' => 'fuzzy'];
            }
        }
        return ['matched' => false];
    }

    /**
     * Extraction heuristique du nom du chauffeur (ou conducteur/driver)
     */
    public function extractChauffeurName(string $text): ?string
    {
        $lines = preg_split('/\R/u', str_replace("\r", '', $text));
        if (!$lines) return null;
        foreach ($lines as $line) {
            if (preg_match('~\b(chauffeur|conducteur|driver)\b\s*[:\-]?\s*([\p{L}"\'\-\.\s]{2,60})~iu', $line, $m)) {
                $name = trim($m[2]);
                if ($name !== '' && mb_strlen($name, 'UTF-8') >= 2) return $name;
            }
        }
        $b = $this->extractBeneficiaireName($text);
        return $b;
    }

    /**
     * Extraction du nom du bénéficiaire si présent
     */
    public function extractBeneficiaireName(string $text): ?string
    {
        $lines = preg_split('/\R/u', str_replace("\r", '', $text));
        if (!$lines) return null;
        foreach ($lines as $line) {
            if (preg_match('~\b(b[eé]n[eé]ficiaire|beneficiaire|nom)\b\s*[:\-]?\s*([\p{L}"\'\-\.\s]{2,60})~iu', $line, $m)) {
                $name = trim($m[2]);
                if ($name !== '' && mb_strlen($name, 'UTF-8') >= 2) return $name;
            }
        }
        return null;
    }

    /**
     * Détection simple de la présence d’une signature (mots clés)
     */
    public function detectSignature(string $text): bool
    {
        $t = $this->normalize($text);
        foreach (['signature', 'signe', 'signé', 'visa', 'cachet'] as $k) {
            if (mb_strpos($t, $k) !== false) return true;
        }
        return false;
    }

    /**
     * Retourne des champs structurés extraits du texte OCR.
     * @return array{ amount: int|null, station: array }
     */
    public function extractStructured(string $ocrText): array
    {
        return [
            'amount' => $this->extractAmount($ocrText),
            'station' => $this->detectStationInfo($ocrText),
            'chauffeur_name' => $this->extractChauffeurName($ocrText),
            'beneficiaire_name' => $this->extractBeneficiaireName($ocrText),
            'signature_present' => $this->detectSignature($ocrText),
            'receipt_number' => $this->extractReceiptNumber($ocrText),
            'date' => $this->extractDate($ocrText),
            'quantity_liters' => $this->extractQuantityLiters($ocrText),
            'vehicle' => $this->extractVehicleId($ocrText),
        ];
    }

    /**
     * Extrait un numéro de reçu/ticket/facture probable
     */
    public function extractReceiptNumber(string $text): ?string
    {
        $text = str_replace("\r", '', $text);
        $lines = preg_split('/\n/u', $text) ?: [];
        // Heuristiques anti-faux positifs (ex: numéros de téléphone)
        $phoneLineRe = '~\b(tel|t[eé]l|fax|telephone|t[eé]l[eé]phone)\b~iu';
        $twoDigitGroupPhoneRe = '~(?:\d{2}\s+){3,}\d{2}~u';
        // 1) Recherche directe avec tolérance d'espaces entre caractères
        foreach ($lines as $i => $line) {
            // Cas "N°" / "No" / "Numero"
            if (preg_match('~\b(n[°o0]|num[eé]ro)\b~iu', $line)) {
                // Concaténer avec la ligne suivante si très courte (OCR cassé)
                $chunk = $line . ' ' . ($lines[$i + 1] ?? '');
                if (preg_match('~\b(n[°o]|num[eé]ro)\b[^A-Za-z0-9]{0,10}(([A-Z0-9][\s\-]?){3,20})~iu', $chunk, $m)) {
                    $raw = preg_replace('~[^A-Z0-9]~i', '', (string)($m[2] ?? ''));
                    if ($raw !== '' && strlen($raw) >= 3) return strtoupper($raw);
                }
                // Variante chiffres espacés: ex: 0 0 2 2
                if (preg_match('~\b(n[°o]|num[eé]ro)\b[^0-9]{0,10}((?:[0-9][\s\-]?){3,12})~iu', $chunk, $m2)) {
                    $raw = preg_replace('~[^0-9]~', '', (string)($m2[2] ?? ''));
                    if ($raw !== '' && strlen($raw) >= 3) return $raw;
                }
            }
            // Cas sans mot clé mais ligne du haut: une suite de chiffres espacés
            if ($i <= 4) { // top de page
                // Éviter lignes contenant des indices de téléphone
                if (preg_match($phoneLineRe, $line) || preg_match($twoDigitGroupPhoneRe, $line)) {
                    // ignorer probable numéro de téléphone
                } elseif (preg_match('~((?:[0-9][\s\-]?){3,7})~', $line, $mm)) {
                    $raw = preg_replace('~[^0-9]~', '', (string)($mm[1] ?? ''));
                    if ($raw !== '' && strlen($raw) >= 3) return $raw;
                }
            }
        }
        // 2) Fallback: OCR ROI (haut-droite) si image disponible
        if ($this->lastImagePath && is_file($this->lastImagePath)) {
            // D'abord un recadrage plus petit en haut-droit pour viser le cachet "N°"
            $roiText = $this->ocrCropTopRightText($this->lastImagePath, 0.45, 0.20);
            if (!$roiText) {
                // Puis un recadrage plus large en fallback
                $roiText = $this->ocrCropTopRightText($this->lastImagePath, 0.5, 0.35);
            }
            if ($roiText) {
                // Ré-essayer les mêmes motifs sur le texte ROI
                $roiLines = preg_split('/\n/u', str_replace("\r", '', $roiText)) ?: [];
                foreach ($roiLines as $i => $line) {
                    if (preg_match('~\b(n[°o0]|num[eé]ro)\b[^A-Za-z0-9]{0,10}(([A-Z0-9][\s\-]?){3,20})~iu', $line, $m)) {
                        $raw = preg_replace('~[^A-Z0-9]~i', '', (string)($m[2] ?? ''));
                        if ($raw !== '' && strlen($raw) >= 3) return strtoupper($raw);
                    }
                    // Éviter lignes avec indicateurs de téléphone et limiter à 3-7 chiffres
                    if (preg_match($phoneLineRe, $line) || preg_match($twoDigitGroupPhoneRe, $line)) {
                        continue;
                    }
                    if (preg_match('~((?:[0-9][\s\-]?){3,7})~', $line, $mm)) {
                        $raw = preg_replace('~[^0-9]~', '', (string)($mm[1] ?? ''));
                        if ($raw !== '' && strlen($raw) >= 3) return $raw;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Effectue un OCR ciblé sur une zone haut-droite de l'image pour le numéro de reçu.
     * Retourne le texte OCR de cette zone ou null.
     */
    private function ocrCropTopRightText(string $imagePath, float $wPct = 0.5, float $hPct = 0.35): ?string
    {
        $crop = $this->cropTopRight($imagePath, $wPct, $hPct);
        if (!$crop || !is_file($crop)) return null;
        // Construire commande Tesseract dédiée avec PSM 7
        $tess = AppConfig::ocrTesseractPath();
        if (!is_file($tess)) return null;
        $lang = AppConfig::ocrLang();
        $psm = 7;
        $lang = preg_replace('/[^a-zA-Z+_]/', '', (string)$lang) ?: 'eng';
        $tessdata = rtrim(dirname($tess), "\\/") . DIRECTORY_SEPARATOR . 'tessdata';
        $cmd = '"' . $tess . '" ' . '"' . $crop . '"' . ' stdout --psm ' . $psm . ' -l ' . $lang . ' --oem 1 --dpi 300';
        if (is_dir($tessdata)) {
            $cmd .= ' --tessdata-dir ' . '"' . $tessdata . '"';
        }
        $out = shell_exec($cmd . ' 2>&1');
        if ($out === null) return null;
        $text = trim(str_replace("\r", '', $out));
        return $text !== '' ? $text : null;
    }

    /**
     * Recadre la zone haut-droite de l'image et la sauvegarde en PNG temporaire.
     * Retourne le chemin du fichier recadré ou null.
     */
    private function cropTopRight(string $imagePath, float $wPct = 0.5, float $hPct = 0.35): ?string
    {
        $dir = dirname($imagePath);
        $base = pathinfo($imagePath, PATHINFO_FILENAME);
        $cropPath = $dir . DIRECTORY_SEPARATOR . $base . '_roi_topright.png';

        // Imagick si dispo
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();
                $im->readImage($imagePath);
                if (method_exists($im, 'autoOrient')) $im->autoOrient();
                $w = $im->getImageWidth();
                $h = $im->getImageHeight();
                $cw = max(1, (int)round($w * $wPct));
                $ch = max(1, (int)round($h * $hPct));
                $cx = max(0, $w - $cw);
                $cy = 0;
                if (method_exists($im, 'cropImage')) {
                    $im->cropImage($cw, $ch, $cx, $cy);
                    if (method_exists($im, 'setImagePage')) {
                        $im->setImagePage(0, 0, 0, 0); // reset page
                    }
                }
                if (method_exists($im, 'setImageColorspace')) $im->setImageColorspace(Imagick::COLORSPACE_GRAY);
                if (method_exists($im, 'enhanceImage')) @$im->enhanceImage();
                $im->setImageFormat('png');
                $im->writeImage($cropPath);
                $im->clear();
                $im->destroy();
                if (is_file($cropPath)) return $cropPath;
            } catch (\Throwable $e) {
                // fallback GD
            }
        }
        // GD fallback
        $src = null;
        $mime = null;
        if (function_exists('mime_content_type')) {
            $mime = strtolower((string)@mime_content_type($imagePath));
        }
        if (function_exists('imagecreatefromjpeg') && (($mime && strpos($mime, 'jpeg') !== false) || preg_match('/\.(jpe?g)$/i', $imagePath))) {
            $src = @imagecreatefromjpeg($imagePath);
        } elseif (function_exists('imagecreatefrompng') && (($mime && strpos($mime, 'png') !== false) || preg_match('/\.(png)$/i', $imagePath))) {
            $src = @imagecreatefrompng($imagePath);
        } elseif (function_exists('imagecreatefromwebp') && (($mime && strpos($mime, 'webp') !== false) || preg_match('/\.(webp)$/i', $imagePath))) {
            $src = @imagecreatefromwebp($imagePath);
        }
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $cw = max(1, (int)round($w * $wPct));
            $ch = max(1, (int)round($h * $hPct));
            $cx = max(0, $w - $cw);
            $cy = 0;
            $dst = imagecreatetruecolor($cw, $ch);
            imagecopy($dst, $src, 0, 0, $cx, $cy, $cw, $ch);
            // niveau de gris
            @imagefilter($dst, IMG_FILTER_GRAYSCALE);
            @imagefilter($dst, IMG_FILTER_CONTRAST, -10);
            @imagepng($dst, $cropPath, 6);
            imagedestroy($dst);
            imagedestroy($src);
            if (is_file($cropPath)) return $cropPath;
        }
        return null;
    }

    /**
     * Extrait une date au format dd/mm/yyyy (ou variantes - . et 2 chiffres pour l'année)
     */
    public function extractDate(string $text): ?string
    {
        $t = str_replace("\r", '', $text);
        // Autoriser du bruit non numérique entre les composantes de la date
        if (preg_match('~\b(\d{1,2})\D{0,5}(\d{1,2})\D{0,5}(\d{2,4})\b~u', $t, $m)) {
            $d = (int)$m[1];
            $M = (int)$m[2];
            $y = (int)$m[3];
            if ($y < 100) $y += 2000; // heuristique
            if (checkdate($M, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $M, $d);
            }
        }
        return null;
    }

    /**
     * Extrait une quantité (L)
     */
    public function extractQuantityLiters(string $text): ?float
    {
        $t = $this->normalize($text);
        if (preg_match('~\b(quantit[eé]|qte|volume|litres?)\b[^0-9]{0,6}([0-9]+(?:[\.,][0-9]+)?)~u', $t, $m)) {
            $num = str_replace(',', '.', $m[2]);
            return (float)$num;
        }
        if (preg_match('~\b([0-9]+(?:[\.,][0-9]+)?)\s*l\b~u', $t, $m)) {
            $num = str_replace(',', '.', $m[1]);
            return (float)$num;
        }
        return null;
    }

    /**
     * Extrait un identifiant véhicule probable (immatriculation/plaque)
     */
    public function extractVehicleId(string $text): ?string
    {
        $lines = preg_split('/\R/u', str_replace("\r", '', $text));
        if (!$lines) return null;
        foreach ($lines as $line) {
            if (preg_match('~\b(v[eé]hicule|vehicule|immat|immatriculation|plaque)\b[^A-Za-z0-9]{0,8}([A-Z0-9\-]{4,15})~iu', $line, $m)) {
                $val = trim($m[2]);
                if ($val !== '') return $val;
            }
        }
        // fallback: pattern direct style plaque
        if (preg_match('~\b([A-Z]{2,3}-?[0-9]{2,4}-?[A-Z]{1,3})\b~', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function levenshteinUtf8(string $a, string $b): int
    {
        // Simpliste: translit en ASCII basique pour distance
        $a = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $a) ?: $a;
        $b = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $b) ?: $b;
        return levenshtein($a, $b);
    }

    /**
     * Vérifie les règles de conformité et retourne un verdict structuré
     * @param string $ocrText texte OCR
     * @param array $rules [
     *   'expected_amount' => int|null,
     *   'check_station' => bool (default true)
     * ]
     * @return array{ ok: bool, checks: array, errors?: array }
     */
    public function validate(string $ocrText, array $rules): array
    {
        $checks = [];
        $errors = [];

        $amount = $this->extractAmount($ocrText);
        $checks['amount_extracted'] = $amount;

        if (isset($rules['expected_amount']) && $rules['expected_amount'] !== null) {
            $tol = isset($rules['amount_tolerance']) ? abs((int)$rules['amount_tolerance']) : 0;
            if ($amount === null) {
                $errors[] = 'Montant non détecté';
            } else if (abs((int)$rules['expected_amount'] - $amount) > $tol) {
                $errors[] = 'Montant différent de la valeur attendue (tolérance ' . $tol . ')';
            }
        }

        $checkStation = $rules['check_station'] ?? true;
        if ($checkStation) {
            $stationInfo = $this->detectStationInfo($ocrText);
            $checks['station_vinko'] = $stationInfo['matched'] ?? false;
            $checks['station_info'] = $stationInfo;
            if (!($stationInfo['matched'] ?? false)) {
                $errors[] = 'Nom de station non reconnu comme VINKO';
            }
        }

        // Numéro de reçu requis ?
        if (!empty($rules['require_receipt_number'])) {
            $rn = $this->extractReceiptNumber($ocrText);
            $checks['receipt_number'] = $rn;
            if (!$rn) {
                $errors[] = 'Numéro de reçu non détecté';
            }
        }

        // Mots-clés du template requis ?
        if (!empty($rules['check_template'])) {
            $tpl = $rules['template_keywords'] ?? ['bon pour', 'enlevement', 'quantite', 'valeur', 'cachet', 'signature'];
            $norm = $this->normalize($ocrText);
            $missing = [];
            foreach ($tpl as $kw) {
                $kwNorm = $this->normalize($kw);
                if (mb_strpos($norm, $kwNorm) === false) {
                    $missing[] = $kw;
                }
            }
            $checks['template_missing_keywords'] = $missing;
            if (!empty($rules['require_template']) && count($missing) > 0) {
                $errors[] = 'Mots-clés du reçu manquants: ' . implode(', ', $missing);
            }
        }

        // Date extraite raisonnable ?
        if (!empty($rules['date_max_age_days']) || !empty($rules['require_date'])) {
            $dateStr = $this->extractDate($ocrText);
            $checks['date_extracted'] = $dateStr;
            if (!$dateStr) {
                if (!empty($rules['require_date'])) {
                    $errors[] = 'Date non détectée';
                }
            } else if (!empty($rules['date_max_age_days'])) {
                $max = (int)$rules['date_max_age_days'];
                $ts = strtotime($dateStr . ' 00:00:00');
                if ($ts !== false) {
                    $today = strtotime(date('Y-m-d') . ' 00:00:00');
                    $deltaDays = abs(($today - $ts) / 86400);
                    $checks['date_age_days'] = $deltaDays;
                    if ($deltaDays > $max) {
                        $errors[] = 'Date trop éloignée (' . (int)$deltaDays . ' jours)';
                    }
                }
            }
        }

        // Signature requise ?
        if (!empty($rules['require_signature'])) {
            $sig = $this->detectSignature($ocrText);
            $checks['signature_present'] = $sig;
            if (!$sig) {
                $errors[] = 'Signature non détectée';
            }
        }

        return [
            'ok' => count($errors) === 0,
            'checks' => $checks,
            'errors' => $errors,
        ];
    }
}

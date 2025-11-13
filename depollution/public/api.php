<?php
// API Dépollution – fournit endpoints pour admin, opérateur1, opérateur2
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../model/Database.php';
$__db = (new Database())->getConnection();
// Migration légère : ajouter colonne generation_fiches si absente (avant toute logique qui pourrait la requêter)
try {
    $col = $__db->query("SHOW COLUMNS FROM depollution_voyage LIKE 'generation_fiches'")->fetch();
    if (!$col) {
        try {
            $__db->exec("ALTER TABLE depollution_voyage ADD COLUMN generation_fiches TINYINT DEFAULT 0");
        } catch (Throwable $eAlter) {
            // Ignorer si droits insuffisants
        }
    }
} catch (Throwable $eChkGen) { /* ignore */
}
// Migration décaissement: ajouter colonnes solde/solde_at/solde_ref si absentes
try {
    $col = $__db->query("SHOW COLUMNS FROM depollution_voyage LIKE 'solde'")->fetch();
    if (!$col) {
        try {
            $__db->exec("ALTER TABLE depollution_voyage ADD COLUMN solde TINYINT(1) DEFAULT 0");
        } catch (Throwable $e) {
        }
    }
    $col = $__db->query("SHOW COLUMNS FROM depollution_voyage LIKE 'solde_at'")->fetch();
    if (!$col) {
        try {
            $__db->exec("ALTER TABLE depollution_voyage ADD COLUMN solde_at DATETIME NULL");
        } catch (Throwable $e) {
        }
    }
    $col = $__db->query("SHOW COLUMNS FROM depollution_voyage LIKE 'solde_ref'")->fetch();
    if (!$col) {
        try {
            $__db->exec("ALTER TABLE depollution_voyage ADD COLUMN solde_ref VARCHAR(120) NULL");
        } catch (Throwable $e) {
        }
    }
} catch (Throwable $e) { /* ignore */
}
// (Optionnel) préparer table trace si absente - non bloquant
try {
    $__db->exec('CREATE TABLE IF NOT EXISTS depollution_fiche_trace (
        id INT AUTO_INCREMENT PRIMARY KEY,
        voyage_id INT NOT NULL,
        type_fiche VARCHAR(30) NOT NULL,
        num_fiche VARCHAR(50) NOT NULL,
        montant DOUBLE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $eTrace) { /* ignore */
}
unset($__db);

// ---------- Constantes métier ----------
if (!defined('DEPOLLUTION_CARBURANT_PRIX_LITRE')) {
    define('DEPOLLUTION_CARBURANT_PRIX_LITRE', 675); // CFA / litre
}

// ---------- Helpers Import Excel ----------
function excel_is_serial_date($v): bool
{
    if (!is_numeric($v)) return false;
    $n = (int)$v;
    return $n > 35000 && $n < 50000; // ~1995 - 2036
}
function excel_serial_to_date($n): ?DateTime
{
    if (!excel_is_serial_date($n)) return null;
    // Excel base (1900 system) = 1899-12-30
    $base = new DateTime('1899-12-30');
    return $base->modify('+' . (int)$n . ' days');
}
function detect_columns($sheet): array
{
    // Retourne mapping [date=>col, camion=>col, chauffeur=>col, bon=>col]
    $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $highestRow = min(200, $sheet->getHighestDataRow());
    $scores = [];
    for ($col = 1; $col <= $highestCol; $col++) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $scores[$letter] = ['date' => 0, 'camion' => 0, 'chauffeur' => 0, 'bon' => 0, 'samples' => []];
        for ($row = 2; $row <= $highestRow; $row++) {
            $valRaw = $sheet->getCell($letter . $row)->getValue();
            $val = trim((string)$sheet->getCell($letter . $row)->getFormattedValue());
            if ($val === '') continue;
            if (preg_match('~\b\d{2}/\d{2}/\d{4}\b~', $val) || preg_match('~\b\d{4}-\d{2}-\d{2}\b~', $val) || excel_is_serial_date($valRaw)) $scores[$letter]['date']++;
            if (preg_match('~[A-Z]{2,}\d|\d{2,}[A-Z]{2,}~', $val)) $scores[$letter]['camion']++;
            if (preg_match('~\bN°?\b|BON|^N\s+\d+~iu', $val)) $scores[$letter]['bon']++;
            // Heuristique chauffeur améliorée : nom(s) + éventuellement téléphone (groupes de 2 chiffres)
            if (
                preg_match('~[A-ZÉÈÊÂÎÙÔÇ][a-zéèêàâîôç]+\s+[A-ZA-ZÉÈÊÂÎÙÔÇ]~u', $val) || // deux mots au moins
                preg_match('~\d{2}\s\d{2}\s\d{2}\s\d{2}\s\d{2}~', $val) || // téléphone 10 chiffres formaté
                preg_match('~[A-Z]{3,}\s+[A-Z]{3,}~u', $val) // noms en majuscules
            ) {
                // Exclure colonnes clairement BON / NUMERO
                if (!preg_match('~\bBON\b|N°|NUMÉRO|NUMERO~iu', $val)) {
                    $scores[$letter]['chauffeur']++;
                }
            }
            $scores[$letter]['samples'][] = $val;
        }
    }
    // Heuristiques: choisir la colonne avec score le plus élevé pour chaque type, en évitant collisions.
    $mapping = ['date' => null, 'camion' => null, 'chauffeur' => null, 'bon' => null];
    foreach (['date', 'camion', 'chauffeur', 'bon'] as $type) {
        $best = null;
        $bestScore = 0;
        foreach ($scores as $col => $sc) {
            if ($sc[$type] > $bestScore && !in_array($col, $mapping, true)) {
                $bestScore = $sc[$type];
                $best = $col;
            }
        }
        $mapping[$type] = $best; // peut rester null
    }
    return $mapping;
}
// Colonnes étendues (optionnelles)
function detect_extended_columns($sheet): array
{
    $map = ['nb_voy' => null, 'cubage' => null, 'montant' => null, 'frais' => null, 'carburant' => null, 'litre' => null, 'reel' => null];
    $scanRows = min(40, $sheet->getHighestDataRow());
    $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($r = 1; $r <= $scanRows; $r++) {
        for ($c = 1; $c <= $highestCol; $c++) {
            $L = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $raw = (string)$sheet->getCell($L . $r)->getFormattedValue();
            if ($raw === '') continue;
            $u = mb_strtoupper(trim($raw));
            if ($map['nb_voy'] === null && str_contains($u, 'NOMBRE') && str_contains($u, 'VOY')) $map['nb_voy'] = $L;
            if ($map['cubage'] === null && str_contains($u, 'CUBAGE')) $map['cubage'] = $L;
            if ($map['montant'] === null && (str_contains($u, 'MONTANT ORIG') || str_contains($u, 'MONTANT  ORIG'))) $map['montant'] = $L;
            if ($map['frais'] === null && str_contains($u, 'FRAIS') && str_contains($u, 'ROUTE')) $map['frais'] = $L;
            if ($map['carburant'] === null && str_contains($u, 'CARBURANT')) $map['carburant'] = $L;
            if ($map['litre'] === null && (str_contains($u, 'LITRE') || str_contains($u, 'LITRES'))) $map['litre'] = $L;
            if ($map['reel'] === null && (str_contains($u, 'REEL RECU') || str_contains($u, 'RÉEL REÇU') || str_contains($u, 'REEL  RECU'))) $map['reel'] = $L;
        }
    }
    return $map;
}
function parse_money_to_float($val): float
{
    $raw = (string)$val;
    if ($raw === '' || trim($raw) === '-') return 0.0;
    // Normalisation espaces (classiques, insécables, fines), tab, retour ligne -> espace simple
    $raw = preg_replace('~[\x{00A0}\x{202F}\s]+~u', ' ', $raw);
    $rawUp = strtoupper($raw);
    // Retirer mentions monétaires pour limiter le bruit (laisser les nombres intacts)
    $rawUp = str_replace(['CFA', 'FCFA', 'F CFA', ' FRCS', 'FRCS', 'FR C S', 'FRCFA', ' F '], ' ', $rawUp);
    // Capturer tous les candidats : patterns avec séparateurs milliers, puis décimaux, puis entiers
    if (!preg_match_all('/\d{1,3}(?:[ \.\x{00A0}\x{202F}]\d{3})+(?:[,.]\d+)?|\d+[.,]\d+|\d+/u', $rawUp, $matches)) {
        return 0.0;
    }
    $best = 0.0;
    foreach ($matches[0] as $cand) {
        $c = trim($cand);
        if ($c === '' || $c === '-') continue;
        $isThousandsPattern = (bool)preg_match('/^\d{1,3}(?:[ \.\x{00A0}\x{202F}]\d{3})+(?:[,.]\d+)?$/u', $c);
        // Séparateurs milliers -> enlever (espace, point, NBSP) quand pattern milliers détecté
        if ($isThousandsPattern) {
            // Garder éventuelle partie décimale séparée par virgule/point
            if (strpos($c, ',') !== false && substr_count($c, ',') === 1) {
                [$ent, $dec] = explode(',', $c, 2);
                $ent = preg_replace('~[ \.\x{00A0}\x{202F}]~u', '', $ent);
                $c = $ent . '.' . preg_replace('~[^0-9]~', '', $dec);
            } elseif (strpos($c, '.') !== false && substr_count($c, '.') === 1 && !preg_match('~\.\d{3}$~', $c)) {
                // Probable décimale (rare ici) => supprimer séparateurs milliers (espaces) seulement
                $parts = explode('.', $c, 2);
                $ent = preg_replace('~[ \x{00A0}\x{202F}]~u', '', $parts[0]);
                $c = $ent . '.' . preg_replace('~[^0-9]~', '', $parts[1]);
            } else {
                // Pas de décimale : enlever tous séparateurs de milliers
                $c = preg_replace('~[ \.\x{00A0}\x{202F}]~u', '', $c);
            }
        } else {
            // Cas non milliers : traiter virgule comme décimale ; si plusieurs séparateurs, prendre la dernière comme décimale
            if (strpos($c, ',') !== false) {
                if (substr_count($c, ',') > 1) {
                    $parts = explode(',', $c);
                    $dec = array_pop($parts);
                    $c = preg_replace('~,~', '', implode('', $parts)) . '.' . $dec;
                } else {
                    $c = str_replace(',', '.', $c);
                }
            }
            // Si on a un unique point et 3 chiffres après et longueur totale petite -> point = millier (ex: 33.750)
            if (preg_match('~^\d{1,3}\.\d{3}$~', $c)) {
                $c = str_replace('.', '', $c); // 33.750 -> 33750
            }
        }
        // Nettoyage final conservateur
        $c = preg_replace('~[^0-9\.]~', '', $c);
        if ($c === '' || $c === '.') continue;
        $valNum = (float)$c;
        if ($valNum > $best) $best = $valNum;
    }
    return $best;
}
function parse_int_like($val, $default = 0): int
{
    if ($val === null) return $default;
    $v = preg_replace('~[^0-9-]~', '', trim((string)$val));
    if ($v === '' || $v === '-') return $default;
    return (int)$v;
}
function parse_float_like($val, $default = 0.0): float
{
    if ($val === null) return $default;
    $s = trim((string)$val);
    if ($s === '' || $s === '-') return $default;
    $s = str_replace("\xC2\xA0", ' ', $s);
    $s = str_replace(' ', '', $s);
    $s = str_replace(',', '.', $s);
    if (!preg_match('~^-?\d+(?:\.\d+)?$~', $s)) return $default;
    return (float)$s;
}
// Fallback ligne pour trouver chauffeur si mapping['chauffeur'] vide
function find_chauffeur_cell($sheet, int $row, ?string $camionCol, ?string $bonCol): array
{
    $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $best = ['', ''];
    for ($col = 1; $col <= $highestCol; $col++) {
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $val = trim((string)$sheet->getCell($letter . $row)->getFormattedValue());
        if ($val === '') continue;
        // On évite de réutiliser camion / bon
        if ($camionCol && $letter === $camionCol) continue;
        if ($bonCol && $letter === $bonCol) continue;
        // Critères chauffeur: contient lettres + (téléphone ou >=2 mots)
        $words = preg_split('~\s+~', preg_replace('~\d~', ' ', $val));
        $wordCount = 0;
        foreach ($words as $w) {
            if (mb_strlen($w) > 1) $wordCount++;
        }
        $hasPhone = preg_match('~\d{2}\s\d{2}\s\d{2}\s\d{2}\s\d{2}~', $val);
        if (($wordCount >= 2 || $hasPhone) && !preg_match('~\bBON\b|N°|NUMÉRO|NUMERO~iu', $val)) {
            return [$val, $letter];
        }
    }
    return $best; // ['', '']
}
function extract_chauffeur_tel(string $raw): array
{
    // Essaye de séparer nom et numéro: prend derniers groupes de chiffres espacés
    if (preg_match('~(.*?)(\d[\d\s]{6,})$~u', $raw, $m)) {
        $name = trim($m[1]);
        $telDigits = preg_replace('~\D~', '', $m[2]);
        if (strlen($telDigits) >= 8 && strlen($telDigits) <= 14) return [$name, $telDigits];
    }
    return [trim($raw), ''];
}
function normalize_bon(string $raw): string
{
    $raw = strtoupper($raw);
    $raw = preg_replace('~[^A-Z0-9]+~', ' ', $raw);
    if (preg_match('~(\d{4,})~', $raw, $m)) return $m[1];
    return trim($raw);
}

// Détection lignes décoratives / en-têtes multi-lignes
function is_header_decorative(string $txt): bool
{
    $u = strtoupper(trim($txt));
    if ($u === '') return false;
    if (strpos($u, 'POINTAGE') !== false) return true;
    if (strpos($u, 'SEMAINE DU') !== false) return true;
    if (preg_match('~^DATE$~', $u)) return true;
    return false;
}

const DEPOLLUTION_PLACEHOLDER_CHAUFFEUR = 'INCONNU';

// ---------- Helpers JSON ----------
function json_out($data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function require_fields(array $src, array $fields)
{
    $miss = [];
    foreach ($fields as $f) if (!isset($src[$f]) || $src[$f] === '') $miss[] = $f;
    if ($miss) json_out(['ok' => false, 'error' => 'Champs manquants', 'fields' => $miss], 422);
}

// ---------- Connexion ----------
$pdo = Database::getConnection();

// ---------- Création tables si manquantes ----------
// Suffixe depollution
// prestataires, chauffeurs, voyages (un voyage = un déplacement = row Excel), pieces (bons de sortie)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_prestataire (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(150) NOT NULL UNIQUE,
        montant_origine DECIMAL(15,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_chauffeur (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(150) NOT NULL,
        telephone VARCHAR(30) NULL,
        UNIQUE KEY uniq_chauffeur_tel (nom, telephone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_camion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        matricule VARCHAR(80) NOT NULL UNIQUE,
        prestataire_id INT NULL,
        FOREIGN KEY (prestataire_id) REFERENCES depollution_prestataire(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_bon_sortie (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(120) NOT NULL,
        fichier_path VARCHAR(255) NULL,
        UNIQUE KEY uniq_numero (numero)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_voyage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date_voyage DATE NOT NULL,
        prestataire_id INT NOT NULL,
        chauffeur_id INT NOT NULL,
        camion_id INT NOT NULL,
        bon_sortie_id INT NOT NULL,
        nombre_voyage INT DEFAULT 1,
        cubage DECIMAL(10,3) DEFAULT 24.000,
        montant_origine DECIMAL(15,2) DEFAULT 0,
        frais_route DECIMAL(15,2) DEFAULT 0,
        carburant_montant DECIMAL(15,2) DEFAULT 0,
        carburant_litre DECIMAL(10,2) DEFAULT 0,
        reel_recu DECIMAL(15,2) DEFAULT 0,
        statut ENUM('SAISI','CLOS') DEFAULT 'SAISI',
        solde TINYINT(1) DEFAULT 0,
        solde_at DATETIME NULL,
        solde_ref VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (prestataire_id) REFERENCES depollution_prestataire(id),
        FOREIGN KEY (chauffeur_id) REFERENCES depollution_chauffeur(id),
        FOREIGN KEY (camion_id) REFERENCES depollution_camion(id),
        FOREIGN KEY (bon_sortie_id) REFERENCES depollution_bon_sortie(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    json_out(['ok' => false, 'error' => 'Erreur création tables', 'detail' => $e->getMessage()], 500);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ---------- Actions Admin ----------
if ($action === 'listPrestataires') {
    $rows = $pdo->query('SELECT * FROM depollution_prestataire ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok' => true, 'data' => $rows]);
}

// ---------- Liste des opérations (étapes du projet) ----------
if ($action === 'listOperations') {
    try {
        $rows = $pdo->query('SELECT id_depollution_operation AS id, lib_depollution_operation AS label FROM depollution_operation ORDER BY lib_depollution_operation')->fetchAll(PDO::FETCH_ASSOC);
        json_out(['ok' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ---------- Décaissement: marquer voyage soldé/non soldé ----------
if ($action === 'setVoyageSolde') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $solde = isset($_POST['solde']) ? (int)$_POST['solde'] : (int)($_GET['solde'] ?? 0);
    $ref = trim((string)($_POST['ref'] ?? $_GET['ref'] ?? ''));
    if ($id <= 0) json_out(['ok' => false, 'error' => 'id requis'], 422);
    try {
        $sql = 'UPDATE depollution_voyage SET solde=?, solde_at=?, solde_ref=? WHERE id=?';
        $dt = $solde ? date('Y-m-d H:i:s') : null;
        $refFinal = $solde ? ($ref !== '' ? $ref : null) : null;
        $st = $pdo->prepare($sql);
        $st->execute([$solde ? 1 : 0, $dt, $refFinal, $id]);
        json_out(['ok' => true, 'id' => $id, 'solde' => (int)$solde, 'solde_at' => $dt, 'solde_ref' => $refFinal]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}
if ($action === 'importPrestataire') { // simple création manuelle
    $payload = $_POST;
    require_fields($payload, ['nom', 'montant_origine']);
    try {
        $st = $pdo->prepare('INSERT INTO depollution_prestataire (nom, montant_origine) VALUES (?,?)');
        $st->execute([$payload['nom'], (float)$payload['montant_origine']]);
        json_out(['ok' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

// ---------- Import Excel multi-feuilles (admin) ----------
if ($action === 'importExcel') {
    if (empty($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        json_out(['ok' => false, 'error' => 'Fichier manquant ou invalide'], 400);
    }
    $tmp = $_FILES['excel']['tmp_name'];
    $name = $_FILES['excel']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) json_out(['ok' => false, 'error' => 'Extension non supportée'], 415);
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
        $spread = $reader->load($tmp);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'Lecture Excel: ' . $e->getMessage()], 400);
    }
    $report = [];
    $strict = isset($_POST['strict']) && $_POST['strict'] === '1';
    foreach ($spread->getWorksheetIterator() as $sheet) {
        $sheetName = trim($sheet->getTitle());
        if ($sheetName === '') continue;
        $prestataireNom = $sheetName; // nom feuille = prestataire
        $createdPrest = false;
        $prestId = null;
        $montantOrigine = 200000; // défaut si absent
        // upsert prestataire
        $st = $pdo->prepare('SELECT id, montant_origine FROM depollution_prestataire WHERE nom=?');
        $st->execute([$prestataireNom]);
        $prest = $st->fetch(PDO::FETCH_ASSOC);
        if ($prest) {
            $prestId = (int)$prest['id'];
            $montantOrigine = (float)$prest['montant_origine'];
        } else {
            $st = $pdo->prepare('INSERT INTO depollution_prestataire (nom, montant_origine) VALUES (?,?)');
            $st->execute([$prestataireNom, $montantOrigine]);
            $prestId = (int)$pdo->lastInsertId();
            $createdPrest = true;
        }
        $rowsCreated = 0;
        $rowsErrors = 0;
        $lineDetails = [];
        $mapping = detect_columns($sheet);
        $extended = detect_extended_columns($sheet);
        $mapping = array_merge($mapping, $extended);
        $highest = $sheet->getHighestDataRow();
        $lastDateSql = null;
        for ($row = 1; $row <= $highest; $row++) {
            $cellA = (string)$sheet->getCell('A' . $row)->getFormattedValue();
            if ($row <= 10 && is_header_decorative($cellA)) continue;
            $dateRawVal = $mapping['date'] ? $sheet->getCell($mapping['date'] . $row)->getValue() : '';
            $dateDisp = $mapping['date'] ? trim((string)$sheet->getCell($mapping['date'] . $row)->getFormattedValue()) : '';
            $camion = $mapping['camion'] ? trim((string)$sheet->getCell($mapping['camion'] . $row)->getFormattedValue()) : '';
            $chauffRaw = $mapping['chauffeur'] ? trim((string)$sheet->getCell($mapping['chauffeur'] . $row)->getFormattedValue()) : '';
            if ($chauffRaw === '' && !$mapping['chauffeur']) {
                [$found, $colFound] = find_chauffeur_cell($sheet, $row, $mapping['camion'], $mapping['bon']);
                if ($found !== '') $chauffRaw = $found;
            }
            $bonRaw = $mapping['bon'] ? trim((string)$sheet->getCell($mapping['bon'] . $row)->getFormattedValue()) : '';
            if ($dateDisp === '' && $camion === '' && $chauffRaw === '' && $bonRaw === '') continue;
            $rowConcat = strtoupper($cellA . ' ' . $camion . ' ' . $chauffRaw . ' ' . $bonRaw);
            if ($camion === '' && preg_match('~TOTAL|SOMME~', $rowConcat)) continue; // ligne total
            try {
                $dateObj = null;
                if ($dateDisp !== '' && is_header_decorative($dateDisp)) continue; // décoratif
                if (excel_is_serial_date($dateRawVal)) {
                    $dateObj = excel_serial_to_date($dateRawVal);
                } elseif ($dateDisp !== '') {
                    if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d/m/Y', $dateDisp);
                    elseif (preg_match('~^\d{1,2}/\d{1,2}/\d{2}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d/m/y', $dateDisp);
                    elseif (preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateDisp)) $dateObj = new DateTime($dateDisp);
                    elseif (preg_match('~^\d{1,2}-\d{1,2}-\d{4}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d-m-Y', $dateDisp);
                    else {
                        if ($lastDateSql === null) continue; // première occurrence illisible -> saute
                    }
                } else {
                    if ($lastDateSql === null) continue; // aucune date encore
                }
                if ($dateObj) $lastDateSql = $dateObj->format('Y-m-d');
                $dateSql = $lastDateSql;
                if (!$dateSql) continue; // sécurité
                if ($camion === '') throw new Exception('Matricule vide');
                if ($strict && $chauffRaw === '') throw new Exception('Chauffeur vide');
                if ($chauffRaw === '') {
                    $chauffeur = DEPOLLUTION_PLACEHOLDER_CHAUFFEUR;
                    $tel = '';
                } else {
                    [$chauffeur, $tel] = extract_chauffeur_tel($chauffRaw);
                }
                if ($strict && $bonRaw === '') throw new Exception('Bon vide');
                if ($bonRaw === '') {
                    $bonNumero = 'AUTO-' . $prestataireNom . '-' . str_replace('-', '', $dateSql) . '-' . $row;
                } else {
                    $bonNumero = normalize_bon($bonRaw);
                }
                // Extraction valeurs étendues
                $nbVoy = $mapping['nb_voy'] ? parse_int_like($sheet->getCell($mapping['nb_voy'] . $row)->getValue(), 1) : 1;
                if ($nbVoy <= 0) $nbVoy = 1;
                $cubage = $mapping['cubage'] ? parse_float_like($sheet->getCell($mapping['cubage'] . $row)->getFormattedValue(), 24.0) : 24.0;
                if ($nbVoy > 1 && $cubage > 0 && fmod($cubage, $nbVoy) === 0.0) {
                    $cubage = $cubage / $nbVoy;
                }
                $montantOrigineLigne = $mapping['montant'] ? parse_money_to_float($sheet->getCell($mapping['montant'] . $row)->getFormattedValue()) : 0.0;
                if ($montantOrigineLigne > 0) $montantOrigine = $montantOrigineLigne;
                $fraisRoute = $mapping['frais'] ? parse_money_to_float($sheet->getCell($mapping['frais'] . $row)->getFormattedValue()) : 0.0;
                $carbMontant = $mapping['carburant'] ? parse_money_to_float($sheet->getCell($mapping['carburant'] . $row)->getFormattedValue()) : 0.0;
                $litre = $mapping['litre'] ? parse_float_like($sheet->getCell($mapping['litre'] . $row)->getFormattedValue(), 0.0) : 0.0;
                $reelRecuXls = $mapping['reel'] ? parse_money_to_float($sheet->getCell($mapping['reel'] . $row)->getFormattedValue()) : 0.0;
                $calcReel = false;
                $reelRecu = $reelRecuXls > 0 ? $reelRecuXls : ($montantOrigine - $fraisRoute - $carbMontant);
                if ($reelRecuXls <= 0) $calcReel = true;
                $pdo->beginTransaction();
                // Chauffeur
                $st = $pdo->prepare('SELECT id FROM depollution_chauffeur WHERE nom=? AND (telephone=? OR (telephone IS NULL AND ?=""))');
                $st->execute([$chauffeur, $tel, $tel]);
                $ch = $st->fetch(PDO::FETCH_ASSOC);
                if ($ch) $chauffeurId = (int)$ch['id'];
                else {
                    $pdo->prepare('INSERT INTO depollution_chauffeur (nom, telephone) VALUES (?,?)')->execute([$chauffeur, $tel]);
                    $chauffeurId = (int)$pdo->lastInsertId();
                }
                // Camion
                $st = $pdo->prepare('SELECT id FROM depollution_camion WHERE matricule=?');
                $st->execute([$camion]);
                $cm = $st->fetch(PDO::FETCH_ASSOC);
                if ($cm) $camionId = (int)$cm['id'];
                else {
                    $pdo->prepare('INSERT INTO depollution_camion (matricule, prestataire_id) VALUES (?,?)')->execute([$camion, $prestId]);
                    $camionId = (int)$pdo->lastInsertId();
                }
                // Bon
                $st = $pdo->prepare('SELECT id FROM depollution_bon_sortie WHERE numero=?');
                $st->execute([$bonNumero]);
                $bn = $st->fetch(PDO::FETCH_ASSOC);
                if ($bn) $bonId = (int)$bn['id'];
                else {
                    $pdo->prepare('INSERT INTO depollution_bon_sortie (numero) VALUES (?)')->execute([$bonNumero]);
                    $bonId = (int)$pdo->lastInsertId();
                }
                // Voyage (anti doublon)
                $st = $pdo->prepare('SELECT id FROM depollution_voyage WHERE date_voyage=? AND camion_id=? AND bon_sortie_id=?');
                $st->execute([$dateSql, $camionId, $bonId]);
                $vx = $st->fetch(PDO::FETCH_ASSOC);
                if (!$vx) {
                    $pdo->prepare('INSERT INTO depollution_voyage (date_voyage, prestataire_id, chauffeur_id, camion_id, bon_sortie_id, nombre_voyage, cubage, montant_origine, frais_route, carburant_montant, carburant_litre, reel_recu, statut) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"SAISI")')
                        ->execute([$dateSql, $prestId, $chauffeurId, $camionId, $bonId, $nbVoy, $cubage, $montantOrigine, $fraisRoute, $carbMontant, $litre, $reelRecu]);
                    @$pdo->exec('CREATE TABLE IF NOT EXISTS depollution_import_audit (id INT AUTO_INCREMENT PRIMARY KEY, sheet VARCHAR(120), source_row INT, calc_reel TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
                    $stAudit = $pdo->prepare('INSERT INTO depollution_import_audit (sheet, source_row, calc_reel) VALUES (?,?,?)');
                    $stAudit->execute([$sheetName, $row, $calcReel ? 1 : 0]);
                    $rowsCreated++;
                }
                $pdo->commit();
                $lineDetails[] = ['row' => $row, 'status' => 'ok'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $rowsErrors++;
                $lineDetails[] = ['row' => $row, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        $report[] = [
            'sheet' => $sheetName,
            'prestataire_created' => $createdPrest,
            'voyages_created' => $rowsCreated,
            'voyages_errors' => $rowsErrors,
            'mapping' => $mapping,
            'lines' => $lineDetails
        ];
    }
    json_out(['ok' => true, 'report' => $report]);
}

// ---------- Prévisualisation import Excel (sans insertion DB) ----------
if ($action === 'importExcelPreview') {
    if (empty($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        json_out(['ok' => false, 'error' => 'Fichier manquant ou invalide'], 400);
    }
    $tmp = $_FILES['excel']['tmp_name'];
    $name = $_FILES['excel']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) json_out(['ok' => false, 'error' => 'Extension non supportée'], 415);
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
        $spread = $reader->load($tmp);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => 'Lecture Excel: ' . $e->getMessage()], 400);
    }
    $sheetsPreview = [];
    $strict = isset($_POST['strict']) && $_POST['strict'] === '1';
    foreach ($spread->getWorksheetIterator() as $sheet) {
        $sheetName = trim($sheet->getTitle());
        if ($sheetName === '') continue;
        $mapping = detect_columns($sheet);
        $extended = detect_extended_columns($sheet);
        $mapping = array_merge($mapping, $extended);
        $highest = $sheet->getHighestDataRow();
        $rows = [];
        $lastDateSql = null;
        for ($row = 1; $row <= $highest; $row++) {
            $cellA = (string)$sheet->getCell('A' . $row)->getFormattedValue();
            if ($row <= 10 && is_header_decorative($cellA)) continue;
            $dateRawVal = $mapping['date'] ? $sheet->getCell($mapping['date'] . $row)->getValue() : '';
            $dateDisp = $mapping['date'] ? trim((string)$sheet->getCell($mapping['date'] . $row)->getFormattedValue()) : '';
            $camion = $mapping['camion'] ? trim((string)$sheet->getCell($mapping['camion'] . $row)->getFormattedValue()) : '';
            $chauffRaw = $mapping['chauffeur'] ? trim((string)$sheet->getCell($mapping['chauffeur'] . $row)->getFormattedValue()) : '';
            if ($chauffRaw === '' && !$mapping['chauffeur']) {
                [$found, $colFound] = find_chauffeur_cell($sheet, $row, $mapping['camion'], $mapping['bon']);
                if ($found !== '') $chauffRaw = $found;
            }
            $bonRaw = $mapping['bon'] ? trim((string)$sheet->getCell($mapping['bon'] . $row)->getFormattedValue()) : '';
            if ($dateDisp === '' && $camion === '' && $chauffRaw === '' && $bonRaw === '') continue;
            $rowConcat = strtoupper($cellA . ' ' . $camion . ' ' . $chauffRaw . ' ' . $bonRaw);
            if ($camion === '' && preg_match('~TOTAL|SOMME~', $rowConcat)) continue; // exclusion lignes total
            $errors = [];
            $dateObj = null;
            if ($dateDisp !== '' && is_header_decorative($dateDisp)) continue;
            try {
                if (excel_is_serial_date($dateRawVal)) {
                    $dateObj = excel_serial_to_date($dateRawVal);
                } elseif ($dateDisp !== '') {
                    if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d/m/Y', $dateDisp);
                    elseif (preg_match('~^\d{1,2}/\d{1,2}/\d{2}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d/m/y', $dateDisp);
                    elseif (preg_match('~^\d{4}-\d{2}-\d{2}$~', $dateDisp)) $dateObj = new DateTime($dateDisp);
                    elseif (preg_match('~^\d{1,2}-\d{1,2}-\d{4}$~', $dateDisp)) $dateObj = DateTime::createFromFormat('d-m-Y', $dateDisp);
                    else {
                        if ($lastDateSql === null) continue; // on ne peut pas dater la première occurrence
                    }
                } else {
                    if ($lastDateSql === null) continue;
                }
            } catch (Throwable $e) {
                if ($lastDateSql === null) continue;
            }
            if ($dateObj) $lastDateSql = $dateObj->format('Y-m-d');
            $dateSql = $lastDateSql;
            $chauffeurFinal = $chauffRaw === '' ? DEPOLLUTION_PLACEHOLDER_CHAUFFEUR : extract_chauffeur_tel($chauffRaw)[0];
            $tel = $chauffRaw === '' ? '' : extract_chauffeur_tel($chauffRaw)[1];
            $bonNorm = $bonRaw === '' ? ('AUTO-' . $sheetName . '-' . ($dateSql ? str_replace('-', '', $dateSql) : 'NA') . '-' . $row) : normalize_bon($bonRaw);
            if (!$dateSql) $errors[] = 'Date absente';
            if ($camion === '') $errors[] = 'Matricule vide';
            if ($strict && $chauffRaw === '') $errors[] = 'Chauffeur vide';
            if ($strict && $bonRaw === '') $errors[] = 'Bon vide';
            $nbVoy = $mapping['nb_voy'] ? parse_int_like($sheet->getCell($mapping['nb_voy'] . $row)->getValue(), 1) : 1;
            if ($nbVoy <= 0) $nbVoy = 1;
            $cubage = $mapping['cubage'] ? parse_float_like($sheet->getCell($mapping['cubage'] . $row)->getFormattedValue(), 24.0) : null;
            if ($cubage !== null && $nbVoy > 1 && $cubage > 0 && fmod($cubage, $nbVoy) === 0.0) $cubage = $cubage / $nbVoy; // normalisation par voyage
            $montantOrig = $mapping['montant'] ? parse_money_to_float($sheet->getCell($mapping['montant'] . $row)->getFormattedValue()) : null;
            $fraisRoute = $mapping['frais'] ? parse_money_to_float($sheet->getCell($mapping['frais'] . $row)->getFormattedValue()) : null;
            $carburantMontant = $mapping['carburant'] ? parse_money_to_float($sheet->getCell($mapping['carburant'] . $row)->getFormattedValue()) : null;
            $litre = $mapping['litre'] ? parse_float_like($sheet->getCell($mapping['litre'] . $row)->getFormattedValue(), 0.0) : null;
            $reelRecu = $mapping['reel'] ? parse_money_to_float($sheet->getCell($mapping['reel'] . $row)->getFormattedValue()) : null;
            $calcReel = false;
            if (($reelRecu === null || $reelRecu <= 0) && $montantOrig !== null) {
                $baseMontant = $montantOrig - (float)$fraisRoute - (float)$carburantMontant;
                if ($baseMontant > 0) {
                    $reelRecu = $baseMontant;
                    $calcReel = true;
                }
            }
            $rows[] = [
                'row' => $row,
                'date_source' => $dateDisp ?: $dateRawVal,
                'date_sql' => $dateSql,
                'camion' => $camion,
                'chauffeur_raw' => $chauffRaw,
                'chauffeur' => $chauffeurFinal,
                'telephone' => $tel,
                'bon_raw' => $bonRaw,
                'bon_normalize' => $bonNorm,
                'nb_voy' => $mapping['nb_voy'] ? $nbVoy : null,
                'cubage' => $mapping['cubage'] ? $cubage : null,
                'montant_origine' => $montantOrig,
                'frais_route' => $fraisRoute,
                'carburant_montant' => $carburantMontant,
                'carburant_litre' => $litre,
                'reel_recu' => $reelRecu,
                'calc_reel' => $calcReel,
                'errors' => $errors,
                'valid' => empty($errors)
            ];
        }
        $sheetsPreview[] = [
            'sheet' => $sheetName,
            'mapping' => $mapping,
            'rows_total' => count($rows),
            'rows_valid' => count(array_filter($rows, fn($r) => $r['valid'])),
            'rows_errors' => count(array_filter($rows, fn($r) => !$r['valid'])),
            'rows' => $rows
        ];
    }
    json_out(['ok' => true, 'preview' => $sheetsPreview]);
}

// ---------- Actions Opérateur 1 : création voyage ----------
if ($action === 'createVoyageOp1') {
    $payload = $_POST;
    require_fields($payload, [
        'date_voyage',
        'prestataire_nom',
        'chauffeur_nom',
        'chauffeur_tel',
        'camion_matricule',
        'bon_numero'
    ]);
    $date = $payload['date_voyage'];
    try {
        $d = new DateTime($date);
        $date = $d->format('Y-m-d');
    } catch (Exception $e) {
        json_out(['ok' => false, 'error' => 'date_voyage invalide'], 422);
    }
    // Récupération optionnelle de l'opération
    $operationId = isset($payload['operation_id']) ? (int)$payload['operation_id'] : 0;
    if ($operationId > 0) {
        try {
            $chk = $pdo->prepare('SELECT id_depollution_operation FROM depollution_operation WHERE id_depollution_operation=?');
            $chk->execute([$operationId]);
            if (!$chk->fetch()) {
                $operationId = 0; // invalide -> ignorer
            }
        } catch (Throwable $e) {
            $operationId = 0;
        }
    }

    $pdo->beginTransaction();
    try {
        // prestataire
        $st = $pdo->prepare('SELECT id, montant_origine FROM depollution_prestataire WHERE nom=?');
        $st->execute([$payload['prestataire_nom']]);
        $prest = $st->fetch(PDO::FETCH_ASSOC);
        if (!$prest) throw new Exception('Prestataire introuvable');
        $prestId = (int)$prest['id'];
        $montantOrigine = (float)$prest['montant_origine'];

        // chauffeur (upsert logique)
        $st = $pdo->prepare('SELECT id FROM depollution_chauffeur WHERE nom=? AND (telephone=? OR (telephone IS NULL AND ?=""))');
        $st->execute([$payload['chauffeur_nom'], $payload['chauffeur_tel'], $payload['chauffeur_tel']]);
        $ch = $st->fetch(PDO::FETCH_ASSOC);
        if ($ch) {
            $chauffeurId = (int)$ch['id'];
        } else {
            $st = $pdo->prepare('INSERT INTO depollution_chauffeur (nom, telephone) VALUES (?,?)');
            $st->execute([$payload['chauffeur_nom'], $payload['chauffeur_tel']]);
            $chauffeurId = (int)$pdo->lastInsertId();
        }

        // camion
        $st = $pdo->prepare('SELECT id FROM depollution_camion WHERE matricule=?');
        $st->execute([$payload['camion_matricule']]);
        $cam = $st->fetch(PDO::FETCH_ASSOC);
        if ($cam) {
            $camionId = (int)$cam['id'];
        } else {
            $st = $pdo->prepare('INSERT INTO depollution_camion (matricule, prestataire_id) VALUES (?,?)');
            $st->execute([$payload['camion_matricule'], $prestId]);
            $camionId = (int)$pdo->lastInsertId();
        }

        // bon sortie
        $st = $pdo->prepare('SELECT id FROM depollution_bon_sortie WHERE numero=?');
        $st->execute([$payload['bon_numero']]);
        $bon = $st->fetch(PDO::FETCH_ASSOC);
        $uploadedPath = null;
        if (!empty($_FILES['bon_fichier']) && $_FILES['bon_fichier']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../storage/bons';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['bon_fichier']['name']);
            $dest = $uploadDir . '/' . time() . '_' . $safeName;
            $tmp = $_FILES['bon_fichier']['tmp_name'];
            $moved = false;
            if (is_uploaded_file($tmp)) {
                $moved = move_uploaded_file($tmp, $dest);
            }
            if (!$moved) {
                // Fallback copie
                if (@copy($tmp, $dest)) {
                    $moved = true;
                    @unlink($tmp);
                }
            }
            if ($moved) {
                $uploadedPath = $dest;
            }
        }
        if ($bon) {
            $bonId = (int)$bon['id'];
            if ($uploadedPath) {
                $upd = $pdo->prepare('UPDATE depollution_bon_sortie SET fichier_path=? WHERE id=?');
                $upd->execute([$uploadedPath, $bonId]);
            }
            // Recharger pour renvoyer le chemin après éventuelle mise à jour
            $stp = $pdo->prepare('SELECT fichier_path FROM depollution_bon_sortie WHERE id=?');
            $stp->execute([$bonId]);
            $updatedBon = $stp->fetch(PDO::FETCH_ASSOC);
            $uploadedPath = $updatedBon ? $updatedBon['fichier_path'] : $uploadedPath;
        } else {
            $st = $pdo->prepare('INSERT INTO depollution_bon_sortie (numero, fichier_path) VALUES (?,?)');
            $st->execute([$payload['bon_numero'], $uploadedPath]);
            $bonId = (int)$pdo->lastInsertId();
        }

        // voyage (insertion avec operation_id si la colonne existe)
        $hasOperationCol = true;
        try {
            $pdo->query('SELECT operation_id FROM depollution_voyage LIMIT 1');
        } catch (Throwable $eOpCol) {
            $hasOperationCol = false;
            // Tentative ajout colonne si possible
            try {
                $pdo->exec('ALTER TABLE depollution_voyage ADD COLUMN operation_id INT NULL');
                $hasOperationCol = true;
            } catch (Throwable $eAddOp) { /* ignore */
            }
        }
        $reel = $montantOrigine; // frais route et carburant non encore déduits
        if ($hasOperationCol) {
            $st = $pdo->prepare('INSERT INTO depollution_voyage (date_voyage, prestataire_id, chauffeur_id, camion_id, bon_sortie_id, operation_id, montant_origine, reel_recu) VALUES (?,?,?,?,?,?,?,?)');
            $st->execute([$date, $prestId, $chauffeurId, $camionId, $bonId, ($operationId > 0 ? $operationId : null), $montantOrigine, $reel]);
        } else {
            $st = $pdo->prepare('INSERT INTO depollution_voyage (date_voyage, prestataire_id, chauffeur_id, camion_id, bon_sortie_id, montant_origine, reel_recu) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$date, $prestId, $chauffeurId, $camionId, $bonId, $montantOrigine, $reel]);
        }
        $voyageId = (int)$pdo->lastInsertId();
        $pdo->commit();
        // Diagnostics upload fichier bon
        $diag = [
            'file_field_present' => isset($_FILES['bon_fichier']),
            'file_name' => isset($_FILES['bon_fichier']['name']) ? $_FILES['bon_fichier']['name'] : null,
            'file_size' => isset($_FILES['bon_fichier']['size']) ? (int)$_FILES['bon_fichier']['size'] : null,
            'uploaded_tmp' => isset($_FILES['bon_fichier']['tmp_name']) ? $_FILES['bon_fichier']['tmp_name'] : null,
            'saved_path' => $uploadedPath,
            'bon_id' => $bonId
        ];
        json_out(['ok' => true, 'voyage_id' => $voyageId, 'upload_diag' => $diag]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

// ---------- Actions Opérateur 2 : clôture / mise à jour voyage ----------
if ($action === 'updateVoyageOp2') {
    $payload = $_POST;
    require_fields($payload, ['voyage_id', 'frais_route', 'carburant_litre', 'carburant_montant']);
    $voyageId = (int)$payload['voyage_id'];
    $frais = (float)$payload['frais_route'];
    $carbL = (float)$payload['carburant_litre'];
    $carbM = (float)$payload['carburant_montant'];
    try {
        // Vérifier si la colonne generation_fiches existe (compatibilité versions anciennes du schéma)
        $hasGenerationCol = true;
        try {
            $pdo->query("SELECT generation_fiches FROM depollution_voyage LIMIT 1");
        } catch (Throwable $eCol) {
            $hasGenerationCol = false;
            // Tentative d'ajout rapide de la colonne (ignorer erreurs si droits insuffisants)
            try {
                $pdo->exec("ALTER TABLE depollution_voyage ADD COLUMN generation_fiches TINYINT DEFAULT 0");
                $hasGenerationCol = true;
            } catch (Throwable $eAdd) {
                // Ignore, on fera un SELECT sans la colonne
            }
        }

        // Requête principale avec fallback sans la colonne si nécessaire
        if ($hasGenerationCol) {
            try {
                $st = $pdo->prepare('SELECT v.montant_origine, v.generation_fiches, v.prestataire_id, v.chauffeur_id, v.camion_id, c.matricule, ch.nom chauffeur_nom, ch.telephone chauffeur_tel
                                     FROM depollution_voyage v
                                     JOIN depollution_camion c ON v.camion_id=c.id
                                     JOIN depollution_chauffeur ch ON v.chauffeur_id=ch.id
                                     WHERE v.id=?');
                $st->execute([$voyageId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $eSel) {
                // Fallback sans la colonne si l'erreur persiste (ex: absence droits alter mais colonne absente réellement)
                $hasGenerationCol = false;
            }
        }
        if (!$hasGenerationCol) {
            $st = $pdo->prepare('SELECT v.montant_origine, v.prestataire_id, v.chauffeur_id, v.camion_id, c.matricule, ch.nom chauffeur_nom, ch.telephone chauffeur_tel
                                 FROM depollution_voyage v
                                 JOIN depollution_camion c ON v.camion_id=c.id
                                 JOIN depollution_chauffeur ch ON v.chauffeur_id=ch.id
                                 WHERE v.id=?');
            $st->execute([$voyageId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Simuler la colonne manquante à 0 (pas encore généré)
                $row['generation_fiches'] = 0;
            }
        }
        if (!$row) json_out(['ok' => false, 'error' => 'Voyage introuvable'], 404);
        if (!empty($row['generation_fiches'])) {
            json_out(['ok' => false, 'error' => 'Fiches déjà générées pour ce voyage'], 409);
        }
        $montantOrigine = (float)$row['montant_origine'];
        $reel = $montantOrigine - $frais - $carbM;
        if ($reel < 0) $reel = 0;
        // Clôture du voyage
        $pdo->prepare('UPDATE depollution_voyage SET frais_route=?, carburant_litre=?, carburant_montant=?, reel_recu=?, statut="CLOS" WHERE id=?')
            ->execute([$frais, $carbL, $carbM, $reel, $voyageId]);

        require_once __DIR__ . '/../../model/Fiche.php';
        require_once __DIR__ . '/../../model/EmailManager.php';
        require_once __DIR__ . '/../../model/WhatsAppSMS.php';
        $ficheObj = new Fiche($pdo);
        $emailMgr = new EmailManager();

        // Valeurs communes
        $chauffeurNom = $row['chauffeur_nom'];
        $chauffeurTel = $row['chauffeur_tel'];
        $matricule = $row['matricule'];
        $affectationId = 1; // chantier
        $chantierId = 60;   // chantier dépollution
        $entreprise = 'BANAMUR';
        $modePaiementCash = 'Cash';
        $designationCarburant = 'Dotation carburant (50 l/j) purge';

        // Service insertion générique
        $insertFiche = function (array $opts) use ($ficheObj) {
            $data = [
                'beficiaire_fiche' => $opts['beneficiaire'],
                'montant_fiche' => $opts['montant'],
                'tel_beneficiaire_fiche' => $opts['telephone'],
                'num_fiche' => $ficheObj->generateNumFiche(),
                'affectation_id' => $opts['affectation_id'],
                'num_piece' => $opts['num_piece'],
                'chantier_id' => $opts['chantier_id'],
                'precision_fiche' => $opts['precision_fiche'],
                'serv_bureau_banamur_id' => '',
                'code_autorisation_feb' => $opts['code_autorisation_feb'],
                'entreprise' => $opts['entreprise'],
                'designation_fiche' => $opts['designation_fiche'],
                'photo_beneficiaire' => '',
                'cni_beneficiaire' => ''
            ];
            $ficheObj->insertFiche($data);
            return $data['num_fiche'];
        };

        // Chargement config centralisée (Twilio / emails / logging)
        $configDepo = $configDepo ?? (function () {
            $file = __DIR__ . '/../../config/depollution_config.php';
            if (is_file($file)) {
                return require $file;
            }
            return [];
        })();

        // Démarrage transaction pour atomicité (clôture voyage + fiches + traces + logs)
        $txnStarted = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $txnStarted = true;
        }

        $fichesCreees = [];
        $index = $voyageId;
        // Fiche carburant ?
        if ($carbM > 0) {
            $numCarb = $insertFiche([
                'beneficiaire' => $chauffeurNom,
                'telephone' => $chauffeurTel,
                'montant' => $carbM,
                'affectation_id' => $affectationId,
                'num_piece' => $modePaiementCash,
                'chantier_id' => $chantierId,
                'precision_fiche' => 'Carburant pour la benne immatriculée ' . $matricule,
                'designation_fiche' => $designationCarburant,
                'code_autorisation_feb' => 'depo_bypass_' . $index . '_carb',
                'entreprise' => $entreprise
            ]);
            $fichesCreees[] = ['type' => 'carburant', 'num_fiche' => $numCarb, 'montant' => $carbM];
        }
        // Fiche reliquat
        $numRel = $insertFiche([
            'beneficiaire' => $chauffeurNom,
            'telephone' => $chauffeurTel,
            'montant' => $reel,
            'affectation_id' => $affectationId,
            'num_piece' => $modePaiementCash,
            'chantier_id' => $chantierId,
            'precision_fiche' => 'Reliquat pour la benne immatriculée ' . $matricule,
            'designation_fiche' => 'Reliquat benne ' . $matricule,
            'code_autorisation_feb' => 'depo_bypass_' . $index . '_reliq',
            'entreprise' => $entreprise
        ]);
        $fichesCreees[] = ['type' => 'reliquat', 'num_fiche' => $numRel, 'montant' => $reel];

        // Flag anti doublon & trace
        // Ajout colonne generation_fiches si absente (MySQL 8+ supporte IF NOT EXISTS). Fallback pour versions plus anciennes.
        try {
            $pdo->exec("ALTER TABLE depollution_voyage ADD COLUMN IF NOT EXISTS generation_fiches TINYINT DEFAULT 0");
        } catch (Throwable $eAlter) {
            try {
                $col = $pdo->query("SHOW COLUMNS FROM depollution_voyage LIKE 'generation_fiches'")->fetch();
                if (!$col) {
                    $pdo->exec("ALTER TABLE depollution_voyage ADD COLUMN generation_fiches TINYINT DEFAULT 0");
                }
            } catch (Throwable $eAlter2) { /* ignore */
            }
        }
        $pdo->prepare('UPDATE depollution_voyage SET generation_fiches=1 WHERE id=?')->execute([$voyageId]);
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS depollution_fiche_trace (
                id INT AUTO_INCREMENT PRIMARY KEY,
                voyage_id INT NOT NULL,
                type_fiche VARCHAR(30) NOT NULL,
                num_fiche VARCHAR(50) NOT NULL,
                montant DOUBLE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (Throwable $eTbl) { /* ignore */
        }
        $insTrace = $pdo->prepare('INSERT INTO depollution_fiche_trace (voyage_id, type_fiche, num_fiche, montant) VALUES (?,?,?,?)');
        foreach ($fichesCreees as $fc) {
            $insTrace->execute([$voyageId, $fc['type'], $fc['num_fiche'], $fc['montant']]);
        }

        // Préparation table de log notifications si activée
        $enableDbLog = (bool)($configDepo['log']['enable_db_log'] ?? false);
        $logStmt = null;
        if ($enableDbLog) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS depollution_notification_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    voyage_id INT NULL,
                    num_fiche VARCHAR(50) NULL,
                    type_fiche VARCHAR(30) NULL,
                    channel VARCHAR(20) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    message TEXT NULL,
                    payload JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Throwable $eLogTbl) { /* ignore creation failure */
            }
            try {
                $logStmt = $pdo->prepare('INSERT INTO depollution_notification_log (voyage_id, num_fiche, type_fiche, channel, status, message, payload) VALUES (?,?,?,?,?,?,?)');
            } catch (Throwable $ePrepLog) {
                $enableDbLog = false;
            }
        }
        $logNotif = function ($voyId, $fiche, $type, $channel, $status, $message, $payload) use ($enableDbLog, $logStmt) {
            if (!$enableDbLog || !$logStmt) return;
            try {
                $logStmt->execute([$voyId, $fiche, $type, $channel, $status, mb_substr($message, 0, 1000), json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            } catch (Throwable $e) { /* ignore */
            }
        };

        // Notifications email
        $notifErrors = [];
        try {
            $subject = 'DEPOLLUTION - Fiches générées voyage #' . $voyageId;
            $body = '<p>Voyage clôturé - Benne ' . htmlspecialchars($matricule) . '</p><ul>';
            foreach ($fichesCreees as $fc) {
                $body .= '<li>' . htmlspecialchars($fc['type']) . ' : ' . htmlspecialchars($fc['num_fiche']) . ' (' . number_format($fc['montant'], 0, ',', ' ') . ' CFA)</li>';
            }
            $body .= '</ul>';
            $recipients = $configDepo['emails']['recipients'] ?? [];
            $emailMgr->sendEmail($subject, $body, $recipients);
            $logNotif($voyageId, null, null, 'email', 'success', 'Envoi email OK', ['subject' => $subject, 'to' => $recipients]);
        } catch (Throwable $eMail) {
            $notifErrors[] = 'email:' . $eMail->getMessage();
            $logNotif($voyageId, null, null, 'email', 'error', $eMail->getMessage(), []);
        }

        // Notifications WhatsApp selon logique métier (carburant / réparation)
        try {
            $sid = $configDepo['twilio']['sid'] ?? '';
            $token = $configDepo['twilio']['token'] ?? '';
            $from = $configDepo['twilio']['from'] ?? '';
            $wa = new WhatsAppSMS($sid, $token, $from);
            $waDG = $configDepo['dg_whatsapp'] ?? '';
            foreach ($fichesCreees as $fc) {
                $precision = ($fc['type'] === 'carburant') ? ('Carburant pour la benne immatriculée ' . $matricule) : ('Reliquat pour la benne immatriculée ' . $matricule);
                $designation = ($fc['type'] === 'carburant') ? $designationCarburant : ('Reliquat benne ' . $matricule);
                $texte_precision = $precision;
                $texte_designation = $designation;
                // Carburant ?
                if (stripos($texte_precision, 'carburant') !== false || stripos($texte_designation, 'carburant') !== false) {
                    $resp = $wa->sendCarburantManageCallToAction(
                        $waDG,
                        $fc['num_fiche'],
                        $fc['montant'],
                        $chauffeurNom,
                        $texte_precision ?: $texte_designation
                    );
                    if (!is_array($resp) || $resp['status'] !== 'success') {
                        $notifErrors[] = 'wa_carb:' . (is_array($resp) ? $resp['message'] : 'fail');
                        $logNotif($voyageId, $fc['num_fiche'], $fc['type'], 'whatsapp', 'error', (is_array($resp) ? ($resp['message'] ?? 'fail') : 'fail'), $resp ?? []);
                    } else {
                        $logNotif($voyageId, $fc['num_fiche'], $fc['type'], 'whatsapp', 'success', 'WA carburant envoyée', $resp);
                    }
                }
                // Réparation urgence ? (utilise heuristique contientMotReparation reconstituée locale)
                $checker = function ($txt) {
                    $t = mb_strtolower($txt, 'UTF-8');
                    $t = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç'], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c'], $t);
                    return (strpos($t, 'reparation') !== false || strpos($t, 'reparations') !== false);
                };
                if ($checker($texte_precision) || $checker($texte_designation)) {
                    $resp2 = $wa->sendUrgentReparationCallToAction(
                        $waDG,
                        $fc['num_fiche'],
                        $fc['montant'],
                        $chauffeurNom,
                        $texte_precision ?: $texte_designation
                    );
                    if (!is_array($resp2) || $resp2['status'] !== 'success') {
                        $notifErrors[] = 'wa_rep:' . (is_array($resp2) ? $resp2['message'] : 'fail');
                        $logNotif($voyageId, $fc['num_fiche'], $fc['type'], 'whatsapp', 'error', (is_array($resp2) ? ($resp2['message'] ?? 'fail') : 'fail'), $resp2 ?? []);
                    } else {
                        $logNotif($voyageId, $fc['num_fiche'], $fc['type'], 'whatsapp', 'success', 'WA réparation envoyée', $resp2);
                    }
                }
            }
        } catch (Throwable $eWa) {
            $notifErrors[] = 'whatsapp:' . $eWa->getMessage();
            $logNotif($voyageId, null, null, 'whatsapp', 'error', $eWa->getMessage(), []);
        }
        if ($txnStarted && $pdo->inTransaction()) {
            try {
                $pdo->commit();
            } catch (Throwable $eC) { /* ignore */
            }
        }
        json_out(['ok' => true, 'reel_recu' => $reel, 'fiches' => $fichesCreees, 'notif_errors' => $notifErrors]);
    } catch (Throwable $e) {
        if (isset($txnStarted) && $txnStarted && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (Throwable $eRb) { /* ignore */
            }
        }
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

// ---------- Annulation voyage (Opérateur 2) ----------
if ($action === 'cancelVoyageOp2') {
    $payload = $_POST;
    require_fields($payload, ['voyage_id']);
    $voyageId = (int)$payload['voyage_id'];
    $reason = trim($payload['reason'] ?? '');
    try {
        // Vérifier existence voyage + statut
        $st = $pdo->prepare('SELECT id, statut, generation_fiches FROM depollution_voyage WHERE id=?');
        try {
            $st->execute([$voyageId]);
        } catch (Throwable $eSelGen) {
            // Fallback si colonne generation_fiches absente
            $st = $pdo->prepare('SELECT id, statut, 0 AS generation_fiches FROM depollution_voyage WHERE id=?');
            $st->execute([$voyageId]);
        }
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_out(['ok' => false, 'error' => 'Voyage introuvable'], 404);
        if (in_array($row['statut'], ['CLOS', 'ANNULE'], true)) {
            json_out(['ok' => false, 'error' => 'Voyage déjà ' . strtolower($row['statut'])]);
        }
        if (!empty($row['generation_fiches'])) {
            json_out(['ok' => false, 'error' => 'Impossible: fiches déjà générées']);
        }
        // Ajout colonnes cancel_reason / canceled_at si absentes
        $needCols = false;
        try {
            $pdo->query("SELECT cancel_reason, canceled_at FROM depollution_voyage LIMIT 1");
        } catch (Throwable $eCols) {
            $needCols = true;
        }
        if ($needCols) {
            try {
                $pdo->exec("ALTER TABLE depollution_voyage ADD COLUMN cancel_reason VARCHAR(255) NULL");
            } catch (Throwable $e1) { /* ignore */
            }
            try {
                $pdo->exec("ALTER TABLE depollution_voyage ADD COLUMN canceled_at DATETIME NULL");
            } catch (Throwable $e2) { /* ignore */
            }
        }
        $now = date('Y-m-d H:i:s');
        // Mise à jour
        try {
            $upd = $pdo->prepare('UPDATE depollution_voyage SET statut="ANNULE", cancel_reason=?, canceled_at=? WHERE id=?');
            $ok = $upd->execute([$reason !== '' ? mb_substr($reason, 0, 250) : null, $now, $voyageId]);
        } catch (Throwable $eUpd) {
            // Fallback si colonnes pas disponibles (statut seulement)
            $upd = $pdo->prepare('UPDATE depollution_voyage SET statut="ANNULE" WHERE id=?');
            $ok = $upd->execute([$voyageId]);
        }
        if (!$ok) json_out(['ok' => false, 'error' => 'Échec annulation']);
        json_out(['ok' => true, 'voyage_id' => $voyageId, 'statut' => 'ANNULE']);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

// ---------- Listing voyages (temps réel) ----------
if ($action === 'listVoyages') {
    $statut = isset($_GET['statut']) && in_array($_GET['statut'], ['SAISI', 'CLOS'], true) ? $_GET['statut'] : null;
    $includeCanceled = isset($_GET['include_canceled']) && $_GET['include_canceled'] === '1';
    $limit = isset($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 200;
    $clauses = [];
    if ($statut) $clauses[] = 'v.statut = :statut';
    if (!$includeCanceled) $clauses[] = "v.statut <> 'ANNULE'";
    $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';
    $sql = "SELECT v.*, p.nom prestataire, c.matricule, ch.nom chauffeur, ch.telephone,
            b.id AS bon_id, b.numero bon_numero, b.fichier_path,
            op.lib_depollution_operation AS operation_label
            FROM depollution_voyage v
            JOIN depollution_prestataire p ON v.prestataire_id=p.id
            JOIN depollution_camion c ON v.camion_id=c.id
            JOIN depollution_chauffeur ch ON v.chauffeur_id=ch.id
            JOIN depollution_bon_sortie b ON v.bon_sortie_id=b.id
            LEFT JOIN depollution_operation op ON v.operation_id = op.id_depollution_operation
            $where
            ORDER BY v.id DESC LIMIT :lim";
    $st = $pdo->prepare($sql);
    if ($statut) $st->bindValue(':statut', $statut);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok' => true, 'data' => $rows, 'include_canceled' => $includeCanceled ? 1 : 0]);
}

// Configuration carburant (prix litre etc.)
if ($action === 'configCarburant') {
    json_out(['ok' => true, 'prix_litre' => DEPOLLUTION_CARBURANT_PRIX_LITRE]);
}

// ---------- Action inconnue ----------
json_out(['ok' => false, 'error' => 'Action inconnue'], 400);

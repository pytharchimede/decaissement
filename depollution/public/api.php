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
    $v = strtoupper(trim((string)$val));
    if ($v === '' || $v === '-' || $v === '0' || $v === '- CFA') return 0.0;
    $v = str_replace(['CFA', 'F CFA', 'FCFA', 'F'], '', $v);
    $v = preg_replace('~[^0-9,.-]+~', '', $v);
    if (substr_count($v, ',') > 1) {
        $parts = explode(',', $v);
        $dec = array_pop($parts);
        $v = preg_replace('~,~', '', implode('', $parts)) . ',' . $dec;
    }
    $v = str_replace(',', '.', $v);
    $v = str_replace(' ', '', $v);
    if ($v === '' || $v === '-' || $v === '.') return 0.0;
    return (float)$v;
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
    $v = str_replace([' ', "\xC2\xA0"], '', str_replace(',', '.', trim((string)$val)));
    if (!is_numeric($v)) return $default;
    return (float)$v;
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
        if ($bon) {
            $bonId = (int)$bon['id'];
        } else {
            $fichierPath = null;
            if (!empty($_FILES['bon_fichier']) && $_FILES['bon_fichier']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../storage/bons';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
                $dest = $uploadDir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['bon_fichier']['name']);
                move_uploaded_file($_FILES['bon_fichier']['tmp_name'], $dest);
                $fichierPath = $dest;
            }
            $st = $pdo->prepare('INSERT INTO depollution_bon_sortie (numero, fichier_path) VALUES (?,?)');
            $st->execute([$payload['bon_numero'], $fichierPath]);
            $bonId = (int)$pdo->lastInsertId();
        }

        // voyage
        $st = $pdo->prepare('INSERT INTO depollution_voyage (date_voyage, prestataire_id, chauffeur_id, camion_id, bon_sortie_id, montant_origine, reel_recu) VALUES (?,?,?,?,?,?,?)');
        $reel = $montantOrigine; // frais route et carburant non encore déduits
        $st->execute([$date, $prestId, $chauffeurId, $camionId, $bonId, $montantOrigine, $reel]);
        $voyageId = (int)$pdo->lastInsertId();
        $pdo->commit();
        json_out(['ok' => true, 'voyage_id' => $voyageId]);
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
        $st = $pdo->prepare('SELECT montant_origine FROM depollution_voyage WHERE id=?');
        $st->execute([$voyageId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_out(['ok' => false, 'error' => 'Voyage introuvable'], 404);
        $montantOrigine = (float)$row['montant_origine'];
        $reel = $montantOrigine - $frais - $carbM;
        $st = $pdo->prepare('UPDATE depollution_voyage SET frais_route=?, carburant_litre=?, carburant_montant=?, reel_recu=?, statut="CLOS" WHERE id=?');
        $st->execute([$frais, $carbL, $carbM, $reel, $voyageId]);
        json_out(['ok' => true, 'reel_recu' => $reel]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

// ---------- Listing voyages (temps réel) ----------
if ($action === 'listVoyages') {
    $statut = isset($_GET['statut']) && in_array($_GET['statut'], ['SAISI', 'CLOS']) ? $_GET['statut'] : null;
    $limit = isset($_GET['limit']) ? max(1, min(1000, (int)$_GET['limit'])) : 200;
    $where = $statut ? 'WHERE v.statut = :statut' : '';
    $sql = "SELECT v.*, p.nom prestataire, c.matricule, ch.nom chauffeur, ch.telephone, b.numero bon_numero
            FROM depollution_voyage v
            JOIN depollution_prestataire p ON v.prestataire_id=p.id
            JOIN depollution_camion c ON v.camion_id=c.id
            JOIN depollution_chauffeur ch ON v.chauffeur_id=ch.id
            JOIN depollution_bon_sortie b ON v.bon_sortie_id=b.id
            $where
            ORDER BY v.id DESC LIMIT $limit";
    $st = $pdo->prepare($sql);
    if ($statut) $st->bindValue(':statut', $statut);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    json_out(['ok' => true, 'data' => $rows]);
}

// Configuration carburant (prix litre etc.)
if ($action === 'configCarburant') {
    json_out(['ok' => true, 'prix_litre' => DEPOLLUTION_CARBURANT_PRIX_LITRE]);
}

// ---------- Action inconnue ----------
json_out(['ok' => false, 'error' => 'Action inconnue'], 400);

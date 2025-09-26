<?php
session_start();

require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/Fiche.php';
require_once __DIR__ . '/model/DemandeEssence.php';
require_once __DIR__ . '/model/WhatsAppSMS.php';
require_once __DIR__ . '/model/SmsSender.php';
require_once __DIR__ . '/model/Config.php';

$pdo = Database::getConnection();
$ficheObj = new Fiche($pdo);
$demandeObj = new DemandeEssence($pdo);
$smsSender = new SmsSender();

// Config WhatsApp (reprend les valeurs existantes du projet)
$sid = AppConfig::twilioSid();
$token = AppConfig::twilioToken();
$from = AppConfig::whatsappFrom();
$whatsapp = new WhatsAppSMS($sid, $token, $from);

// Numéro gérante (WhatsApp et SMS)
$numeroGerant = AppConfig::geranteSms(); // envoi SMS sans +
$numeroGerantWhatsApp = AppConfig::geranteWhatsapp(); // WhatsApp en +225...

// Helper: génère le code bon comme dans valider_fiche_carburant.php
function generateCodeBon(array $fiche): string
{
    $num_fiche = $fiche['num_fiche'];
    $date = new DateTime($fiche['date_creat_fiche']);
    return 'BE-' . $date->format('ym') . '-' . substr(str_pad($num_fiche, 5, '0', STR_PAD_LEFT), -5);
}

// Exécuter la requête d'extraction des fiches EXPRESS DEPOLLUTION
function fetchExpressFiches(PDO $pdo, ?string $sqlOverride = null): array
{
    $sql = $sqlOverride ?: "SELECT * FROM fiche WHERE code_autorisation_feb LIKE '%EXP-DEP-%' ORDER BY date_creat_fiche DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Analyse: parser la précision carburant pour extraire quelques champs structurés
function parseCarburantPrecision(?string $text): array
{
    $res = [
        'nom' => null,
        'matricule' => null,
        'telephone' => null,
        'quantite' => null,
        'unite' => null,
        'carburant' => null,
        'frais_route' => null,
        'solde' => null,
    ];
    if (!$text) return $res;
    $t = (string)$text;

    if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) {
        $res['nom'] = trim($m[1]);
    }
    if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['matricule'] = trim($m[1]);
    }
    if (preg_match('/T[ée]l[ée]?phone\s*:\s*([^\r\n]+)/mi', $t, $m) || preg_match('/Tel\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['telephone'] = trim($m[1]);
    }
    if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)\s*(m3|m³|L|litres?)?/mi', $t, $m)) {
        $res['quantite'] = (float)str_replace(',', '.', $m[1]);
        $res['unite'] = isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null;
    }
    if (preg_match('/(Carburant|Type)\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $res['carburant'] = trim($m[2]);
    }
    if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $res['frais_route'] = (int)preg_replace('/\D+/', '', $m[1]);
    }
    if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $res['solde'] = (int)preg_replace('/\D+/', '', $m[1]);
    }
    return $res;
}

// Vérifie si une fiche enfant (créée automatiquement) existe déjà pour un parent/type
function hasChildFiche(PDO $pdo, string $parentNum, string $type): bool
{
    $type = strtolower($type);
    $likeDes = $type === 'frais' ? 'Frais de route%' : 'Solde%';
    $likeParent = '%Parent: ' . $parentNum . '%';
    $sql = "SELECT COUNT(*) FROM fiche WHERE designation_fiche LIKE :des AND precision_fiche LIKE :par";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':des' => $likeDes, ':par' => $likeParent]);
    return ((int)$stmt->fetchColumn()) > 0;
}

// Crée une fiche enfant (frais de route ou solde) à partir d'une fiche parent
function createChildFiche(Fiche $ficheObj, array $parent, string $type, int $amount): array
{
    $type = strtolower($type);
    $label = $type === 'frais' ? 'Frais de route' : 'Solde';
    $newNum = $ficheObj->generateNumFiche();

    // Copie sécurisée des champs
    $g = function (array $arr, string $k, $def = null) {
        return isset($arr[$k]) ? $arr[$k] : $def;
    };

    $data = [
        'beficiaire_fiche' => (string)$g($parent, 'beficiaire_fiche', $g($parent, 'beneficiaire_fiche', '')),
        'montant_fiche' => $amount,
        'tel_beneficiaire_fiche' => (string)$g($parent, 'tel_beneficiaire_fiche', ''),
        'date_creat_fiche' => gmdate('Y-m-d H:i:s'),
        'num_fiche' => $newNum,
        'affectation_id' => $g($parent, 'affectation_id', 0),
        'designation_fiche' => $label . (isset($parent['designation_fiche']) && $parent['designation_fiche'] ? ' - ' . $parent['designation_fiche'] : ''),
        'num_piece' => 'AUTO-' . strtoupper($type) . '-' . (string)$g($parent, 'num_fiche', ''),
        'chantier_id' => $g($parent, 'chantier_id', 0),
        'precision_fiche' => 'HÉRITIER AUTOMATIQUE: ' . $label . ' | Parent: ' . (string)$g($parent, 'num_fiche', '') . ' | Source carburant.',
        'serv_bureau_banamur_id' => $g($parent, 'serv_bureau_banamur_id', 0),
        'code_autorisation_feb' => (string)$g($parent, 'code_autorisation_feb', ''),
        'photo_beneficiaire' => '',
        'cni_beneficiaire' => '',
        'entreprise' => (string)$g($parent, 'entreprise', ''),
    ];

    $ok = $ficheObj->insertFiche($data);
    if (!$ok) {
        throw new RuntimeException("Échec de création de la fiche enfant");
    }
    return ['num_fiche' => $newNum, 'designation' => $data['designation_fiche']];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'search';
$searchResults = [];
$generationReport = [];
$analysis = [];
$analysisReport = [];

// SQL personnalisé avec coloration et validation (par défaut EXP-DEP)
$defaultSql = "SELECT * FROM fiche WHERE code_autorisation_feb LIKE '%EXP-DEP-%' ORDER BY date_creat_fiche DESC";
$customSql = isset($_POST['custom_sql']) ? (string)$_POST['custom_sql'] : (isset($_GET['custom_sql']) ? (string)$_GET['custom_sql'] : '');
// Fallback sur session si aucun SQL fourni cette fois-ci
if ($customSql === '' && isset($_SESSION['batch_custom_sql']) && is_string($_SESSION['batch_custom_sql'])) {
    $customSql = $_SESSION['batch_custom_sql'];
}

function validateSelectSql(string $sql): array
{
    $errors = [];
    $trim = trim($sql);
    if ($trim === '') {
        return [false, ["SQL vide: utilisation de la requête par défaut."]];
    }
    // Autoriser un seul statement SELECT
    $body = rtrim($trim, ";\r\n\t ");
    if (stripos($body, 'select') !== 0) {
        $errors[] = "La requête doit commencer par SELECT.";
    }
    $lower = strtolower($body);
    if (strpos($lower, ' from ') === false || strpos($lower, ' from fiche') === false) {
        $errors[] = "La requête doit sélectionner depuis la table fiche.";
    }
    $forbidden = [' update ', ' delete ', ' insert ', ' drop ', ' alter ', ' truncate ', ' create ', ' grant ', ' revoke '];
    foreach ($forbidden as $bad) {
        if (strpos($lower, $bad) !== false) {
            $errors[] = "Mot-clé interdit détecté: $bad";
        }
    }
    if (substr_count($body, ';') > 0) {
        $errors[] = "Plusieurs statements non autorisés.";
    }
    return [empty($errors), $errors];
}

list($sqlOk, $sqlErrors) = validateSelectSql($customSql);
$effectiveSql = $sqlOk ? trim($customSql) : $defaultSql;
$editorSql = ($customSql !== '') ? $customSql : $defaultSql;
// Mémoriser l'éditeur en session
$_SESSION['batch_custom_sql'] = $editorSql;

if ($action === 'search') {
    $searchResults = fetchExpressFiches($pdo, $effectiveSql);
}

if ($action === 'analyse') {
    $searchResults = fetchExpressFiches($pdo, $effectiveSql);
    foreach ($searchResults as $f) {
        $parsed = parseCarburantPrecision($f['precision_fiche'] ?? '');
        $analysis[] = [
            'fiche' => $f,
            'parsed' => $parsed,
            'has_frais' => hasChildFiche($pdo, (string)$f['num_fiche'], 'frais'),
            'has_solde' => hasChildFiche($pdo, (string)$f['num_fiche'], 'solde'),
        ];
    }
}

if ($action === 'create_child') {
    $parentNum = isset($_POST['parent_num']) ? (string)$_POST['parent_num'] : '';
    $childType = isset($_POST['child_type']) ? strtolower((string)$_POST['child_type']) : '';
    $amount = isset($_POST['amount']) ? (int)preg_replace('/\D+/', '', (string)$_POST['amount']) : 0;
    $validType = in_array($childType, ['frais', 'solde'], true);
    if ($parentNum === '' || !$validType || $amount <= 0) {
        $analysisReport[] = 'Paramètres invalides pour la création.';
    } else {
        $parent = $ficheObj->getByNumFiche($parentNum);
        if (!$parent || !is_array($parent)) {
            $analysisReport[] = 'Fiche parent introuvable: ' . htmlspecialchars($parentNum);
        } elseif (hasChildFiche($pdo, (string)$parent['num_fiche'], $childType)) {
            $analysisReport[] = 'Déjà existant: ' . $childType . ' pour parent ' . htmlspecialchars((string)$parent['num_fiche']);
        } else {
            try {
                $created = createChildFiche($ficheObj, $parent, $childType, $amount);
                $analysisReport[] = 'Créé: ' . $childType . ' -> fiche ' . $created['num_fiche'];
            } catch (Throwable $e) {
                $analysisReport[] = 'Erreur de création: ' . $e->getMessage();
            }
        }
    }
    // Recharger résultats et analyse
    $searchResults = fetchExpressFiches($pdo, $effectiveSql);
    foreach ($searchResults as $f) {
        $parsed = parseCarburantPrecision($f['precision_fiche'] ?? '');
        $analysis[] = [
            'fiche' => $f,
            'parsed' => $parsed,
            'has_frais' => hasChildFiche($pdo, (string)$f['num_fiche'], 'frais'),
            'has_solde' => hasChildFiche($pdo, (string)$f['num_fiche'], 'solde'),
        ];
    }
}

if ($action === 'generate') {
    // Construire la liste des numéros de fiches à traiter (préserver les zéros à gauche)
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? array_map('strval', $_POST['selected']) : [];
    if (empty($selected) && isset($_POST['generate_all']) && $_POST['generate_all'] === '1') {
        $all = fetchExpressFiches($pdo, $effectiveSql);
        $selected = array_map(fn($f) => (string)$f['num_fiche'], $all);
    }

    // Pour éviter duplication lors d'un refresh
    $selected = array_values(array_unique(array_filter($selected, fn($v) => $v !== '')));

    foreach ($selected as $num) {
        try {
            $fiche = $ficheObj->getByNumFiche($num);
            if (!$fiche || !is_array($fiche)) {
                $generationReport[] = [
                    'num_fiche' => $num,
                    'status' => 'error',
                    'message' => "Fiche introuvable",
                    'wa_benef' => '-',
                    'wa_ger' => '-',
                    'sms' => '-'
                ];
                continue;
            }

            $code_bon = generateCodeBon($fiche);
            $exists = $demandeObj->getByCodeBon($code_bon);
            if ($exists) {
                $generationReport[] = [
                    'num_fiche' => $num,
                    'code_bon' => $code_bon,
                    'status' => 'skipped',
                    'message' => 'Bon déjà existant',
                    'wa_benef' => 'skipped',
                    'wa_ger' => 'skipped',
                    'sms' => 'skipped',
                    'view_url' => 'bon/bon_essence.php?id_bon=' . urlencode($code_bon),
                    'serve_url' => 'bon/servir_essence.php?id_bon=' . urlencode($code_bon)
                ];
                continue;
            }

            // Construire les données du bon
            $parsed = parseCarburantPrecision($fiche['precision_fiche'] ?? '');
            $qte = is_array($parsed) && isset($parsed['quantite']) && $parsed['quantite'] !== null ? (float)$parsed['quantite'] : 0;
            $data = [
                'num_fiche'        => $fiche['num_fiche'],
                'code_bon'         => $code_bon,
                'nom_beneficiaire' => $fiche['beficiaire_fiche'],
                'vehicule'         => $fiche['designation_fiche'] ?? '',
                'quantite'         => $qte,
                'montant'          => $fiche['montant_fiche'],
                'date_demande'     => $fiche['date_creat_fiche'],
                'motif'            => $fiche['precision_fiche'] ?? '',
                'dg_nom'           => 'M. Alex Braud'
            ];

            // Création
            $demandeObj->create($data);

            // WhatsApp bénéficiaire (normalisation du numéro)
            $raw = preg_replace('/\D+/', '', (string)$fiche['tel_beneficiaire_fiche']);
            // Retire un éventuel préfixe 225 déjà présent
            if (strpos($raw, '225') === 0) {
                $raw = substr($raw, 3);
            }
            // Retire un éventuel 0 initial
            $raw = ltrim($raw, '0');
            $toBenef = '+225' . $raw;
            $benefName = $fiche['beficiaire_fiche'];
            $waBenef = $whatsapp->sendCarburantBon($toBenef, $benefName, $code_bon);

            // WhatsApp gérante
            $waGer = $whatsapp->sendNotifCreatToGerant(
                $numeroGerantWhatsApp,
                $code_bon,
                $fiche['beficiaire_fiche'],
                (string)$fiche['montant_fiche'],
                (new DateTime($fiche['date_creat_fiche']))->format('d/m/Y H:i'),
                $code_bon,
                $code_bon
            );

            // SMS gérante (legacy)
            $smsResp = $smsSender->sendBonEssenceToGerant(
                $numeroGerant,
                $code_bon,
                $fiche['num_fiche'],
                $fiche['beficiaire_fiche'],
                $fiche['designation_fiche'] ?? '',
                0,
                $fiche['montant_fiche'],
                $fiche['date_creat_fiche'],
                $fiche['precision_fiche'] ?? ''
            );

            $generationReport[] = [
                'num_fiche' => $fiche['num_fiche'],
                'code_bon' => $code_bon,
                'status' => 'success',
                'wa_benef' => $waBenef['status'] ?? 'unknown',
                'wa_ger' => $waGer['status'] ?? 'unknown',
                'sms' => is_string($smsResp) ? (strlen($smsResp) > 200 ? substr($smsResp, 0, 200) . '…' : $smsResp) : 'ok',
                'view_url' => 'bon/bon_essence.php?id_bon=' . urlencode($code_bon),
                'serve_url' => 'bon/servir_essence.php?id_bon=' . urlencode($code_bon)
            ];
        } catch (Throwable $e) {
            $generationReport[] = [
                'num_fiche' => $num,
                'status' => 'error',
                'message' => $e->getMessage(),
                'wa_benef' => '-',
                'wa_ger' => '-',
                'sms' => '-'
            ];
        }
    }

    // Après génération, on recharge les résultats de recherche pour affichage
    $searchResults = fetchExpressFiches($pdo, $effectiveSql);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Batch Génération Bons - Express Dépollution</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/eclipse.css">
    <style>
        .CodeMirror {
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            height: auto;
            min-height: 120px;
            font-size: 0.9rem;
        }
    </style>
    <style>
        .container {
            max-width: 1100px;
        }

        table {
            width: 100%;
        }

        th,
        td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/headers/top_nav.php'; ?>
    <div class="container mx-auto p-4">
        <h1 class="text-xl font-bold text-yellow-600 mb-3">Batch: Fiches EXP-DEP → Génération des bons</h1>

        <!-- Actions -->
        <div class="mb-4">
            <?php if (!empty($sqlErrors) && !$sqlOk): ?>
                <div class="mb-2 p-2 bg-red-50 text-red-700 rounded">
                    <?php foreach ($sqlErrors as $err): ?>
                        <div>- <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="get" class="space-y-2">
                <input type="hidden" name="action" value="search" />
                <textarea id="custom_sql" name="custom_sql" class="hidden"><?= htmlspecialchars($editorSql) ?></textarea>
                <div id="sql_editor"></div>
                <div class="flex space-x-2">
                    <button class="px-3 py-2 bg-gray-800 text-white rounded" type="submit">Exécuter la requête</button>
                    <button type="button" class="px-3 py-2 bg-gray-200 text-gray-800 rounded" id="resetSql">Réinitialiser</button>
                    <button class="px-3 py-2 bg-indigo-600 text-white rounded" name="action" value="analyse" title="Analyser les fiches carburant (parse, héritiers)">Analyser</button>
                    <a class="px-3 py-2 bg-yellow-300 text-black rounded font-semibold" href="recap_carburant.php?scope=batch" title="Ouvrir le récap/analytique carburant (fiches filtrées)" target="_blank">Récap carburant</a>
                </div>
            </form>
        </div>

        <!-- Résultats de recherche -->
        <div class="bg-white rounded shadow p-3 mb-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold">Fiches trouvées (<?= count($searchResults) ?>)</h2>
                <?php if (!empty($searchResults)) : ?>
                    <form method="post" onsubmit="return confirm('Générer les bons pour la sélection ?');">
                        <input type="hidden" name="action" value="generate" />
                        <input type="hidden" name="generate_all" value="0" />
                        <input type="hidden" name="custom_sql" value="<?= htmlspecialchars($effectiveSql) ?>" />
                        <button type="submit" class="px-3 py-2 bg-yellow-500 text-white rounded">Générer pour la sélection</button>
                        <button type="submit" class="px-3 py-2 bg-green-600 text-white rounded" name="generate_all" value="1">Générer pour tous</button>
                    <?php endif; ?>
            </div>

            <?php if (empty($searchResults)) : ?>
                <p class="text-gray-500">Aucune fiche EXP-DEP trouvée.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr class="text-left text-sm text-gray-600">
                            <th style="width: 36px;"><input type="checkbox" id="checkAll" /></th>
                            <th>N° Fiche</th>
                            <th>Date</th>
                            <th>Bénéficiaire</th>
                            <th>Montant</th>
                            <th>Code Autorisation</th>
                            <th>Code Bon (prévu)</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($searchResults as $f): $codePrev = generateCodeBon($f); ?>
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($f['num_fiche']) ?>" class="chk" /></td>
                                <td><?= htmlspecialchars($f['num_fiche']) ?></td>
                                <td><?= htmlspecialchars((new DateTime($f['date_creat_fiche']))->format('d/m/Y H:i')) ?></td>
                                <td><?= htmlspecialchars($f['beficiaire_fiche']) ?></td>
                                <td><?= number_format((float)$f['montant_fiche'], 0, ',', ' ') ?></td>
                                <td><?= htmlspecialchars($f['code_autorisation_feb']) ?></td>
                                <td class="font-mono text-gray-700"><?= htmlspecialchars($codePrev) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($searchResults)) : ?></form><?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Analyse Carburant -->
        <?php if ($action === 'analyse' || $action === 'create_child'): ?>
            <div class="bg-white rounded shadow p-3 mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-semibold">Analyse Carburant (<?= count($analysis) ?>)</h2>
                </div>
                <?php if (!empty($analysisReport)): ?>
                    <div class="mb-3 p-2 bg-blue-50 text-blue-800 rounded">
                        <?php foreach ($analysisReport as $msg): ?>
                            <div>- <?= htmlspecialchars($msg) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($analysis)): ?>
                    <p class="text-gray-500">Aucun résultat à analyser.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr class="text-left text-sm text-gray-600">
                                <th>N° Fiche</th>
                                <th>Bénéficiaire</th>
                                <th>Désignation</th>
                                <th>Quantité</th>
                                <th>Carburant</th>
                                <th>Frais de route</th>
                                <th>Solde</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <?php foreach ($analysis as $row): $f = $row['fiche'];
                                $p = $row['parsed']; ?>
                                <tr>
                                    <td class="font-mono text-gray-700"><?= htmlspecialchars($f['num_fiche']) ?></td>
                                    <td><?= htmlspecialchars($f['beficiaire_fiche']) ?></td>
                                    <td><?= htmlspecialchars($f['designation_fiche'] ?? '') ?></td>
                                    <td>
                                        <?php if ($p['quantite'] !== null): ?>
                                            <?= htmlspecialchars((string)$p['quantite']) ?><?= $p['unite'] ? ' ' . htmlspecialchars($p['unite']) : '' ?>
                                            <?php else: ?>-
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $p['carburant'] !== null ? htmlspecialchars($p['carburant']) : '-' ?></td>
                                    <td><?= $p['frais_route'] !== null ? number_format((int)$p['frais_route'], 0, ',', ' ') : '-' ?></td>
                                    <td><?= $p['solde'] !== null ? number_format((int)$p['solde'], 0, ',', ' ') : '-' ?></td>
                                    <td>
                                        <div class="space-y-1">
                                            <form method="post" class="inline">
                                                <input type="hidden" name="action" value="create_child" />
                                                <input type="hidden" name="custom_sql" value="<?= htmlspecialchars($effectiveSql) ?>" />
                                                <input type="hidden" name="parent_num" value="<?= htmlspecialchars($f['num_fiche']) ?>" />
                                                <input type="hidden" name="child_type" value="frais" />
                                                <input type="hidden" name="amount" value="<?= (int)($p['frais_route'] ?? 20000) ?>" />
                                                <button class="px-2 py-1 text-xs rounded <?= $row['has_frais'] ? 'bg-gray-300 text-gray-700' : 'bg-yellow-500 text-white' ?>" <?= $row['has_frais'] ? 'disabled' : '' ?>>Créer Frais (<?= number_format((int)($p['frais_route'] ?? 20000), 0, ',', ' ') ?>)</button>
                                            </form>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="action" value="create_child" />
                                                <input type="hidden" name="custom_sql" value="<?= htmlspecialchars($effectiveSql) ?>" />
                                                <input type="hidden" name="parent_num" value="<?= htmlspecialchars($f['num_fiche']) ?>" />
                                                <input type="hidden" name="child_type" value="solde" />
                                                <input type="hidden" name="amount" value="<?= (int)($p['solde'] ?? 146250) ?>" />
                                                <button class="px-2 py-1 text-xs rounded <?= $row['has_solde'] ? 'bg-gray-300 text-gray-700' : 'bg-green-600 text-white' ?>" <?= $row['has_solde'] ? 'disabled' : '' ?>>Créer Solde (<?= number_format((int)($p['solde'] ?? 146250), 0, ',', ' ') ?>)</button>
                                            </form>
                                            <!-- Saisie personnalisée -->
                                            <form method="post" class="flex items-center space-x-1">
                                                <input type="hidden" name="action" value="create_child" />
                                                <input type="hidden" name="custom_sql" value="<?= htmlspecialchars($effectiveSql) ?>" />
                                                <input type="hidden" name="parent_num" value="<?= htmlspecialchars($f['num_fiche']) ?>" />
                                                <select name="child_type" class="border rounded px-1 py-0.5 text-xs">
                                                    <option value="frais">Frais</option>
                                                    <option value="solde">Solde</option>
                                                </select>
                                                <input type="number" name="amount" class="border rounded px-1 py-0.5 text-xs w-24" placeholder="Montant" min="1" />
                                                <button class="px-2 py-1 text-xs rounded bg-indigo-600 text-white">Créer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Rapport de génération -->
        <?php if (!empty($generationReport)) : ?>
            <div class="bg-white rounded shadow p-3">
                <h2 class="font-semibold mb-2">Rapport de génération (<?= count($generationReport) ?>)</h2>
                <table>
                    <thead>
                        <tr class="text-left text-sm text-gray-600">
                            <th>N° Fiche</th>
                            <th>Code Bon</th>
                            <th>Statut</th>
                            <th>WhatsApp Bénéf.</th>
                            <th>WhatsApp Gérante</th>
                            <th>SMS</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($generationReport as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$r['num_fiche']) ?></td>
                                <td class="font-mono text-gray-700"><?= htmlspecialchars($r['code_bon'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['status']) ?><?= isset($r['message']) ? ' - ' . htmlspecialchars($r['message']) : '' ?></td>
                                <td><?= htmlspecialchars($r['wa_benef'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['wa_ger'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(is_string($r['sms'] ?? '') ? $r['sms'] : '-') ?></td>
                                <td>
                                    <?php if (!empty($r['view_url'])): ?>
                                        <a class="text-blue-600 hover:underline" target="_blank" href="<?= htmlspecialchars($r['view_url']) ?>">Voir</a>
                                    <?php endif; ?>
                                    <?php if (!empty($r['serve_url'])): ?>
                                        &nbsp;|&nbsp;
                                        <a class="text-green-700 hover:underline" target="_blank" href="<?= htmlspecialchars($r['serve_url']) ?>">Servir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/sql/sql.js"></script>
    <script>
        const checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', (e) => {
                document.querySelectorAll('.chk').forEach(ch => ch.checked = e.target.checked);
            });
        }

        // CodeMirror SQL editor setup
        const ta = document.getElementById('custom_sql');
        if (ta) {
            const editor = CodeMirror(document.getElementById('sql_editor'), {
                value: ta.value,
                mode: 'text/x-sql',
                theme: 'eclipse',
                lineNumbers: true,
                lineWrapping: true,
                viewportMargin: Infinity
            });
            const form = ta.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    ta.value = editor.getValue();
                });
            }
            const resetBtn = document.getElementById('resetSql');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    editor.setValue(`<?= str_replace(["\r", "\n"], ["\\r", "\\n"], addslashes($defaultSql)) ?>`);
                });
            }
        }
    </script>
</body>

</html>
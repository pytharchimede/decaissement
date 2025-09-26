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
function fetchExpressFiches(PDO $pdo): array
{
    $sql = "SELECT * FROM fiche WHERE code_autorisation_feb LIKE '%EXP-DEP-%' ORDER BY date_creat_fiche DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'search';
$searchResults = [];
$generationReport = [];

if ($action === 'search') {
    $searchResults = fetchExpressFiches($pdo);
}

if ($action === 'generate') {
    // Construire la liste des numéros de fiches à traiter
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? array_map('intval', $_POST['selected']) : [];
    if (empty($selected) && isset($_POST['generate_all']) && $_POST['generate_all'] === '1') {
        $all = fetchExpressFiches($pdo);
        $selected = array_map(fn($f) => (int)$f['num_fiche'], $all);
    }

    // Pour éviter duplication lors d'un refresh
    $selected = array_values(array_unique(array_filter($selected)));

    foreach ($selected as $num) {
        try {
            $fiche = $ficheObj->getByNumFiche($num);
            if (!$fiche) {
                $generationReport[] = [
                    'num_fiche' => $num,
                    'status' => 'error',
                    'message' => "Fiche introuvable"
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
                    'view_url' => 'bon/bon_essence.php?id_bon=' . urlencode($code_bon)
                ];
                continue;
            }

            // Construire les données du bon
            $data = [
                'num_fiche'        => $fiche['num_fiche'],
                'code_bon'         => $code_bon,
                'nom_beneficiaire' => $fiche['beficiaire_fiche'],
                'vehicule'         => $fiche['designation_fiche'] ?? '',
                'quantite'         => 0,
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
                'message' => $e->getMessage()
            ];
        }
    }

    // Après génération, on recharge les résultats de recherche pour affichage
    $searchResults = fetchExpressFiches($pdo);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Batch Génération Bons - Express Dépollution</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" />
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
        <div class="flex space-x-2 mb-4">
            <form method="get" class="inline">
                <input type="hidden" name="action" value="search" />
                <button class="px-3 py-2 bg-gray-800 text-white rounded">Exécuter la requête</button>
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
                        <button class="px-3 py-2 bg-yellow-500 text-white rounded">Générer pour la sélection</button>
                        <button class="px-3 py-2 bg-green-600 text-white rounded" name="generate_all" value="1" onclick="this.form.submit(); return false;">Générer pour tous</button>
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
                                <td><input type="checkbox" name="selected[]" value="<?= (int)$f['num_fiche'] ?>" class="chk" /></td>
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

    <script>
        const checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', (e) => {
                document.querySelectorAll('.chk').forEach(ch => ch.checked = e.target.checked);
            });
        }
    </script>
</body>

</html>
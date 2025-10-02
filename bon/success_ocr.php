<?php
session_start();
$r = $_SESSION['ocr_result'] ?? null;
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OCR — Succès</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            margin: 0;
            display: grid;
            place-items: center;
            height: 100vh;
            background: #07160e;
            color: #d4f7df;
        }

        .panel {
            text-align: center;
            padding: 24px;
            border: 1px solid #0b3b2a;
            border-radius: 16px;
            background: #0f1713;
        }

        .panel h1 {
            color: #00ff88;
        }

        a {
            color: #00ff88;
        }
    </style>
</head>

<body>
    <div class="panel">
        <h1>Analyse réussie</h1>
        <p>Le reçu est conforme (montant et station VINKO).</p>
        <?php if ($r): ?>
            <div style="text-align:left;max-width:680px;margin:16px auto;">
                <?php if (!empty($r['image_web'])): ?>
                    <p><img src="<?= htmlspecialchars($r['image_web']) ?>" alt="Reçu" style="max-width:100%;border-radius:8px;border:1px solid #0b3b2a;"></p>
                <?php endif; ?>
                <p><strong>Code bon:</strong> <?= htmlspecialchars((string)($r['code_bon'] ?? '')) ?></p>
                <p><strong>Montant attendu:</strong> <?= isset($r['expected_amount']) ? number_format((int)$r['expected_amount'], 0, ',', ' ') . ' FCFA' : '—' ?></p>
                <?php if (!empty($r['extracted'])): ?>
                    <p><strong>Montant détecté:</strong> <?= $r['extracted']['amount'] !== null ? number_format((int)$r['extracted']['amount'], 0, ',', ' ') . ' FCFA' : '—' ?></p>
                    <p><strong>Station VINKO:</strong> <?= ($r['extracted']['station']['matched'] ?? false) ? 'Oui' : 'Non' ?>
                        <?php if ($r['extracted']['station']['matched'] ?? false): ?>
                            <small>(match: <?= htmlspecialchars((string)($r['extracted']['station']['match'] ?? '')) ?>, méthode: <?= htmlspecialchars((string)($r['extracted']['station']['method'] ?? '')) ?>)</small>
                        <?php endif; ?>
                    </p>
                    <p><strong>Chauffeur/Conducteur:</strong> <?= !empty($r['extracted']['chauffeur_name']) ? htmlspecialchars((string)$r['extracted']['chauffeur_name']) : '—' ?></p>
                    <p><strong>Bénéficiaire:</strong> <?= !empty($r['extracted']['beneficiaire_name']) ? htmlspecialchars((string)$r['extracted']['beneficiaire_name']) : '—' ?></p>
                    <p><strong>Signature présente:</strong> <?= !empty($r['extracted']['signature_present']) ? 'Oui' : 'Non' ?></p>
                    <p><strong>Numéro de reçu:</strong> <?= !empty($r['extracted']['receipt_number']) ? htmlspecialchars((string)$r['extracted']['receipt_number']) : '—' ?></p>
                    <p><strong>Date:</strong> <?= !empty($r['extracted']['date']) ? htmlspecialchars((string)$r['extracted']['date']) : '—' ?></p>
                    <p><strong>Quantité (L):</strong> <?= $r['extracted']['quantity_liters'] !== null ? htmlspecialchars((string)$r['extracted']['quantity_liters']) : '—' ?></p>
                    <p><strong>Véhicule/Immat.:</strong> <?= !empty($r['extracted']['vehicle']) ? htmlspecialchars((string)$r['extracted']['vehicle']) : '—' ?></p>
                <?php endif; ?>
                <details>
                    <summary style="cursor:pointer;color:#00ff88;">Voir texte OCR</summary>
                    <pre style="white-space:pre-wrap;background:#0c1410;border:1px solid #0b3b2a;padding:12px;border-radius:8px;"><?= htmlspecialchars((string)($r['ocr_text'] ?? '')) ?></pre>
                </details>
            </div>
        <?php endif; ?>
        <p><a href="test_ocr.php">Retour aux tests</a></p>
    </div>
</body>

</html>
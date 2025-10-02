<?php
session_start();
$r = $_SESSION['ocr_result'] ?? null;
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OCR — Échec</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial;
            margin: 0;
            display: grid;
            place-items: center;
            height: 100vh;
            background: #1a0a0a;
            color: #ffd6d6;
        }

        .panel {
            text-align: center;
            padding: 24px;
            border: 1px solid #5b1a1a;
            border-radius: 16px;
            background: #2a0f0f;
        }

        .panel h1 {
            color: #ff4d4f;
        }

        a {
            color: #ffd6d6;
        }
    </style>
</head>

<body>
    <div class="panel">
        <h1>Analyse échouée</h1>
        <p>Le reçu n'est pas conforme (montant et/ou station VINKO non reconnus).</p>
        <?php if ($r): ?>
            <div style="text-align:left;max-width:680px;margin:16px auto;">
                <?php if (!empty($r['image_web'])): ?>
                    <p><img src="<?= htmlspecialchars($r['image_web']) ?>" alt="Reçu" style="max-width:100%;border-radius:8px;border:1px solid #5b1a1a;"></p>
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
                <?php if (!empty($r['validation']['errors'])): ?>
                    <p><strong>Erreurs:</strong></p>
                    <ul>
                        <?php foreach ($r['validation']['errors'] as $e): ?>
                            <li><?= htmlspecialchars((string)$e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <details>
                    <summary style="cursor:pointer;color:#ffd6d6;">Voir texte OCR</summary>
                    <pre style="white-space:pre-wrap;background:#2a0f0f;border:1px solid #5b1a1a;padding:12px;border-radius:8px;"><?= htmlspecialchars((string)($r['ocr_text'] ?? '')) ?></pre>
                </details>
            </div>
        <?php endif; ?>
        <p><a href="/bon/test_ocr.php">Retour aux tests</a></p>
    </div>
</body>

</html>
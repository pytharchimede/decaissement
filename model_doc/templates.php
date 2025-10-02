<?php
// Wrapper pour la gestion des modèles, avec menu commun
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Gestion des modèles</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <div class="row" style="margin:8px 0;justify-content:space-between;align-items:center">
            <div class="muted">Gérez les modèles côté admin, puis revenez ici.</div>
            <div>
                <a class="btn" href="builder.php">Ouvrir le Builder</a>
            </div>
        </div>
        <iframe src="../admin_document_templates.php" style="width:100%;height:80vh;border:1px solid var(--border);border-radius:12px;background:#fff"></iframe>
    </div>
</body>

</html>
<?php
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>IA Documents - Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <div class="wrap">
        <?php include __DIR__ . '/menu.php'; ?>
        <div class="grid">
            <a class="card" href="templates.php">
                <h2>Gestion des modèles</h2>
                <p>Créer/éditer/supprimer, tester un modèle existant (JSON ou DB).</p>
            </a>
            <a class="card" href="builder.php">
                <h2>Créer un modèle depuis image</h2>
                <p>Uploader une image, cliquer pour sélectionner des mots-clés et dessiner des ROI.</p>
            </a>
            <a class="card" href="test.php">
                <h2>Tester un modèle</h2>
                <p>Uploader une image et voir le verdict (ok/errors) avec détails.</p>
            </a>
        </div>
    </div>
</body>

</html>
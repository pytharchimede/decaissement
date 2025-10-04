<?php
// Formulaire pour créer ou mettre à jour une tâche

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Traitement des données du formulaire
    $taskName = $_POST['task_name'] ?? '';
    $taskDescription = $_POST['task_description'] ?? '';
    $taskDeadline = $_POST['task_deadline'] ?? '';

    // Validation des données (à implémenter)
    // Enregistrement des données dans le fichier JSON (à implémenter)
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/operators.css">
    <title>Formulaire de Tâche</title>
</head>
<body>
    <div class="container">
        <h1>Créer ou Mettre à Jour une Tâche</h1>
        <form action="" method="POST">
            <div class="form-group">
                <label for="task_name">Nom de la Tâche</label>
                <input type="text" id="task_name" name="task_name" required>
            </div>
            <div class="form-group">
                <label for="task_description">Description de la Tâche</label>
                <textarea id="task_description" name="task_description" required></textarea>
            </div>
            <div class="form-group">
                <label for="task_deadline">Date Limite</label>
                <input type="date" id="task_deadline" name="task_deadline" required>
            </div>
            <button type="submit">Soumettre</button>
        </form>
    </div>
</body>
</html>
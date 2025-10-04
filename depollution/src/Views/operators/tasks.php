<?php
// Vue pour gérer les tâches des opérateurs

session_start();

// Vérification de l'authentification de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    header('Location: ../index.php');
    exit();
}

// Chargement des données des tâches depuis le fichier JSON
$tasksJson = file_get_contents('../../data/tasks.json');
$tasks = json_decode($tasksJson, true);

// Affichage des tâches
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/operators.css">
    <title>Gestion des Tâches</title>
</head>
<body>
    <?php include '../partials/header.php'; ?>
    <?php include '../partials/sidebar.php'; ?>

    <main>
        <h1>Gestion des Tâches</h1>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($task['id']); ?></td>
                        <td><?php echo htmlspecialchars($task['description']); ?></td>
                        <td><?php echo htmlspecialchars($task['status']); ?></td>
                        <td>
                            <a href="edit_task.php?id=<?php echo htmlspecialchars($task['id']); ?>">Modifier</a>
                            <a href="delete_task.php?id=<?php echo htmlspecialchars($task['id']); ?>">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <?php include '../partials/footer.php'; ?>
</body>
</html>
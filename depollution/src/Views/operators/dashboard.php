<?php
require_once '../../Models/User.php';
require_once '../../Models/Project.php';
require_once '../../Repositories/JsonDataRepository.php';

session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'operator') {
    header('Location: ../../public/index.php');
    exit();
}

$user = $_SESSION['user'];
$dataRepository = new JsonDataRepository();
$projects = $dataRepository->getProjects();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/operators.css">
    <title>Tableau de Bord - Opérateur</title>
</head>
<body>
    <?php include '../partials/header.php'; ?>
    <?php include '../partials/sidebar.php'; ?>

    <main>
        <h1>Bienvenue, <?php echo htmlspecialchars($user['name']); ?></h1>
        <h2>Projets en cours</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom du Projet</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($project['id']); ?></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['status']); ?></td>
                        <td>
                            <a href="tasks.php?project_id=<?php echo htmlspecialchars($project['id']); ?>">Voir Tâches</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <?php include '../partials/footer.php'; ?>
</body>
</html>
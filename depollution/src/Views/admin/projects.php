<?php
require_once '../../Repositories/ProjectRepository.php';

$projectRepository = new ProjectRepository();
$projects = $projectRepository->getAllProjects();

include '../partials/header.php';
include '../partials/sidebar.php';
?>

<div class="container">
    <h1>Gestion des Projets de Dépollution</h1>
    <a href="project_form.php" class="btn btn-primary">Ajouter un Nouveau Projet</a>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom du Projet</th>
                <th>Description</th>
                <th>Date de Début</th>
                <th>Date de Fin</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo htmlspecialchars($project['id']); ?></td>
                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                    <td><?php echo htmlspecialchars($project['description']); ?></td>
                    <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($project['end_date']); ?></td>
                    <td>
                        <a href="project_form.php?id=<?php echo htmlspecialchars($project['id']); ?>" class="btn btn-warning">Modifier</a>
                        <a href="delete_project.php?id=<?php echo htmlspecialchars($project['id']); ?>" class="btn btn-danger">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../partials/footer.php'; ?>
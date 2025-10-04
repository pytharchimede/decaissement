<?php
require_once '../../partials/header.php';
require_once '../../partials/sidebar.php';
?>

<div class="dashboard">
    <h1>Tableau de bord Administrateur</h1>
    <div class="stats">
        <div class="stat-item">
            <h2>Projets en cours</h2>
            <p id="ongoing-projects">0</p>
        </div>
        <div class="stat-item">
            <h2>Tâches à valider</h2>
            <p id="tasks-to-validate">0</p>
        </div>
        <div class="stat-item">
            <h2>Utilisateurs</h2>
            <p id="total-users">0</p>
        </div>
    </div>
    <div class="actions">
        <h2>Actions rapides</h2>
        <a href="projects.php" class="btn">Gérer les projets</a>
        <a href="users.php" class="btn">Gérer les utilisateurs</a>
        <a href="#" class="btn">Importer des fichiers</a>
    </div>
</div>

<?php
require_once '../../partials/footer.php';
?>
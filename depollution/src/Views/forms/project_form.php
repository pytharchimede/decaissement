<?php
// project_form.php

?>

<form action="/api/projects" method="POST" class="project-form">
    <h2>Créer ou Mettre à Jour un Projet</h2>
    
    <label for="project_name">Nom du Projet:</label>
    <input type="text" id="project_name" name="project_name" required>

    <label for="project_description">Description:</label>
    <textarea id="project_description" name="project_description" required></textarea>

    <label for="project_start_date">Date de Début:</label>
    <input type="date" id="project_start_date" name="project_start_date" required>

    <label for="project_end_date">Date de Fin:</label>
    <input type="date" id="project_end_date" name="project_end_date" required>

    <label for="project_site">Site:</label>
    <select id="project_site" name="project_site" required>
        <!-- Options will be populated dynamically from JSON data -->
    </select>

    <button type="submit">Soumettre</button>
</form>

<script src="/assets/js/app.js"></script>
// File: /depollution/depollution/public/assets/js/admin.js

document.addEventListener('DOMContentLoaded', function() {
    const importButton = document.getElementById('import-button');
    const providerSelect = document.getElementById('provider-select');
    const projectForm = document.getElementById('project-form');

    importButton.addEventListener('click', function() {
        const fileInput = document.getElementById('file-input');
        if (fileInput.files.length === 0) {
            alert('Veuillez sélectionner un fichier à importer.');
            return;
        }
        // Logic to handle file import
        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('file', file);
        fetch('/api/import', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Fichier importé avec succès.');
                // Optionally refresh the provider list or update the UI
            } else {
                alert('Erreur lors de l\'importation du fichier.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue. Veuillez réessayer.');
        });
    });

    providerSelect.addEventListener('change', function() {
        const selectedProvider = providerSelect.value;
        // Logic to load provider-specific data
        fetch(`/api/providers/${selectedProvider}`)
        .then(response => response.json())
        .then(data => {
            // Update the UI with provider data
            console.log(data);
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    });

    projectForm.addEventListener('submit', function(event) {
        event.preventDefault();
        // Logic to handle project form submission
        const formData = new FormData(projectForm);
        fetch('/api/projects', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Projet créé avec succès.');
                // Optionally redirect or update the UI
            } else {
                alert('Erreur lors de la création du projet.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue. Veuillez réessayer.');
        });
    });
});
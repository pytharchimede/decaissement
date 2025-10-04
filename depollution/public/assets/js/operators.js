// File: /depollution/depollution/public/assets/js/operators.js

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('operator-form');
    const submitButton = document.getElementById('submit-button');
    const messageBox = document.getElementById('message-box');

    submitButton.addEventListener('click', function(event) {
        event.preventDefault();
        const formData = new FormData(form);
        const jsonData = {};

        formData.forEach((value, key) => {
            jsonData[key] = value;
        });

        fetch('/depollution/public/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageBox.textContent = 'Données soumises avec succès!';
                messageBox.style.color = 'green';
                form.reset();
            } else {
                messageBox.textContent = 'Erreur lors de la soumission des données.';
                messageBox.style.color = 'red';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            messageBox.textContent = 'Erreur de connexion au serveur.';
            messageBox.style.color = 'red';
        });
    });
});
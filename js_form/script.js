document.addEventListener("DOMContentLoaded", function () {
  // Constantes globales
  const progressBar = document.getElementById("progress-bar");
  const steps = document.querySelectorAll(".setup-content");
  const totalSteps = steps.length;
  let currentStep = 0;

  // Mise à jour de la barre de progression
  function updateProgressBar() {
    const progressPercentage = ((currentStep + 1) / totalSteps) * 100;
    progressBar.style.width = progressPercentage + "%";
    progressBar.textContent = `Étape ${currentStep + 1} sur ${totalSteps}`;

    // Changer la classe en fonction du pourcentage
    progressBar.classList.remove("low", "medium", "high");
    if (progressPercentage <= 33) {
      progressBar.classList.add("low");
    } else if (progressPercentage <= 66) {
      progressBar.classList.add("medium");
    } else {
      progressBar.classList.add("high");
    }
  }

  // Navigation entre étapes
  document.querySelectorAll(".nextBtn").forEach((button) => {
    button.addEventListener("click", function () {
      const targetStep = this.getAttribute("data-target");
      document
        .querySelector(".setup-content.active")
        .classList.remove("active");
      document.querySelector(targetStep).classList.add("active");
      currentStep++;
      updateProgressBar();
    });
  });

  document.querySelectorAll(".prevBtn").forEach((button) => {
    button.addEventListener("click", function () {
      const targetStep = this.getAttribute("data-target");
      document
        .querySelector(".setup-content.active")
        .classList.remove("active");
      document.querySelector(targetStep).classList.add("active");
      currentStep--;
      updateProgressBar();
    });
  });

  // Sélection de l'entreprise
  document.querySelectorAll(".company-card").forEach((card) => {
    card.addEventListener("click", function () {
      document
        .querySelector(".setup-content.active")
        .classList.remove("active");
      document.getElementById("step-1").classList.add("active");
    });
  });

  // Gestion du champ d'affectation
  document
    .getElementById("affectation")
    .addEventListener("change", function () {
      const affectation = this.value;
      document.getElementById("chantier-select").style.display =
        affectation === "chantier" ? "block" : "none";
      document.getElementById("bureau-select").style.display =
        affectation === "bureau" ? "block" : "none";
    });

  // Fonction pour afficher l'aperçu des fichiers
  function displayPreview(input, previewId) {
    const file = input.files[0];
    if (file && file.type.startsWith("image/")) {
      const reader = new FileReader();
      reader.onload = function (e) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu" />`;
      };
      reader.readAsDataURL(file);
    } else {
      alert("Veuillez télécharger une image valide.");
    }
  }

  // Gestion des zones de drag-and-drop
  function setupDropArea(areaId, inputId, previewId) {
    const dropArea = document.getElementById(areaId);
    const fileInput = document.getElementById(inputId);

    dropArea.addEventListener("click", () => fileInput.click());

    dropArea.addEventListener("dragover", (e) => {
      e.preventDefault();
      dropArea.style.backgroundColor = "#eaf4ff";
    });

    dropArea.addEventListener("dragleave", (e) => {
      e.preventDefault();
      dropArea.style.backgroundColor = "#f9f9f9";
    });

    dropArea.addEventListener("drop", (e) => {
      e.preventDefault();
      dropArea.style.backgroundColor = "#f9f9f9";
      fileInput.files = e.dataTransfer.files;
      displayPreview(fileInput, previewId);
    });

    fileInput.addEventListener("change", () =>
      displayPreview(fileInput, previewId)
    );
  }

  // Initialiser les zones de drag-and-drop
  setupDropArea("photo-drop-area", "photo_demandeur", "photo-preview");
  setupDropArea("cni-drop-area", "cni_demandeur", "cni-preview");

  // Effacer la signature
  function clearSignature() {
    var canvas = document.getElementById("signature-canvas");
    var context = canvas.getContext("2d");
    context.clearRect(0, 0, canvas.width, canvas.height);
  }

  document
    .getElementById("clear-signature")
    .addEventListener("click", clearSignature);

  // Fonction pour sauvegarder la signature
  function saveSignature() {
    var canvas = document.getElementById("signature-canvas");
    var dataURL = canvas.toDataURL("image/png");
    document.getElementById("signature-feedback").innerText =
      "Signature enregistrée avec succès !";
    console.log("Signature en base64 :", dataURL);
  }

  document
    .querySelector(".btn-outline-success")
    .addEventListener("click", saveSignature);

  // Gestion de la soumission du formulaire
  document
    .getElementById("form_fiche")
    .addEventListener("submit", function (e) {
      e.preventDefault(); // Empêche le rechargement de la page

      // Collecte des données du formulaire
      var formData = new FormData(this);

      // Ajout des fichiers pour la photo et la CNI, si nécessaires
      var photoFile = document.getElementById("photo_demandeur").files[0];
      var cniFile = document.getElementById("cni_demandeur").files[0];
      if (photoFile) formData.append("photo_demandeur", photoFile);
      if (cniFile) formData.append("cni_demandeur", cniFile);

      console.log(formData);

      // Envoi des données avec AJAX
      $.ajax({
        url: "request/insert_fiche.php", // URL du script serveur
        type: "POST", // Méthode HTTP
        data: formData, // Données à envoyer
        contentType: false, // Nécessaire pour les fichiers
        processData: false, // Nécessaire pour les fichiers
        beforeSend: function () {
          // Affiche un indicateur de chargement, si nécessaire
          $("#submit-button").prop("disabled", true).text("Envoi en cours...");
        },
        success: function (response) {
          // Traitement de la réponse du serveur
          console.log(response); // Affiche la réponse dans la console (pour débogage)
          if (response.success) {
            alert("Demande soumise avec succès !");
            // Réinitialise le formulaire après succès
            document.getElementById("form_fiche").reset();
            $("#photo-preview").html(""); // Efface l'aperçu de la photo
            $("#cni-preview").html(""); // Efface l'aperçu de la CNI
          } else {
            alert(
              response.message || "Une erreur est survenue. Veuillez réessayer."
            );
          }
        },
        error: function (xhr, status, error) {
          // Gestion des erreurs
          console.error("Erreur:", error);
          alert(
            "Erreur lors de l'envoi de la demande. Veuillez vérifier votre connexion."
          );
        },
        complete: function () {
          // Réactive le bouton après traitement
          $("#submit-button").prop("disabled", false).text("Soumettre");
        },
      });
    });
});

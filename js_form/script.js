document.addEventListener("DOMContentLoaded", function () {
  // Gestion de la sélection de l'entreprise
  $(".company-card").click(function () {
    var company = $(this).data("company");
    // Afficher la prochaine étape en fonction de l'entreprise sélectionnée
    $(".setup-content").removeClass("active");
    $("#step-1").addClass("active"); // Passer à l'étape 1 après sélection de l'entreprise
  });

  // Script pour afficher le champ en fonction de l'affectation
  $("#affectation").change(function () {
    var affectation = $(this).val();
    if (affectation == "chantier") {
      $("#chantier-select").show();
      $("#bureau-select").hide();
    } else if (affectation == "bureau") {
      $("#bureau-select").show();
      $("#chantier-select").hide();
    } else {
      $("#chantier-select").hide();
      $("#bureau-select").hide();
    }
  });

  // Fonction pour afficher l'aperçu de l'image sélectionnée
  function displayPreview(input, previewId) {
    var file = input.files[0];
    if (file && file.type.startsWith("image/")) {
      var reader = new FileReader();
      reader.onload = function (e) {
        var preview = document.getElementById(previewId);
        preview.innerHTML =
          '<img src="' + e.target.result + '" alt="Aperçu" />';
      };
      reader.readAsDataURL(file);
    } else {
      alert("Veuillez télécharger une image valide.");
    }
  }

  // Gestion du drag and drop pour Photo
  var photoDropArea = document.getElementById("photo-drop-area");
  photoDropArea.addEventListener("click", function () {
    document.getElementById("photo_demandeur").click();
  });

  photoDropArea.addEventListener("dragover", function (e) {
    e.preventDefault();
    photoDropArea.style.backgroundColor = "#eaf4ff";
  });

  photoDropArea.addEventListener("dragleave", function (e) {
    e.preventDefault();
    photoDropArea.style.backgroundColor = "#f9f9f9";
  });

  photoDropArea.addEventListener("drop", function (e) {
    e.preventDefault();
    photoDropArea.style.backgroundColor = "#f9f9f9";
    var fileInput = document.getElementById("photo_demandeur");
    fileInput.files = e.dataTransfer.files;
    displayPreview(fileInput, "photo-preview");
  });

  // Gestion du drag and drop pour CNI
  var cniDropArea = document.getElementById("cni-drop-area");
  cniDropArea.addEventListener("click", function () {
    document.getElementById("cni_demandeur").click();
  });

  cniDropArea.addEventListener("dragover", function (e) {
    e.preventDefault();
    cniDropArea.style.backgroundColor = "#eaf4ff";
  });

  cniDropArea.addEventListener("dragleave", function (e) {
    e.preventDefault();
    cniDropArea.style.backgroundColor = "#f9f9f9";
  });

  cniDropArea.addEventListener("drop", function (e) {
    e.preventDefault();
    cniDropArea.style.backgroundColor = "#f9f9f9";
    var fileInput = document.getElementById("cni_demandeur");
    fileInput.files = e.dataTransfer.files;
    displayPreview(fileInput, "cni-preview");
  });

  // Fonction de prévisualisation pour les fichiers d'images
  document
    .getElementById("photo_demandeur")
    .addEventListener("change", function () {
      displayPreview(this, "photo-preview");
    });

  document
    .getElementById("cni_demandeur")
    .addEventListener("change", function () {
      displayPreview(this, "cni-preview");
    });

  // Effacer la signature
  function clearSignature() {
    var canvas = document.getElementById("signature-canvas");
    var context = canvas.getContext("2d");
    context.clearRect(0, 0, canvas.width, canvas.height);
  }

  const progressBar = document.getElementById("progress-bar");
  const steps = document.querySelectorAll(".setup-content");
  const totalSteps = steps.length;
  let currentStep = 0;

  // Fonction pour mettre à jour la barre de progression
  function updateProgressBar() {
    const progressPercentage = ((currentStep + 1) / totalSteps) * 100;
    progressBar.style.width = progressPercentage + "%";
    progressBar.textContent = `Étape ${currentStep + 1} sur ${totalSteps}`;

    // Modifier la couleur en fonction du pourcentage
    progressBar.classList.remove("low", "medium", "high");
    if (progressPercentage <= 33) {
      progressBar.classList.add("low");
    } else if (progressPercentage <= 66) {
      progressBar.classList.add("medium");
    } else {
      progressBar.classList.add("high");
    }
  }

  // Gestion des boutons "Suivant"
  document.querySelectorAll(".nextBtn").forEach((button) => {
    button.addEventListener("click", function () {
      const targetStep = this.getAttribute("data-target");
      // Masquer l'étape actuelle
      document
        .querySelector(".setup-content.active")
        .classList.remove("active");
      // Afficher l'étape suivante
      document.querySelector(targetStep).classList.add("active");
      // Mettre à jour le compteur d'étape
      currentStep++;
      // Mettre à jour la barre de progression
      updateProgressBar();
    });
  });

  // Gestion des boutons "Précédent"
  document.querySelectorAll(".prevBtn").forEach((button) => {
    button.addEventListener("click", function () {
      const targetStep = this.getAttribute("data-target");
      // Masquer l'étape actuelle
      document
        .querySelector(".setup-content.active")
        .classList.remove("active");
      // Afficher l'étape précédente
      document.querySelector(targetStep).classList.add("active");
      // Mettre à jour le compteur d'étape
      currentStep--;
      // Mettre à jour la barre de progression
      updateProgressBar();
    });
  });
});

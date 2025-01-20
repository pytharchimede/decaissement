// Gestion de la sélection de l'entreprise
$(".company-card").click(function () {
  var company = $(this).data("company");
  // Afficher la prochaine étape en fonction de l'entreprise sélectionnée
  $(".setup-content").removeClass("active");
  $("#step-1").addClass("active"); // Passer à l'étape 1 après sélection de l'entreprise
});

// Gestion des étapes du formulaire
$(".nextBtn").click(function () {
  var target = $(this).data("target");
  $(this).closest(".setup-content").removeClass("active");
  $(target).addClass("active");
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
      preview.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu" />';
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

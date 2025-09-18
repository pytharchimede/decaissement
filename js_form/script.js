document.addEventListener("DOMContentLoaded", function () {
  // Constantes globales
  const progressBar = document.getElementById("progress-bar");
  const steps = document.querySelectorAll(".setup-content");
  const totalSteps = steps.length;
  let currentStep = 0;

  //Log choix du mode de paiement
  document.querySelectorAll('input[name="mode_paiement"]').forEach((radio) => {
    radio.addEventListener("change", (event) => {
      console.log("Mode de paiement sélectionné :", event.target.value);
    });
  });

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

  //Chargement de la liste des chantiers en fonction de l'entreprise choisie
  document.querySelectorAll(".company-card").forEach((card) => {
    card.addEventListener("click", function () {
      // Récupérer l'identifiant ou le nom de l'entreprise
      const entreprise = this.getAttribute("data-company");

      // Afficher le loader ou indiquer le chargement (optionnel)
      const chantierSelect = document.getElementById("chantier-select");
      // chantierSelect.style.display = "block";
      const chantierDropdown = document.getElementById("chantier");
      chantierDropdown.innerHTML = '<option value="">Chargement...</option>';

      console.log("entreprise choisie " + entreprise);

      // Envoyer la requête à charge_chantier.php
      fetch("request/charge_chantier.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `entreprise=${encodeURIComponent(entreprise)}`,
      })
        .then((response) => response.json())
        .then((data) => {
          // Réinitialiser les options du dropdown
          chantierDropdown.innerHTML =
            '<option value="">--Choisir Chantier--</option>';

          // Ajouter les chantiers retournés
          if (data.length > 0) {
            data.forEach((chantier) => {
              const option = document.createElement("option");
              option.value = chantier.id_chantier;
              option.textContent = chantier.num_chantier;
              chantierDropdown.appendChild(option);
            });
          } else {
            // Message si aucun chantier trouvé
            const option = document.createElement("option");
            option.value = "";
            option.textContent =
              "Aucun chantier trouvé pour l'entreprise " + entreprise;
            chantierDropdown.appendChild(option);
          }
        })
        .catch((error) => {
          console.error("Erreur lors du chargement des chantiers :", error);
          chantierDropdown.innerHTML =
            '<option value="">Erreur lors du chargement</option>';
        });
    });
  });

  document.getElementById("motif-input").style.display = "none";
  document.getElementById("motif_input").setAttribute("disabled", "disabled");
  document.getElementById("motif-select").style.display = "none";
  document.getElementById("motif_select").setAttribute("disabled", "disabled");
  document.getElementById("chantier-select").style.display = "none";

  // Gestion du champ d'affectation
  document
    .getElementById("affectation")
    .addEventListener("change", function () {
      const affectation = this.value;

      document.getElementById("chantier-select").style.display =
        affectation === "1" ? "block" : "none";
      document.getElementById("bureau-select").style.display =
        affectation === "19" ? "block" : "none";

      // Gestion du champ motif
      if (affectation === "1") {
        console.log("Affectation  = chantier");
        document
          .getElementById("motif_select")
          .setAttribute("disabled", "disabled");

        document.getElementById("service").required = false; // Rendre service non requis

        // Affectation chantier : affiche le select pour le motif
        document.getElementById("motif-select").style.display = "block";
        document.getElementById("motif-input").style.display = "none";
        document.getElementById("motif-input").value = ""; // Réinitialise l'input
        document.getElementById("chantier-select").style.display = "block";
      } else if (affectation === "19") {
        console.log("Affectation  = Bureau");
        document
          .getElementById("motif_input")
          .setAttribute("disabled", "false");

        document.getElementById("chantier").required = false; // Rendre service non requis

        // Affectation bureau : affiche l'input pour le motif
        document.getElementById("motif-select").style.display = "none";
        document.getElementById("motif-input").style.display = "block";
        document.getElementById("motif-select").value = ""; // Réinitialise le select
        document.getElementById("chantier-select").style.display = "none";
      } else {
        document.getElementById("motif-select").style.display = "none";
        document.getElementById("motif-input").style.display = "none";
        document.getElementById("chantier-select").style.display = "none";
      }
    });

  //Lors de la sélection d'un chantier
  document.getElementById("service").addEventListener("change", function () {
    const service = this.value;
    if (service != "") {
      document.getElementById("motif_input").removeAttribute("disabled");
    } else {
      document
        .getElementById("motif_input")
        .setAttribute("disabled", "disabled");
    }
  });

  // Lors de la sélection d'un chantier
  document.getElementById("chantier").addEventListener("change", function () {
    const chantier = this.value;

    // Détection par code chantier (texte de l'option), car la valeur est l'ID
    const optText = (this.options[this.selectedIndex]?.text || "").trim();
    console.log("[chantier change] value=", chantier, " text=", optText);
    const normalized = optText.toUpperCase().replace(/\s+/g, "");
    const shouldRedirect =
      normalized.startsWith("CH058") || normalized.startsWith("CH059");
    if (optText && shouldRedirect) {
      console.log(
        "Redirection vers formulaire projet pour le chantier:",
        optText
      );
      window.location.href =
        "https://projet.fidest.ci/decaissement/formulaire_demande_decaissement.php";
      return; // Stoppe le reste du handler
    }

    if (chantier !== "") {
      // Activer le select "motif_select"
      document.getElementById("motif_select").removeAttribute("disabled");

      // Envoyer la valeur du chantier à charge_designation
      fetch("request/charge_designation.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "chantier=" + encodeURIComponent(chantier),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Erreur réseau ou réponse invalide");
          }
          return response.json(); // Supposons que le fichier retourne un JSON
        })
        .then((data) => {
          if (data.status === "succes" && Array.isArray(data.message)) {
            const motifSelect = document.getElementById("motif_select");

            // Vider les options existantes
            motifSelect.innerHTML = "";

            // Ajouter une option par défaut
            const defaultOption = document.createElement("option");
            defaultOption.text = "--Choisir un motif--";
            defaultOption.value = "";
            motifSelect.appendChild(defaultOption);

            // Remplir le select avec les désignations
            data.message.forEach((designation) => {
              const option = document.createElement("option");
              option.value = designation.lib_designation; // ID unique
              option.text = designation.lib_designation; // Libellé à afficher
              motifSelect.appendChild(option);
            });
          } else {
            console.error("Données inattendues :", data);
          }
        })
        .catch((error) => {
          console.error("Erreur lors du chargement des désignations :", error);
        });
    } else {
      // Désactiver et vider le select si aucun chantier sélectionné
      const motifSelect = document.getElementById("motif_select");
      motifSelect.innerHTML = '<option value="">--Choisir un motif--</option>';
      motifSelect.setAttribute("disabled", "disabled");
    }
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

  //Gestion de la selection d'entreprise
  document.querySelectorAll(".company-card").forEach((card) => {
    card.addEventListener("click", (event) => {
      const companyName = event.currentTarget.dataset.company;
      // console.log("Selection de la compagnie " + companyName);

      // Appel AJAX
      $.ajax({
        url: "request/define_company.php", // URL du script serveur
        type: "POST", // Méthode HTTP
        data: "companyName=" + companyName, // Données à envoyer
        success: function (response) {
          // Parse la réponse si ce n'est pas déjà un objet JSON
          if (typeof response === "string") {
            response = JSON.parse(response);
          }
          // Vérifie le statut de la réponse
          if (response.status === "success") {
            console.log(response.message); // Affiche un message de succès
          } else {
            console.log(
              response.message || "Une erreur est survenue. Veuillez réessayer."
            );
          }
        },
        error: function (xhr, status, error) {
          // Gestion des erreurs
          console.error("Erreur:", error);
          console.log("Erreur lors de l'envoi de la requete.");
        },
      });
    });
  });

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
          // Parse la réponse si ce n'est pas déjà un objet JSON
          if (typeof response === "string") {
            response = JSON.parse(response);
          }

          // Vérifie le statut de la réponse
          if (response.status === "success") {
            console.log(response.message); // Affiche un message de succès
            console.log("Demande soumise avec succès !");
            // Réinitialise le formulaire après succès
            document.getElementById("form_fiche").reset();
            $("#photo-preview").html(""); // Efface l'aperçu de la photo
            $("#cni-preview").html(""); // Efface l'aperçu de la CNI
            // Rediriger vers un autre formulaire après succès
            window.location.href = "../signer_fiche/index.php";
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

  //Script recap
  // Fonction pour mettre à jour le récapitulatif à chaque changement dans le formulaire
  const updateRecap = () => {
    // Récupérer les valeurs des champs du formulaire
    const nomPrenom = document.getElementById("nom_prenom").value;
    const telephone = document.getElementById("telephone").value;
    const affectation = document.getElementById("affectation").value;
    const motif =
      document.getElementById("motif_select").value ||
      document.getElementById("motif_input").value;
    const details = document.getElementById("details_demande").value;
    const montant = document.getElementById("montant").value;

    // Récupérer la valeur du mode de paiement sélectionné
    const modePaiement =
      document.querySelector('input[name="mode_paiement"]:checked')?.value ||
      "Non sélectionné";

    // Mettre à jour le récapitulatif avec les données du formulaire
    document.getElementById("recap-nom-prenom").textContent =
      nomPrenom || "Non renseigné";

    //Formatage du numéro de téléphone
    const formatTelephone = (telephone) => {
      if (!telephone) return "Non renseigné";

      // Supprimer tous les caractères non numériques
      const cleanNumber = telephone.replace(/\D/g, "");

      // Vérifier la longueur et formater le numéro
      if (cleanNumber.length === 10) {
        return cleanNumber.replace(
          /(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/,
          "$1 $2 $3 $4 $5"
        );
      } else if (cleanNumber.length === 8) {
        // Format alternatif (par exemple, pour un numéro sans indicatif)
        return cleanNumber.replace(
          /(\d{2})(\d{2})(\d{2})(\d{2})/,
          "$1 $2 $3 $4"
        );
      }

      // Si le numéro est invalide, renvoyer "Non renseigné"
      return "Non renseigné";
    };

    // Utilisation dans le script
    document.getElementById("recap-telephone").textContent =
      formatTelephone(telephone);

    //Pour l'affectation, préciser son libellé
    let affectationText;

    switch (parseInt(affectation, 10)) {
      case 1:
        affectationText = "Chantier";
        break;
      case 19:
        affectationText = "Bureau";
        break;
      default:
        affectationText = "Non renseigné"; // Valeur par défaut si aucune correspondance
    }

    document.getElementById("recap-affectation").textContent = affectationText;
    //Fin affectation
    document.getElementById("recap-motif").textContent =
      motif || "Non renseigné";
    document.getElementById("recap-details").textContent =
      details || "Non renseigné";
    document.getElementById("recap-montant").textContent = montant
      ? `${parseInt(montant, 10)
          .toLocaleString("fr-FR")
          .replace(/ /g, "\u00A0")} FCFA`
      : "Non renseigné";

    document.getElementById("recap-mode").textContent =
      modePaiement || "Non renseigné";

    // Mettre à jour les images (si des fichiers sont chargés)
    const photoFile = document.getElementById("photo_demandeur").files[0];
    const cniFile = document.getElementById("cni_demandeur").files[0];

    if (photoFile) {
      document.getElementById("photo-identite").src =
        URL.createObjectURL(photoFile);
    } else {
      document.getElementById("photo-identite").src =
        "https://assets.codeur.com/uli89xy5439jz7s5ud67n92qi9g8"; // Image par défaut si pas de photo
    }

    if (cniFile) {
      document.getElementById("cni-image").src = URL.createObjectURL(cniFile);
    } else {
      document.getElementById("cni-image").src =
        "https://app.fidest.ci/logi/img/logo_connex.png"; // Image par défaut si pas de CNI
    }
  };

  // Ajouter des écouteurs d'événements pour mettre à jour le récapitulatif à chaque modification
  document.getElementById("nom_prenom").addEventListener("input", updateRecap);
  document.getElementById("telephone").addEventListener("input", updateRecap);
  document
    .getElementById("affectation")
    .addEventListener("change", updateRecap);
  document
    .getElementById("motif_select")
    .addEventListener("change", updateRecap);
  document.getElementById("motif_input").addEventListener("input", updateRecap);
  document
    .getElementById("details_demande")
    .addEventListener("input", updateRecap);
  document.getElementById("montant").addEventListener("input", updateRecap);
  document.querySelectorAll('input[name="mode_paiement"]').forEach((radio) => {
    radio.addEventListener("change", updateRecap);
  });

  // Ajouter un événement pour les fichiers photo et CNI
  document
    .getElementById("photo_demandeur")
    .addEventListener("change", updateRecap);
  document
    .getElementById("cni_demandeur")
    .addEventListener("change", updateRecap);

  // Initialiser le récapitulatif dès que la page est chargée
  updateRecap();
});

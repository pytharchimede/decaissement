<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire Fiche</title>
    <!-- Lien vers Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Montserrat, Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .control-label {
            font-weight: bold;
        }

        .form-control {
            font-size: 16px;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #fabd02;
            box-shadow: 0 0 5px rgba(250, 189, 2, 0.6);
        }

        .btn-primary {
            background-color: #1d2b57;
            border: none;
            font-size: 18px;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #fabd02;
            color: #1d2b57;
            transform: scale(1.05);
        }

        .setup-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .setup-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .company-card {
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #fff;
            width: 100%;
            max-width: 300px;
            height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .company-card:hover {
            border-color: #1d2b57;
            background-color: #eaf4ff;
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.2);
        }

        .company-logo {
            max-width: 120px;
            max-height: 120px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1d2b57;
            transition: all 0.3s ease;
        }

        .company-card:hover .company-name {
            color: #fabd02;
        }

        .selection-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .selection-container {
                flex-direction: column;
                align-items: center;
            }

            .company-card {
                max-width: 90%;
                height: auto;
            }
        }

        h1,
        h2 {
            color: #1d2b57;
            font-weight: bold;
        }

        .btn-primary {
            background-color: #1d2b57;
            color: white;
        }

        .btn-primary:hover {
            background-color: #fabd02;
            color: #1d2b57;
            transform: scale(1.05);
        }

        .file-drop-area {
            border: 2px dashed #1d2b57;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            background-color: #f9f9f9;
            transition: all 0.3s ease;
        }

        .file-drop-area:hover {
            background-color: #eaf4ff;
            border-color: #fabd02;
        }

        .file-preview {
            margin-top: 15px;
        }

        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
        }

        .file-preview p {
            color: #1d2b57;
        }

        .setup-content {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .setup-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        h1,
        h2 {
            color: #1d2b57;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Formulaire Fiche</h1>
        <form id="form_fiche">
            <!-- Étape 0 : Sélection de l'entreprise -->
            <div class="setup-content active" id="step-0">
                <h2 class="text-center mb-4">Sélectionnez une entreprise</h2>
                <div class="selection-container">
                    <div class="company-card" data-target="#step-1" data-company="FIDEST">
                        <img src="https://app.fidest.ci/logi/img/logo_connex.png" alt="Logo FIDEST" class="company-logo">
                        <div class="company-name">FIDEST</div>
                    </div>
                    <div class="company-card" data-target="#step-1" data-company="BANAMUR">
                        <img src="https://assets.codeur.com/uli89xy5439jz7s5ud67n92qi9g8" alt="Logo BANAMUR" class="company-logo">
                        <div class="company-name">BANAMUR</div>
                    </div>
                </div>
            </div>

            <!-- Étape 1 : Informations personnelles -->
            <div class="setup-content" id="step-1">
                <h2 class="text-center mb-4">Informations personnelles</h2>
                <div class="mb-3">
                    <label for="nom_prenom" class="form-label control-label">Nom et Prénom(s)</label>
                    <input type="text" class="form-control" id="nom_prenom" name="nom_prenom" placeholder="Nom et Prénom(s)" required>
                </div>
                <div class="mb-3">
                    <label for="telephone" class="form-label control-label">Numéro de téléphone</label>
                    <input type="text" class="form-control" id="telephone" name="telephone" placeholder="Numéro de téléphone" required>
                </div>
                <button type="button" class="btn btn-primary nextBtn" data-target="#step-2">Suivant</button>
            </div>

            <!-- Étape 2 : A propos de la demande -->
            <div class="setup-content" id="step-2">
                <h2 class="text-center mb-4">A propos de la demande</h2>
                <div class="mb-3">
                    <label for="affectation" class="form-label control-label">Affectation</label>
                    <select class="form-control" id="affectation" name="affectation" required>
                        <option value="">--Choisir Affectation--</option>
                        <option value="chantier">Chantier</option>
                        <option value="bureau">Bureau</option>
                        <option value="ressources_humaines">Ressources Humaines</option>
                    </select>
                </div>
                <div class="mb-3" id="chantier-select" style="display:none;">
                    <label for="chantier" class="form-label control-label">Code Chantier</label>
                    <input type="text" class="form-control" id="chantier" name="chantier" placeholder="Code Chantier" />
                </div>
                <div class="mb-3" id="bureau-select" style="display:none;">
                    <label for="service" class="form-label control-label">Service Concerné</label>
                    <input type="text" class="form-control" id="service" name="service" placeholder="Service Concerné" />
                </div>
                <div class="mb-3">
                    <label for="motif_demande" class="form-label control-label">Motif de la demande</label>
                    <input type="text" class="form-control" id="motif_demande" name="motif_demande" placeholder="Motif" required>
                </div>
                <div class="mb-3">
                    <label for="details_demande" class="form-label control-label">Détails sur la demande</label>
                    <textarea class="form-control" id="details_demande" name="details_demande" rows="3" placeholder="Détails supplémentaires" required></textarea>
                </div>
                <button type="button" class="btn btn-primary nextBtn" data-target="#step-3">Suivant</button>
            </div>

            <!-- Étape 3 : Montant et Mode de paiement -->
            <div class="setup-content" id="step-3">
                <h2 class="text-center mb-4">Montant et Mode de paiement</h2>
                <div class="mb-3">
                    <label for="montant" class="form-label control-label">Montant</label>
                    <input type="number" class="form-control" id="montant" name="montant" placeholder="Montant" required>
                </div>
                <div class="mb-3">
                    <label for="mode_paiement" class="control-label">Mode de paiement</label>
                    <select id="mode_paiement" name="mode_paiement" class="form-control" required>
                        <option value="">--Choisir mode de paiement--</option>
                        <option value="Orange Money">Orange Money</option>
                        <option value="MTN Money">MTN Money</option>
                        <option value="Moov Money">Moov Money</option>
                        <option value="Wave">Wave</option>
                        <option value="Cash">Cash</option>
                        <option value="Djamo">Djamo</option>
                        <option value="Chèque">Chèque</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary nextBtn" data-target="#step-4">Suivant</button>
            </div>

            <!-- Étape 4 : Photo et CNI -->
            <div class="setup-content" id="step-4">
                <h2 class="text-center mb-4">Photo et CNI</h2>
                <div class="mb-3">
                    <label for="photo_demandeur" class="form-label control-label">Photo du demandeur</label>
                    <div class="file-drop-area" id="photo-drop-area">
                        <input type="file" id="photo_demandeur" name="photo_demandeur" accept="image/*" style="display:none;">
                        <p>Faites glisser ou sélectionnez une photo</p>
                        <p>Formats autorisés : jpg, png, jpeg</p>
                    </div>
                    <div class="file-preview" id="photo-preview"></div>
                </div>
                <div class="mb-3">
                    <label for="cni_demandeur" class="form-label control-label">CNI du demandeur</label>
                    <div class="file-drop-area" id="cni-drop-area">
                        <input type="file" id="cni_demandeur" name="cni_demandeur" accept="image/*" style="display:none;">
                        <p>Faites glisser ou sélectionnez une image de votre CNI</p>
                        <p>Formats autorisés : jpg, png, jpeg</p>
                    </div>
                    <div class="file-preview" id="cni-preview"></div>
                </div>

                <!-- Étape 5 : Signature -->
                <div class="setup-content" id="step-5">
                    <h2 class="text-center mb-4">Signature</h2>
                    <div class="mb-3">
                        <canvas id="signature-canvas" width="400" height="200" style="border:1px solid #000;"></canvas>
                        <button type="button" class="btn btn-danger" onclick="clearSignature()">Effacer</button>
                    </div>
                    <button type="submit" class="btn btn-primary">Envoyer</button>
                </div>
        </form>
    </div>

    <!-- Scripts Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Gestion de la sélection de l'entreprise
        $(".company-card").click(function() {
            var company = $(this).data("company");
            // Afficher la prochaine étape en fonction de l'entreprise sélectionnée
            $(".setup-content").removeClass("active");
            $("#step-1").addClass("active"); // Passer à l'étape 1 après sélection de l'entreprise
        });

        // Gestion des étapes du formulaire
        $(".nextBtn").click(function() {
            var target = $(this).data("target");
            $(this).closest(".setup-content").removeClass("active");
            $(target).addClass("active");
        });

        // Script pour afficher le champ en fonction de l'affectation
        $("#affectation").change(function() {
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
            if (file && file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById(previewId);
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu" />';
                };
                reader.readAsDataURL(file);
            } else {
                alert("Veuillez télécharger une image valide.");
            }
        }

        // Gestion du drag and drop pour Photo
        var photoDropArea = document.getElementById('photo-drop-area');
        photoDropArea.addEventListener('click', function() {
            document.getElementById('photo_demandeur').click();
        });

        photoDropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            photoDropArea.style.backgroundColor = '#eaf4ff';
        });

        photoDropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            photoDropArea.style.backgroundColor = '#f9f9f9';
        });

        photoDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            photoDropArea.style.backgroundColor = '#f9f9f9';
            var fileInput = document.getElementById('photo_demandeur');
            fileInput.files = e.dataTransfer.files;
            displayPreview(fileInput, 'photo-preview');
        });

        // Gestion du drag and drop pour CNI
        var cniDropArea = document.getElementById('cni-drop-area');
        cniDropArea.addEventListener('click', function() {
            document.getElementById('cni_demandeur').click();
        });

        cniDropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            cniDropArea.style.backgroundColor = '#eaf4ff';
        });

        cniDropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            cniDropArea.style.backgroundColor = '#f9f9f9';
        });

        cniDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            cniDropArea.style.backgroundColor = '#f9f9f9';
            var fileInput = document.getElementById('cni_demandeur');
            fileInput.files = e.dataTransfer.files;
            displayPreview(fileInput, 'cni-preview');
        });

        // Fonction de prévisualisation pour les fichiers d'images
        document.getElementById('photo_demandeur').addEventListener('change', function() {
            displayPreview(this, 'photo-preview');
        });

        document.getElementById('cni_demandeur').addEventListener('change', function() {
            displayPreview(this, 'cni-preview');
        });

        // Effacer la signature
        function clearSignature() {
            var canvas = document.getElementById("signature-canvas");
            var context = canvas.getContext("2d");
            context.clearRect(0, 0, canvas.width, canvas.height);
        }
    </script>
</body>

</html>
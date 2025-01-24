<?php
include 'headers/header_formulaire_demande_decaissement.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulaire Fiche</title>
    <!-- Lien vers Bootstrap CSS -->
    <link href="plugins/css/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link rel="stylesheet" href="plugins/css/fontawesome/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="css_form/style.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Formulaire de décaissement</h1>

        <div class="progress mb-4">
            <div class="progress-bar" role="progressbar" style="width: 20%;" id="progress-bar">Étape 1 sur 5</div>
        </div>

        <form id="form_fiche" enctype="multipart/form-data" method="post">
            <!-- Étape 0 : Sélection de l'entreprise -->
            <div class="setup-content active" id="step-0">

                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fa fa-building fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">Choisissez une entreprise</h2>
                    <p class="enterprise-description text-muted mt-2">
                        Sélectionnez une entreprise dans la liste pour continuer.
                    </p>
                </div>

                <div class="selection-container">
                    <!-- Première carte : FIDEST -->
                    <div class="nextBtn company-card" data-target="#step-1" data-company="FIDEST">
                        <div class="company-card-inner">
                            <img src="https://app.fidest.ci/logi/img/logo_connex.png" alt="Logo FIDEST" class="company-logo">
                            <div class="company-name">FIDEST</div>
                        </div>
                    </div>

                    <!-- Deuxième carte : BANAMUR -->
                    <div class="nextBtn company-card" data-target="#step-1" data-company="BANAMUR">
                        <div class="company-card-inner">
                            <img src="https://assets.codeur.com/uli89xy5439jz7s5ud67n92qi9g8" alt="Logo BANAMUR" class="company-logo">
                            <div class="company-name">BANAMUR</div>
                        </div>
                    </div>

                    <!-- Troisième carte : BUREAU FIDEST BANAMUR -->
                    <div class="nextBtn company-card" data-target="#step-1" data-company="BUREAU FIDEST BANAMUR">
                        <div class="company-card-inner">
                            <div class="logos-combined">
                                <img src="https://app.fidest.ci/logi/img/logo_connex.png" alt="Logo FIDEST" class="company-logo small-logo">
                                <img src="https://assets.codeur.com/uli89xy5439jz7s5ud67n92qi9g8" alt="Logo BANAMUR" class="company-logo small-logo">
                            </div>
                            <div class="company-name">BUREAU FIDEST BANAMUR</div>
                        </div>
                    </div>
                </div>


            </div>

            <!-- Étape 1 : Informations personnelles -->
            <div class="setup-content" id="step-1">

                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fas fa-user fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">Informations Personnelles</h2>
                    <p class="enterprise-description text-muted mt-2">
                        Fournissez vos coordonnées complètes.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="nom_prenom" class="form-label control-label">Nom et Prénom(s)</label>
                    <input type="text" class="form-control" id="nom_prenom" name="nom_prenom" placeholder="Nom et Prénom(s)" oninput="validateName()" onchange="validateName()" title="Veuillez saisir le nom." required>
                    <div id="name-error-message" style="color: red; display: none; margin : 4px;">Veuillez saisir le nom.</div>
                    <div id="name-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Parfaitement renseigné !</div>
                </div>

                <div class="mb-3">
                    <label for="telephone" class="form-label control-label">Numéro de téléphone</label>
                    <input type="text" class="form-control" id="telephone" name="telephone" placeholder="Numéro de téléphone" required oninput="validatePhoneNumber()" pattern="^\d{10}$" title="Le numéro doit contenir exactement 10 chiffres">
                    <div id="tel-error-message" style="color: red; display: none; margin : 4px;">Veuillez entrer un numéro de téléphone valide (exactement 10 chiffres).</div>
                    <div id="tel-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Super ! Continuons.</div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-primary prevBtn" data-target="#step-0">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button id="first_next_button" type="button" class="btn btn-primary nextBtn" data-target="#step-2">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Étape 2 : A propos de la demande -->
            <div class="setup-content" id="step-2">

                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fas fa-question-circle fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">A Propos de la demande</h2>
                    <p class="enterprise-description text-muted mt-2">
                        Décrivez brièvement votre demande.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="affectation" class="form-label control-label">Affectation</label>
                    <select class="form-control" id="affectation" name="affectation" required>
                        <option value="">--Choisir Affectation--</option>
                        <?php foreach ($listeAffectation as $affectation) { ?>
                            <option value="<?php echo $affectation['id_affectation'] ?>"><?php echo $affectation['lib_affectation'] ?></option>
                        <?php } ?>
                    </select>
                    <div id="affectation-error-message" style="color: red; display: none; margin : 4px;">Veuillez sélectionner au moins une affectation.</div>
                    <div id="affectation-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Affectation validée !</div>
                </div>
                <div class="mb-3" id="chantier-select" style="display:none;">
                    <label for="chantier" class="form-label control-label">Code Chantier</label>
                    <select class="form-control" id="chantier" name="chantier" required>
                        <option value="">--Choisir Chantier--</option>
                    </select>
                </div>
                <div class="mb-3" id="bureau-select" style="display:none;">
                    <label for="service" class="form-label control-label">Service Concerné</label>
                    <select class="form-control" id="service" name="service" required>
                        <option value="">--Choisir Service--</option>
                        <?php foreach ($listeService as $service) { ?>
                            <option value="<?php echo $service['id_serv_bureau_banamur'] ?>"><?php echo $service['lib_serv_bureau_banamur'] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3" id="motif-select">
                    <label for="motif_select" class="form-label control-label">Motif de la demande</label>
                    <select class="form-control" id="motif_select" name="motif_select">
                    </select>
                </div>
                <div class="mb-3" id="motif-input">
                    <label for="motif_input" class="form-label control-label">Motif de la demande</label>
                    <input type="text" class="form-control" id="motif_input" name="motif_input" placeholder="Motif">
                </div>
                <div class="mb-3">
                    <label for="details_demande" class="form-label control-label">Détails sur la demande</label>
                    <textarea class="form-control" id="details_demande" name="details_demande" rows="3" placeholder="Détails supplémentaires" required></textarea>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-primary prevBtn" data-target="#step-1">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button id="first_next_button_2" type="button" class="btn btn-primary nextBtn" data-target="#step-3">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Étape 3 : Montant et Mode de paiement -->
            <div class="setup-content" id="step-3">

                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fas fa-dollar-sign fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">Données de décaissement</h2>
                    <p class="enterprise-description text-muted mt-2">
                        Indiquez les détails financiers.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="montant" class="form-label control-label">Montant</label>
                    <input type="number" class="form-control" id="montant" name="montant" placeholder="Montant" oninput="validateMontant()" pattern="^[1-9]\d*$" title="Veuillez saisir un montant en FCFA." required>
                    <div id="montant-error-message" style="color: red; display: none; margin : 4px;">Veuillez saisir un montant en FCFA.</div>
                    <div id="montant-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Excellent, c'est un montant valide !</div>
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
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-primary prevBtn" data-target="#step-2">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button id="first_next_button_3" type="button" class="btn btn-primary nextBtn" data-target="#step-4">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Étape 4 : Photo et CNI -->
            <div class="setup-content" id="step-4">

                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fas fa-id-badge fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">Photo et Pièce d'identité</h2>
                    <p class="enterprise-description text-muted mt-2">Justifiez vos données personnelles</p>
                </div>

                <div class="row">
                    <!-- Photo du demandeur -->
                    <div class="col-md-6 mb-3">
                        <label for="photo_demandeur" class="form-label control-label">Photo du demandeur</label>
                        <div class="file-drop-area" id="photo-drop-area">
                            <input type="file" id="photo_demandeur" name="photo_demandeur" accept="image/*" style="display:none;">
                            <div class="file-preview" id="photo-preview"></div>
                            <p>Faites glisser ou sélectionnez une photo</p>
                            <p>Formats autorisés : jpg, png, jpeg</p>
                        </div>
                        <div id="photo_demandeur-error-message" style="color: red; display: none; margin : 4px;">Veuillez uploader votre photo.</div>
                        <div id="photo_demandeur-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Photo téléchargée avec succès !</div>
                    </div>

                    <!-- CNI du demandeur -->
                    <div class="col-md-6 mb-3">
                        <label for="cni_demandeur" class="form-label control-label">CNI du demandeur</label>
                        <div class="file-drop-area" id="cni-drop-area">
                            <input type="file" id="cni_demandeur" name="cni_demandeur" accept="image/*" style="display:none;">
                            <div class="file-preview" id="cni-preview"></div>
                            <p>Faites glisser ou sélectionnez une image de votre CNI</p>
                            <p>Formats autorisés : jpg, png, jpeg</p>
                        </div>
                        <div id="cni_demandeur-error-message" style="color: red; display: none; margin : 4px;">Veuillez uploader votre CNI.</div>
                        <div id="cni_demandeur-succes-message" style="color: green; display: none; margin : 4px;"><i class="fa fa-check-circle"></i> Parfait !</div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-primary prevBtn" data-target="#step-3">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button id="first_next_button_4" type="button" class="btn btn-primary nextBtn" data-target="#step-5">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

            </div>


            <!-- Étape 6 : Recapitulatif de la demande -->
            <div class="setup-content" id="step-5">
                <div class="enterprise-selection text-center my-5">
                    <div class="icon-container mb-3">
                        <i class="fas fa-file fa-3x"></i>
                    </div>
                    <h2 class="enterprise-title text-dark fw-bold">Récapitulatif de la demande</h2>
                    <p class="enterprise-description text-muted mt-2">
                        Vérifiez les informations avant soumission.
                    </p>
                </div>

                <div id="recapitulatif-container" class="recapitulatif">
                    <div class="recap-header d-flex align-items-center justify-content-between">
                        <img id="company-logo" src="https://app.fidest.ci/logi/img/logo_connex.png" alt="Logo de l'entreprise" class="company-logo">
                        <h3 class="fw-bold">RÉCAPITULATIF DE LA DEMANDE</h3>
                    </div>

                    <div class="recap-body">
                        <div class="photo-container">
                            <img id="photo-identite" src="https://assets.codeur.com/uli89xy5439jz7s5ud67n92qi9g8" alt="Photo d'identité" class="photo-identite">
                        </div>
                        <div class="info-container">
                            <h4>Informations personnelles</h4>
                            <p><strong>Nom et Prénom :</strong> <span id="recap-nom-prenom">John Doe</span></p>
                            <p><strong>Téléphone :</strong> <span id="recap-telephone">+225 01 23 45 67 89</span></p>
                            <p><strong>Affectation :</strong> <span id="recap-affectation">Chantier</span></p>
                            <p><strong>Motif de la demande :</strong> <span id="recap-motif">Avance sur salaire</span></p>
                            <p><strong>Détails de la demande :</strong> <span id="recap-details">Achat urgent</span></p>
                            <p><strong>Montant :</strong> <span id="recap-montant">100 000 FCFA</span></p>
                            <p><strong>Mode de paiement :</strong> <span id="recap-mode">Orange Money</span></p>
                        </div>

                        <div class="cni-container">
                            <h4>Pièce d'identité</h4>
                            <img id="cni-image" src="https://app.fidest.ci/logi/img/logo_connex.png" alt="Image de la CNI" class="cni-image">
                        </div>
                    </div>

                    <!-- <div class="recap-footer text-center mt-4">
                        <h5>Signatures</h5>
                        <div class="signatures d-flex justify-content-around mt-3">
                            <div>
                                <p class="text-muted">Demandeur</p>
                                <div class="signature"></div>
                            </div>
                            <div>
                                <p class="text-muted">Validateur</p>
                                <div class="signature"></div>
                            </div>
                            <div>
                                <p class="text-muted">Responsable</p>
                                <div class="signature"></div>
                            </div>
                        </div>
                    </div> -->
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-primary prevBtn" data-target="#step-4">
                        <i class="fas fa-arrow-left"></i> Précédent
                    </button>
                    <button type="submit" id="submit-button" class="btn btn-success">
                        Soumettre <i class="fas fa-check"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Scripts Bootstrap -->
    <script src="plugins/js/popper/popper.min.js"></script>
    <script src="plugins/js/bootstrap/bootstrap.min.js"></script>
    <script src="plugins/js/jquery/jquery-3.6.0.min.js"></script>
    <script src="plugins/js/fontawesome/all.min.js"></script>
    <script src="js_form/form_control.js"></script>
    <script src="js_form/script.js"></script>
</body>

</html>
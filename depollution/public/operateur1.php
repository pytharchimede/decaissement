<?php
session_start();
// Pour l'instant on laisse accessible même sans session stricte; on pourrait forcer un rôle.
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Opérateur 1 – Création Voyage</title>
    <link rel="stylesheet" href="assets/css/app.css" />
    <style>
        .steps {
            display: flex;
            gap: .5rem;
            margin-bottom: 1rem;
            overflow-x: auto
        }

        .step {
            padding: .5rem .75rem;
            border-radius: 20px;
            background: #eee;
            font-size: .8rem;
            white-space: nowrap
        }

        .step.active {
            background: #2563eb;
            color: #fff;
            font-weight: 600
        }

        form fieldset {
            border: none;
            padding: 0;
            margin: 0;
            display: none
        }

        form fieldset.active {
            display: block;
            animation: fade .25s
        }

        @keyframes fade {
            from {
                opacity: 0;
                transform: translateY(4px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .grid {
            display: grid;
            gap: .75rem
        }

        .two {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr))
        }

        .preview-bon {
            margin-top: .5rem;
            max-height: 120px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px
        }

        .recent-list {
            max-height: 260px;
            overflow: auto;
            font-size: .75rem
        }

        .card-small {
            padding: .5rem .6rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: .5rem;
            background: #fff
        }

        .badge {
            background: #2563eb;
            color: #fff;
            font-size: .65rem;
            padding: 2px 6px;
            border-radius: 4px
        }
    </style>
</head>

<body class="layout">
    <header class="topbar">
        <h1>Nouvel voyage</h1>
        <nav><a href="operateur2.php">Opérateur 2</a> <a href="admin.php">Admin</a></nav>
    </header>
    <main class="main">
        <div class="steps" id="steps"></div>
        <form id="voyageForm" class="panel">
            <input type="hidden" name="action" value="createVoyageOp1" />
            <!-- Etape 1 prestataire/date -->
            <fieldset data-step="1" class="active">
                <div class="grid">
                    <label>Date du voyage
                        <input type="date" name="date_voyage" required value="<?php echo date('Y-m-d'); ?>" />
                    </label>
                    <label>Prestataire
                        <select name="prestataire_nom" id="prestataireSelect" required></select>
                    </label>
                    <div id="montantOrigineBox" class="info-line"></div>
                </div>
                <button type="button" class="btn next">Suivant</button>
            </fieldset>
            <!-- Etape 2 Chauffeur -->
            <fieldset data-step="2">
                <div class="grid two">
                    <label>Nom Chauffeur
                        <input type="text" name="chauffeur_nom" required autocomplete="off" />
                    </label>
                    <label>Téléphone Chauffeur
                        <input type="tel" name="chauffeur_tel" placeholder="07.." />
                    </label>
                </div>
                <div class="nav-steps"><button type="button" class="btn light prev">Retour</button><button type="button" class="btn next">Suivant</button></div>
            </fieldset>
            <!-- Etape 3 Camion -->
            <fieldset data-step="3">
                <div class="grid two">
                    <label>Matricule Camion
                        <input type="text" name="camion_matricule" required placeholder="Ex: 21133WWCI01" />
                    </label>
                    <label>Bon de sortie N°
                        <input type="text" name="bon_numero" required />
                    </label>
                    <label>Fichier Bon (photo / pdf)
                        <input type="file" name="bon_fichier" accept="image/*,application/pdf" />
                    </label>
                    <div id="bonPreviewWrap"></div>
                </div>
                <div class="nav-steps"><button type="button" class="btn light prev">Retour</button><button type="button" class="btn next">Suivant</button></div>
            </fieldset>
            <!-- Etape 4 Résumé -->
            <fieldset data-step="4">
                <h3>Récapitulatif</h3>
                <div id="recap" class="recap"></div>
                <div class="nav-steps"><button type="button" class="btn light prev">Retour</button><button type="submit" class="btn primary">Enregistrer</button></div>
            </fieldset>
        </form>
        <section class="side">
            <h2>Derniers voyages</h2>
            <div id="recentVoyages" class="recent-list"></div>
        </section>
    </main>
    <div id="toast" class="toast" hidden></div>
    <script src="assets/js/op1.js"></script>
</body>

</html>
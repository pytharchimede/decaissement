<?php session_start(); ?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Opérateur 2 – Validation Voyages</title>
    <link rel="stylesheet" href="assets/css/app.css" />
    <style>
        .voyages-table-wrap {
            max-height: 420px;
            overflow: auto
        }

        .selectable tr {
            cursor: pointer
        }

        .selectable tr.selected {
            background: #dbeafe !important
        }
    </style>
</head>

<body class="layout">
    <header class="topbar">
        <h1>Validation Voyages</h1>
        <nav><a href="operateur1.php">Opérateur 1</a> <a href="admin.php">Admin</a></nav>
    </header>
    <main class="main op2-layout">
        <section class="card">
            <div class="flex" style="justify-content:space-between">
                <h2>Voyages en attente</h2><button class="btn light" id="refreshBtn">Rafraîchir</button>
            </div>
            <div class="voyages-table-wrap">
                <table class="table selectable" id="voyagesTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Camion</th>
                            <th>Chauffeur</th>
                            <th>Prestataire</th>
                            <th>Montant Orig.</th>
                            <th>Frais</th>
                            <th>Carb.</th>
                            <th>Réel</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section class="card" id="panelEdition">
            <h2>Détails / Clôture</h2>
            <div id="noSelection">Sélectionnez un voyage.</div>
            <form id="formOp2" hidden>
                <div class="inline-fields">
                    <label>Montant origine
                        <input type="text" name="montant_origine" readonly />
                    </label>
                    <label>Frais route (CFA)
                        <input type="number" name="frais_route" min="0" step="1" value="0" />
                    </label>
                    <label><span class="toggle"><input type="checkbox" id="useCarb" checked /> Carburant ?</span></label>
                    <label>Litres
                        <input type="number" name="carburant_litre" min="0" step="1" value="50" />
                    </label>
                    <label>Montant Carburant
                        <input type="number" name="carburant_montant" min="0" step="1" value="0" />
                    </label>
                    <label>Réel Reçu
                        <input type="text" name="reel_recu" readonly />
                    </label>
                </div>
                <div class="notice">Modification litre ajuste le montant et inversement (prix auto depuis config).</div>
                <div class="separator"></div>
                <button type="submit" class="btn primary">Clore le voyage</button>
            </form>
        </section>
    </main>
    <div id="toast" class="toast" hidden></div>
    <script src="assets/js/op2.js"></script>
</body>

</html>
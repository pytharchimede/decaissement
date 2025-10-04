<?php session_start(); ?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin Dépollution</title>
    <link rel="stylesheet" href="assets/css/app.css" />
    <style>
        .tiles {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            margin-top: 1rem
        }

        .tile {
            background: #fff;
            border-radius: 14px;
            padding: 1.1rem;
            box-shadow: 0 10px 25px -8px rgba(0, 0, 0, .12);
            display: flex;
            flex-direction: column;
            gap: .65rem;
            position: relative;
            overflow: hidden
        }

        .tile:before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 85% 15%, rgba(37, 99, 235, .15), transparent 60%);
            pointer-events: none
        }

        .tile h3 {
            margin: 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: .4rem
        }

        .tile h3 i {
            color: #2563eb
        }

        .small {
            font-size: .7rem;
            color: #555
        }

        .list-prest {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: .6rem;
            margin-top: .75rem;
            max-height: 380px;
            overflow: auto
        }

        .prest-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: .55rem .6rem;
            display: flex;
            flex-direction: column;
            gap: .35rem;
            box-shadow: 0 2px 6px -2px rgba(0, 0, 0, .05);
            position: relative;
        }

        .prest-card .name {
            font-weight: 600;
            font-size: .8rem
        }

        .prest-card .montant {
            font-size: .65rem;
            color: #444
        }

        .drop-zone {
            border: 2px dashed #2563eb;
            border-radius: 14px;
            padding: 1.2rem;
            text-align: center;
            cursor: pointer;
            transition: .25s background, .25s border-color;
            background: #f1f5f9;
            font-size: .8rem
        }

        .drop-zone.drag {
            background: #e0ecfd;
            border-color: #1d4ed8
        }

        .progress-import {
            margin-top: .6rem;
            font-size: .65rem;
            line-height: 1.15rem;
            max-height: 170px;
            overflow: auto;
            background: #0f172a;
            color: #e2e8f0;
            padding: .6rem;
            border-radius: 8px;
            font-family: monospace
        }

        .badge-ok {
            color: #10b981
        }

        .badge-err {
            color: #dc2626
        }
    </style>
</head>

<body class="layout">
    <header class="topbar">
        <h1>Administration Dépollution</h1>
        <nav><a href="operateur1.php">Opérateur 1</a><a href="operateur2.php">Opérateur 2</a></nav>
    </header>
    <main class="main">
        <section class="panel" style="flex:2">
            <h2 style="margin-top:0;display:flex;align-items:center;gap:.5rem"><span>Prestataires</span></h2>
            <form id="formAddPrest" class="flex" style="flex-wrap:wrap;gap:.5rem">
                <input type="text" name="nom" placeholder="Nom prestataire" required />
                <input type="number" name="montant_origine" placeholder="Montant origine" required />
                <button class="btn primary" type="submit">Ajouter</button>
            </form>
            <div class="list-prest" id="prestList">Chargement...</div>
        </section>
        <section class="panel" style="flex:1">
            <h2>Imports / Exports</h2>
            <div class="tiles">
                <div class="tile">
                    <h3><i class="fa fa-file-excel"></i> Import Excel</h3>
                    <p class="small">Chaque feuille = prestataire. Colonnes A:Date, B:Matricule, C:Chauffeur, D:Bon (ligne 1 = en-têtes).</p>
                    <div id="dropZone" class="drop-zone">Glissez-déposez ou cliquez pour choisir un fichier .xlsx</div>
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.65rem;margin-top:.4rem"><input type="checkbox" id="strictMode" /> Mode strict (refuser lignes sans chauffeur ou bon)</label>
                    <input type="file" id="excelInput" accept=".xlsx,.xls,.csv" hidden />
                    <div style="display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap">
                        <button class="btn light" id="btnPreview" type="button" style="flex:1;min-width:130px">Prévisualiser</button>
                        <button class="btn primary" id="btnImportDirect" type="button" style="flex:1;min-width:130px">Importer direct</button>
                    </div>
                    <div class="progress-import" id="importLog" hidden></div>
                    <div id="previewContainer" style="margin-top:.75rem;display:none">
                        <h4 style="margin:.2rem 0 .6rem;font-size:.85rem">Aperçu des feuilles <span style="font-size:.55rem;font-weight:400;color:#475569">(<span class="badge-calc" style="background:#e0f2fe;color:#0369a1;padding:0 4px;border-radius:3px">calc</span> = montant réel calculé)</span></h4>
                        <div id="previewSheets" style="display:flex;flex-direction:column;gap:1rem;max-height:400px;overflow:auto"></div>
                        <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
                            <button class="btn primary" id="btnImportValids" type="button">Importer lignes valides</button>
                            <button class="btn light" id="btnHidePreview" type="button">Fermer aperçu</button>
                        </div>
                    </div>
                </div>
                <div class="tile">
                    <h3><i class="fa fa-download"></i> Export CSV</h3>
                    <p class="small">Voyages (SAISI / CLOS). Export simple – filtre avancé à venir.</p>
                    <button id="exportCsv" class="btn light">Exporter voyages</button>
                </div>
            </div>
        </section>
    </main>
    <div id="toast" class="toast" hidden></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js" crossorigin="anonymous"></script>
    <script src="assets/js/admin_dash.js"></script>
</body>

</html>
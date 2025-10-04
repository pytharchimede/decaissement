<?php session_start(); ?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Admin Dépollution</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="shared-ui.css" />
    <link rel="stylesheet" href="assets/css/app.css" />
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
            font-family: system-ui, 'Segoe UI', sans-serif;
            transition: background .4s, color .4s;
        }

        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .app-nav {
            backdrop-filter: blur(6px);
        }

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

        /* Dark mode overrides */
        .dark .tile {
            background: #1e293b;
            color: #e2e8f0;
        }

        .dark .prest-card {
            background: #1e293b;
            border-color: #475569;
            color: #e2e8f0;
        }

        .dark .drop-zone {
            background: #243047;
            border-color: #64748b;
            color: #e2e8f0;
        }

        .dark .progress-import {
            background: #0f172a;
            color: #e2e8f0;
        }

        .dark .btn-outline {
            background: #1e293b;
            color: #facc15;
            border-color: #facc15;
        }

        .dark .btn-yellow {
            box-shadow: 0 0 0 1px #eab308 inset;
        }

        .dark .toast {
            background: #facc15;
            color: #111;
        }
    </style>
</head>

<body class="min-h-screen">
    <header class="app-nav fixed top-0 inset-x-0 z-40 bg-white/80 border-b border-yellow-300 flex items-center h-14 px-3">
        <button id="hambBtn" class="p-2 rounded hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-yellow-400" aria-label="Menu">
            <span class="block w-5 h-0.5 bg-yellow-700 mb-1"></span>
            <span class="block w-5 h-0.5 bg-yellow-700 mb-1"></span>
            <span class="block w-5 h-0.5 bg-yellow-700"></span>
        </button>
        <h1 class="ml-3 text-sm font-extrabold tracking-wide text-yellow-700">Administration</h1>
        <div class="ml-auto flex items-center gap-2">
            <a href="operateur1.php" class="btn-outline">Opér. 1</a>
            <a href="operateur2.php" class="btn-outline">Opér. 2</a>
            <a href="recap_carburant.php" class="btn-outline">Récap</a>
            <button id="toggleThemeAdmin" class="btn-outline" title="Mode sombre">🌙</button>
        </div>
    </header>
    <nav id="sideMenu" class="fixed top-14 left-0 bottom-0 w-60 bg-white border-r border-yellow-300 p-4 transform -translate-x-full transition-transform duration-300 flex flex-col gap-4 z-30">
        <div class="text-xs font-semibold tracking-wider text-yellow-700">Navigation</div>
        <a class="btn-outline text-left" href="operateur1.php">Opérateur 1</a>
        <a class="btn-outline text-left" href="operateur2.php">Opérateur 2</a>
        <a class="btn-outline text-left" href="recap_carburant.php">Récap carburant</a>
        <a class="btn-yellow text-left" href="admin.php">Administration</a>
    </nav>
    <main class="pt-16 px-3 pb-10 max-w-7xl mx-auto w-full">
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
    <script>
        // Menu hamburger & side
        const hambBtn = document.getElementById('hambBtn');
        const sideMenu = document.getElementById('sideMenu');
        hambBtn.addEventListener('click', () => sideMenu.classList.toggle('-translate-x-full'));
        // Theme toggle
        const themeBtnAdmin = document.getElementById('toggleThemeAdmin');

        function applyThemeAdmin() {
            const mode = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', mode === 'dark');
            themeBtnAdmin.textContent = mode === 'dark' ? '☀️' : '🌙';
            themeBtnAdmin.title = mode === 'dark' ? 'Mode clair' : 'Mode sombre';
        }
        themeBtnAdmin.addEventListener('click', () => {
            const cur = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', cur);
            applyThemeAdmin();
        });
        applyThemeAdmin();
    </script>
</body>

</html>
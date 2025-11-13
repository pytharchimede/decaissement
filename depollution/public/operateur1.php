<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Opérateur 1 – Voyages</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #ffffff 100%);
            font-family: system-ui, 'Segoe UI', sans-serif;
        }

        .app-nav {
            backdrop-filter: blur(6px);
        }

        .hamb-line {
            transition: transform .3s, opacity .3s;
        }

        .modal-backdrop {
            background: rgba(15, 23, 42, .55);
        }

        .card {
            background: #fff;
            border: 1px solid #facc15;
            border-radius: 14px;
            box-shadow: 0 4px 14px -2px rgba(0, 0, 0, .08);
        }

        .card-header {
            font-size: .75rem;
            font-weight: 600;
            color: #78350f;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .step-pill {
            background: #fef3c7;
            color: #92400e;
            font-size: .65rem;
            font-weight: 600;
            padding: .35rem .6rem;
            border-radius: 999px;
            white-space: nowrap;
        }

        .step-pill.active {
            background: #facc15;
            color: #111;
            box-shadow: 0 0 0 2px #fde68a inset;
        }

        fieldset {
            display: none;
        }

        fieldset.active {
            display: block;
            animation: fade .25s ease;
        }

        @keyframes fade {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .mini-label {
            font-size: .6rem;
            font-weight: 600;
            letter-spacing: .05em;
            color: #92400e;
            text-transform: uppercase;
        }

        .recent-card {
            border: 1px solid #fde68a;
            background: #fff;
            border-radius: 10px;
            padding: .55rem .7rem;
            display: flex;
            flex-direction: column;
            gap: .25rem;
            font-size: .65rem;
        }

        .recent-badge {
            background: #facc15;
            color: #111;
            font-weight: 600;
            font-size: .55rem;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .scroll-slim::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-slim::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .btn-yellow {
            background: #facc15;
            color: #111;
            font-weight: 600;
            font-size: .7rem;
            padding: .55rem .9rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .btn-yellow:hover {
            background: #eab308;
        }

        .btn-outline {
            background: #fff;
            border: 1px solid #facc15;
            color: #92400e;
            font-size: .7rem;
            font-weight: 600;
            padding: .5rem .85rem;
            border-radius: 8px;
        }

        .btn-outline:hover {
            background: #fef3c7;
        }

        .toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: #111;
            color: #fff;
            padding: .6rem .9rem;
            font-size: .7rem;
            border-radius: 8px;
            z-index: 60;
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, .4);
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            z-index: 50;
            padding: 4rem 1rem 2rem;
        }

        .modal.open {
            display: flex;
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Navbar -->
    <header class="app-nav fixed top-0 inset-x-0 z-40 bg-white/80 border-b border-yellow-300 flex items-center h-14 px-3">
        <button id="hambBtn" class="p-2 rounded hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-yellow-400" aria-label="Menu">
            <span class="block w-5 h-0.5 bg-yellow-700 mb-1 hamb-line"></span>
            <span class="block w-5 h-0.5 bg-yellow-700 mb-1 hamb-line"></span>
            <span class="block w-5 h-0.5 bg-yellow-700 hamb-line"></span>
        </button>
        <h1 class="ml-3 text-sm font-extrabold tracking-wide text-yellow-700">Opérateur 1</h1>
        <div class="ml-auto flex items-center gap-2">
            <button id="openModalBtn" class="btn-yellow shadow-sm">Nouveau Voyage</button>
            <a href="recap_carburant.php" class="btn-outline">Récap</a>
            <a href="operateur2.php" class="btn-outline">Opér. 2</a>
            <a href="admin.php" class="btn-outline">Admin</a>
        </div>
    </header>
    <!-- Menu latéral -->
    <nav id="sideMenu" class="fixed top-14 left-0 bottom-0 w-60 bg-white border-r border-yellow-300 p-4 transform -translate-x-full transition-transform duration-300 flex flex-col gap-4 z-30">
        <div class="text-xs font-semibold tracking-wider text-yellow-700">Navigation</div>
        <a class="btn-outline text-left" href="recap_carburant.php">Récap carburant</a>
        <a class="btn-outline text-left" href="operateur2.php">Opérateur 2</a>
        <a class="btn-outline text-left" href="admin.php">Admin</a>
    </nav>
    <!-- Contenu principal -->
    <main class="pt-16 px-3 pb-10 max-w-6xl mx-auto w-full">
        <!-- Barre filtre rapide pour la liste -->
        <div class="card mb-6 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="mini-label block mb-1">Filtrer voyages récents</label>
                    <input id="quickFilter" type="text" placeholder="Rechercher (chauffeur, camion, prestataire)" class="w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                </div>
                <div class="flex items-center gap-2">
                    <button id="refreshList" class="btn-outline">Rafraîchir</button>
                    <button id="openModalBtn2" class="btn-yellow">+ Nouveau</button>
                </div>
            </div>
        </div>
        <!-- Liste des voyages -->
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3" id="recentVoyages"></div>
        <div id="emptyState" class="hidden text-center text-xs text-yellow-700 mt-6">Aucun voyage trouvé…</div>
    </main>

    <!-- Modal Formulaire -->
    <div id="voyageModal" class="modal modal-backdrop">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-yellow-300 relative overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-yellow-200 bg-yellow-50">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold tracking-wide text-yellow-800">NOUVEAU VOYAGE</span>
                </div>
                <button id="closeModalBtn" class="p-2 rounded hover:bg-yellow-200" aria-label="Fermer">✕</button>
            </div>
            <div class="px-5 py-4 max-h-[70vh] overflow-y-auto scroll-slim">
                <div id="steps" class="flex gap-2 mb-4 overflow-x-auto pb-1"></div>
                <form id="voyageForm" class="space-y-6">
                    <input type="hidden" name="action" value="createVoyageOp1" />
                    <!-- Etape 1 -->
                    <fieldset data-step="1" class="active space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-xs font-semibold text-yellow-800">Date du voyage
                                <input type="date" name="date_voyage" required value="<?= date('Y-m-d'); ?>" class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                            </label>
                            <label class="text-xs font-semibold text-yellow-800">Prestataire
                                <select name="prestataire_nom" id="prestataireSelect" required class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400"></select>
                            </label>
                            <label class="text-xs font-semibold text-yellow-800 md:col-span-2">Étape / Opération
                                <select name="operation_id" id="operationSelect" required class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400"></select>
                            </label>
                        </div>
                        <div id="montantOrigineBox" class="text-[11px] font-medium text-yellow-700"></div>
                        <div class="flex justify-end">
                            <button type="button" class="btn-yellow next">Suivant</button>
                        </div>
                    </fieldset>
                    <!-- Etape 2 -->
                    <fieldset data-step="2" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-xs font-semibold text-yellow-800">Nom Chauffeur
                                <input type="text" name="chauffeur_nom" required autocomplete="off" class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                            </label>
                            <label class="text-xs font-semibold text-yellow-800">Téléphone Chauffeur
                                <input type="tel" name="chauffeur_tel" placeholder="07.." class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                            </label>
                        </div>
                        <div class="flex justify-between pt-2">
                            <button type="button" class="btn-outline prev">Retour</button>
                            <button type="button" class="btn-yellow next">Suivant</button>
                        </div>
                    </fieldset>
                    <!-- Etape 3 -->
                    <fieldset data-step="3" class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-xs font-semibold text-yellow-800">Matricule Camion
                                <input type="text" name="camion_matricule" required placeholder="Ex: 21133WWCI01" class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                            </label>
                            <label class="text-xs font-semibold text-yellow-800">Bon de sortie N°
                                <input type="text" name="bon_numero" required class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                            </label>
                            <label class="text-xs font-semibold text-yellow-800 md:col-span-2">Fichier Bon (photo / pdf)
                                <input type="file" name="bon_fichier" accept="image/*,application/pdf" class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm bg-white" />
                            </label>
                            <div id="bonPreviewWrap" class="md:col-span-2"></div>
                        </div>
                        <div class="flex justify-between pt-2">
                            <button type="button" class="btn-outline prev">Retour</button>
                            <button type="button" class="btn-yellow next">Suivant</button>
                        </div>
                    </fieldset>
                    <!-- Etape 4 -->
                    <fieldset data-step="4" class="space-y-4">
                        <h3 class="text-xs font-bold tracking-wide text-yellow-800">Récapitulatif</h3>
                        <div id="recap" class="text-[11px] grid gap-1"></div>
                        <div class="flex justify-between pt-2">
                            <button type="button" class="btn-outline prev">Retour</button>
                            <button type="submit" class="btn-yellow">Enregistrer</button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>

    <div id="toast" class="toast" hidden></div>

    <script src="assets/js/op1.js"></script>
    <script>
        // Hamburger & side menu
        const hambBtn = document.getElementById('hambBtn');
        const sideMenu = document.getElementById('sideMenu');
        hambBtn.addEventListener('click', () => {
            sideMenu.classList.toggle('-translate-x-full');
        });
        // Modal
        const modal = document.getElementById('voyageModal');
        const openBtns = [document.getElementById('openModalBtn'), document.getElementById('openModalBtn2')];
        const closeBtn = document.getElementById('closeModalBtn');
        openBtns.forEach(b => b && b.addEventListener('click', () => {
            modal.classList.add('open');
            document.body.classList.add('overflow-hidden');
        }));
        closeBtn.addEventListener('click', () => {
            modal.classList.remove('open');
            document.body.classList.remove('overflow-hidden');
        });
        modal.addEventListener('click', e => {
            if (e.target === modal) {
                modal.classList.remove('open');
                document.body.classList.remove('overflow-hidden');
            }
        });
        // Quick filter
        const quickInput = document.getElementById('quickFilter');
        const recentWrap = document.getElementById('recentVoyages');
        const emptyState = document.getElementById('emptyState');

        function applyQuickFilter() {
            const q = quickInput.value.trim().toLowerCase();
            let visible = 0;
            [...recentWrap.children].forEach(card => {
                const txt = card.textContent.toLowerCase();
                const show = txt.includes(q);
                card.classList.toggle('hidden', !show);
                if (show) visible++;
            });
            emptyState.classList.toggle('hidden', visible !== 0);
        }
        quickInput.addEventListener('input', () => applyQuickFilter());
        document.getElementById('refreshList').addEventListener('click', () => {
            if (window.loadRecent) {
                loadRecent();
                setTimeout(applyQuickFilter, 300);
            }
        });
        // Adapter rendu récent (hook loadRecent existant) pour cartes
        const origLoadRecent = window.loadRecent;
        if (origLoadRecent) {
            window.loadRecent = function() {
                fetch('api.php?action=listVoyages&statut=SAISI&limit=30')
                    .then(r => r.json())
                    .then(j => {
                        if (!j.ok) return;
                        recentWrap.innerHTML = j.data.map(v => {
                            const isPdf = (v.fichier_path || '').toLowerCase().endsWith('.pdf');
                            const thumb = v.fichier_path ? (isPdf ?
                                `<a href='bon_file.php?bon_id=${v.bon_id}' target='_blank' class='block mt-1 text-[10px] text-yellow-800 underline'>Voir PDF</a>` :
                                `<img src='bon_file.php?bon_id=${v.bon_id}' alt='Bon' class='mt-1 w-full h-20 object-cover rounded border border-yellow-200' loading='lazy' />`) : '';
                            const opLabel = v.operation_label ? `<div class='text-[10px] text-slate-700 italic'>${v.operation_label}</div>` : '';
                            return `<div class='recent-card'>
                                <div class='flex items-center justify-between'><span class='recent-badge'>#${v.id}</span><span class='font-medium text-[10px] text-yellow-800'>${v.date_voyage}</span></div>
                                <div class='text-[11px] font-semibold text-slate-700'>${v.camion_matricule||''}</div>
                                <div class='text-[10px] text-slate-600'>${v.chauffeur||''}</div>
                                ${opLabel}
                                <div class='text-[10px] text-yellow-700'>${Number(v.montant_origine||0).toLocaleString('fr-FR')} CFA</div>
                                ${thumb}
                            </div>`;
                        }).join('');
                        applyQuickFilter();
                    });
            };
            // Recharger immédiatement sous nouveau format
            window.loadRecent();
        }
        // Dark mode (installation si bouton présent)
        const themeBtn = document.getElementById('toggleTheme');
        if (themeBtn) {
            function applyThemeOp1() {
                const mode = localStorage.getItem('theme') || 'light';
                document.documentElement.classList.toggle('dark', mode === 'dark');
                themeBtn.textContent = mode === 'dark' ? '☀️' : '🌙';
                themeBtn.title = mode === 'dark' ? 'Mode clair' : 'Mode sombre';
            }
            themeBtn.addEventListener('click', () => {
                const cur = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
                localStorage.setItem('theme', cur);
                applyThemeOp1();
            });
            applyThemeOp1();
        }
    </script>
</body>

</html>
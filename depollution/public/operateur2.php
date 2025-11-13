<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Opérateur 2 – Validation Voyages</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="shared-ui.css" />
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

        .grid-cards {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }

        .voy-card {
            border: 1px solid #fde68a;
            background: #fff;
            border-radius: 14px;
            padding: .75rem .85rem;
            display: flex;
            flex-direction: column;
            gap: .35rem;
            font-size: .7rem;
            box-shadow: 0 2px 6px -1px rgba(0, 0, 0, .08);
        }

        .voy-card.active {
            outline: 2px solid #facc15;
            box-shadow: 0 0 0 3px #fde68a;
        }

        .badge-sm {
            background: #facc15;
            color: #111;
            font-size: .55rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
        }
    </style>
</head>

<body class="min-h-screen">
    <header class="app-nav fixed top-0 inset-x-0 z-40 bg-white/80 border-b border-yellow-300 flex items-center h-14 px-3">
        <a href="operateur1.php" class="btn-outline">Opér. 1</a>
        <h1 class="ml-3 text-sm font-extrabold tracking-wide text-yellow-700">Validation Voyages</h1>
        <div class="ml-auto flex items-center gap-2">
            <button id="refreshBtn" class="btn-outline">Rafraîchir</button>
            <a href="admin.php" class="btn-outline">Admin</a>
            <button id="toggleTheme2" class="btn-outline" title="Mode sombre">🌙</button>
        </div>
    </header>
    <main class="pt-16 px-3 pb-10 max-w-7xl mx-auto w-full">
        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end">
            <div class="flex-1">
                <label class="text-[10px] font-semibold uppercase tracking-wide text-yellow-800 mb-1 block">Filtrer</label>
                <input id="quickFilter2" type="text" placeholder="Recherche (camion, chauffeur, prestataire)" class="w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
            </div>
        </div>
        <div id="voyagesCards" class="grid-cards mb-8"></div>
        <div id="emptyState2" class="hidden text-center text-xs text-yellow-700">Aucun voyage en attente…</div>
        <!-- Panneau édition modal -->
        <div id="editModal" class="modal">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-yellow-300 relative overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-yellow-200 bg-yellow-50">
                    <span class="text-[11px] font-bold tracking-wide text-yellow-800">CLÔTURE VOYAGE</span>
                    <button id="closeEditModal" class="p-2 rounded hover:bg-yellow-200" aria-label="Fermer">✕</button>
                </div>
                <div class="px-5 py-4 max-h-[70vh] overflow-y-auto scroll-slim">
                    <div id="noSelection" class="text-[11px] text-slate-600 pb-2">Sélectionnez un voyage.</div>
                    <form id="formOp2" hidden class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-2 text-[11px]">
                            <label class="font-semibold text-yellow-800">Montant origine
                                <input type="text" name="montant_origine" readonly class="mt-1 w-full border border-yellow-300 rounded px-2 py-2 text-sm bg-slate-50" />
                            </label>
                            <label class="font-semibold text-yellow-800">Frais route (CFA)
                                <input type="number" name="frais_route" min="0" step="1" value="0" class="mt-1 w-full border border-yellow-300 rounded px-2 py-2 text-sm" />
                            </label>
                            <label class="font-semibold text-yellow-800 flex items-center gap-2">Carburant ?
                                <input type="checkbox" id="useCarb" checked class="h-4 w-4 text-yellow-600" />
                            </label>
                            <label class="font-semibold text-yellow-800">Litres
                                <input type="number" name="carburant_litre" min="0" step="1" value="50" class="mt-1 w-full border border-yellow-300 rounded px-2 py-2 text-sm" />
                            </label>
                            <label class="font-semibold text-yellow-800">Montant Carburant
                                <input type="number" name="carburant_montant" min="0" step="1" value="0" class="mt-1 w-full border border-yellow-300 rounded px-2 py-2 text-sm" />
                            </label>
                            <label class="font-semibold text-yellow-800">Réel Reçu
                                <input type="text" name="reel_recu" readonly class="mt-1 w-full border border-yellow-300 rounded px-2 py-2 text-sm bg-slate-50" />
                            </label>
                        </div>
                        <p class="text-[10px] text-slate-600">Modifier litres ajuste le montant et inversement (prix auto config).</p>
                        <div class="flex justify-end pt-2">
                            <button type="button" id="btnCancelVoy" class="mr-2 px-3 py-2 rounded border border-red-300 text-red-700 text-[11px] font-semibold hover:bg-red-50">Annuler</button>
                            <button type="submit" class="btn-yellow">Clore le voyage</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <div id="toast" class="toast" hidden></div>
    <!-- (Ancien op2.js supprimé après refonte) -->
    <script>
        // Conversion tableau -> cartes
        const API = 'api.php';
        let voyages = [];
        let selectedId = null;
        let prixLitre = 675;
        fetch(API + '?action=configCarburant').then(r => r.json()).then(j => {
            if (j.ok) prixLitre = j.prix_litre;
        });
        // Helper échappement basique pour éviter injection HTML
        const h = (str) => {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        const cardsWrap = document.getElementById('voyagesCards');
        const empty2 = document.getElementById('emptyState2');
        const quick2 = document.getElementById('quickFilter2');
        const editModal = document.getElementById('editModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const form = document.getElementById('formOp2');
        const noSel = document.getElementById('noSelection');
        const useCarb = document.getElementById('useCarb');
        const toastEl = document.getElementById('toast');

        function toast(msg, ok = true) {
            toastEl.textContent = msg;
            toastEl.style.background = ok ? '#111' : '#b91c1c';
            toastEl.hidden = false;
            setTimeout(() => toastEl.hidden = true, 3000);
        }

        function load() {
            fetch(API + '?action=listVoyages&statut=SAISI&limit=300').then(r => r.json()).then(j => {
                if (!j.ok) return;
                voyages = j.data;
                render();
                if (selectedId && !voyages.find(v => v.id == selectedId)) clearSelection();
            });
        }

        function render() {
            const q = quick2.value.trim().toLowerCase();
            let html = '';
            let visible = 0;
            voyages.forEach(v => {
                const txt = (v.camion_matricule + ' ' + v.chauffeur + ' ' + v.prestataire).toLowerCase();
                if (q && !txt.includes(q)) return;
                visible++;
                const active = v.id == selectedId;
                const isPdf = String(v.fichier_path || '').toLowerCase().endsWith('.pdf');
                const thumb = v.fichier_path ? (isPdf ?
                        `<a href="bon_file.php?bon_id=${h(v.bon_id)}" target="_blank" class="block mt-1 text-[10px] text-yellow-800 underline">Voir PDF</a>` :
                        `<img src="bon_file.php?bon_id=${h(v.bon_id)}" alt="Bon" style="width:72px;height:52px;object-fit:cover;border-radius:6px;border:1px solid #fde68a;margin-right:.5rem;" onerror="this.remove()"/>`) :
                    '';
                const bonLine = `<div class='text-[10px] text-slate-700'>Bon: <span class='font-semibold'>${h(v.bon_numero)||'-'}</span></div>`;
                const bonBadge = v.fichier_path ?
                    `<span class='inline-block bg-green-100 text-green-700 text-[10px] px-2 py-0.5 rounded'>Bon présent</span>` :
                    `<span class='inline-block bg-gray-100 text-gray-700 text-[10px] px-2 py-0.5 rounded'>Bon manquant</span>`;
                const opLine = v.operation_label ? `<div class='text-[10px] text-slate-700 italic'>${h(v.operation_label)}</div>` : '';
                html += `<div class='voy-card ${active?'active':''}' data-id='${h(v.id)}'>
                    <div class='flex items-center justify-between'><span class='badge-sm'>#${h(v.id)}</span><span class='text-[10px] text-yellow-800 font-medium'>${h(v.date_voyage)}</span></div>
                    <div class='flex items-start gap-2'>${thumb}
                        <div>
                            <div class='text-[11px] font-semibold text-slate-700'>${h(v.camion_matricule)||''}</div>
                            <div class='text-[10px] text-slate-600'>${h(v.chauffeur)||''}</div>
                            ${opLine}
                            ${bonLine}
                            <div>${bonBadge}</div>
                            <div class='text-[10px] text-amber-600'>Prest: ${h(v.prestataire)||''}</div>
                            <div class='text-[10px] text-yellow-700'>Orig: ${Number(v.montant_origine||0).toLocaleString('fr-FR')} CFA</div>
                            <div class='text-[10px] text-slate-500'>Frais: ${Number(v.frais_route||0).toLocaleString('fr-FR')} | Carb: ${Number(v.carburant_montant||0).toLocaleString('fr-FR')}</div>
                        </div>
                    </div>
                </div>`;
            });
            cardsWrap.innerHTML = html;
            empty2.classList.toggle('hidden', visible !== 0);
        }

        function clearSelection() {
            selectedId = null;
            form.hidden = true;
            noSel.style.display = 'block';
        }

        function openEditor(id) {
            const v = voyages.find(x => x.id == id);
            if (!v) return;
            noSel.style.display = 'none';
            form.hidden = false;
            form.dataset.id = id;
            form.montant_origine.value = v.montant_origine;
            form.frais_route.value = v.frais_route || 0;
            form.carburant_litre.value = v.carburant_litre || 50;
            form.carburant_montant.value = v.carburant_montant || (form.carburant_litre.value || 0) * prixLitre;
            // Affichage prestataire dans un élément dédié si non encore présent
            let prestSpan = document.getElementById('prestataireInfo');
            if (!prestSpan) {
                const container = document.createElement('div');
                container.className = 'text-[11px] text-slate-600';
                container.id = 'prestataireInfo';
                form.prepend(container);
                prestSpan = container;
            }
            prestSpan.innerHTML = '<strong>Prestataire:</strong> ' + h(v.prestataire || '');
            computeReel();
            editModal.classList.add('open');
        }

        function computeReel() {
            const montantOrig = parseFloat(form.montant_origine.value) || 0;
            const fr = parseFloat(form.frais_route.value) || 0;
            const carb = parseFloat(form.carburant_montant.value) || 0;
            form.reel_recu.value = (montantOrig - fr - carb).toFixed(0);
        }
        form.addEventListener('input', e => {
            if (e.target === form.frais_route) computeReel();
        });
        form.carburant_litre.addEventListener('input', () => {
            if (!useCarb.checked) {
                form.carburant_litre.value = 0;
                return;
            }
            const l = parseFloat(form.carburant_litre.value) || 0;
            form.carburant_montant.value = (l * prixLitre).toFixed(0);
            computeReel();
        });
        form.carburant_montant.addEventListener('input', () => {
            if (!useCarb.checked) {
                form.carburant_montant.value = 0;
                return;
            }
            const m = parseFloat(form.carburant_montant.value) || 0;
            form.carburant_litre.value = (m / prixLitre).toFixed(0);
            computeReel();
        });
        useCarb.addEventListener('change', () => {
            if (!useCarb.checked) {
                form.carburant_litre.value = 0;
                form.carburant_montant.value = 0;
            } else {
                if (!form.carburant_litre.value) form.carburant_litre.value = 50;
                form.carburant_montant.value = (parseFloat(form.carburant_litre.value) * prixLitre).toFixed(0);
            }
            computeReel();
        });
        quick2.addEventListener('input', render);
        document.getElementById('refreshBtn').addEventListener('click', () => {
            load();
        });
        cardsWrap.addEventListener('click', e => {
            const card = e.target.closest('.voy-card');
            if (!card) return;
            selectedId = card.dataset.id;
            render();
            openEditor(selectedId);
        });
        closeEditModal.addEventListener('click', () => {
            editModal.classList.remove('open');
        });
        editModal.addEventListener('click', e => {
            if (e.target === editModal) {
                editModal.classList.remove('open');
            }
        });
        form.addEventListener('submit', e => {
            e.preventDefault();
            if (!selectedId) return;
            const fd = new FormData();
            fd.append('action', 'updateVoyageOp2');
            fd.append('voyage_id', selectedId);
            fd.append('frais_route', form.frais_route.value || 0);
            fd.append('carburant_litre', form.carburant_litre.value || 0);
            fd.append('carburant_montant', form.carburant_montant.value || 0);
            fetch(API, {
                method: 'POST',
                body: fd
            }).then(r => r.json()).then(j => {
                if (!j.ok) {
                    toast(j.error || 'Erreur maj', false);
                    return;
                }
                toast('Voyage #' + selectedId + ' clos');
                load();
                clearSelection();
                editModal.classList.remove('open');
            }).catch(() => toast('Erreur réseau', false));
        });
        // Annulation voyage
        const btnCancelVoy = document.getElementById('btnCancelVoy');
        if (btnCancelVoy) {
            btnCancelVoy.addEventListener('click', () => {
                if (!selectedId) return;
                const motif = prompt('Motif annulation (optionnel) :', '');
                if (motif === null) return; // annulé
                if (!confirm('Confirmer annulation du voyage #' + selectedId + ' ?')) return;
                const fd = new FormData();
                fd.append('action', 'cancelVoyageOp2');
                fd.append('voyage_id', selectedId);
                if (motif.trim() !== '') fd.append('reason', motif.trim());
                fetch(API, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(j => {
                        if (!j.ok) {
                            toast(j.error || 'Annulation échouée', false);
                            return;
                        }
                        toast('Voyage #' + selectedId + ' annulé');
                        load();
                        clearSelection();
                        editModal.classList.remove('open');
                    })
                    .catch(() => toast('Erreur réseau', false));
            });
        }
        // Theme toggle
        const themeBtn2 = document.getElementById('toggleTheme2');

        function applyTheme2() {
            const mode = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', mode === 'dark');
            themeBtn2.textContent = mode === 'dark' ? '☀️' : '🌙';
            themeBtn2.title = mode === 'dark' ? 'Mode clair' : 'Mode sombre';
        }
        themeBtn2.addEventListener('click', () => {
            const cur = localStorage.getItem('theme') === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', cur);
            applyTheme2();
        });
        applyTheme2();
        load();
        setInterval(load, 15000);
    </script>
</body>

</html>
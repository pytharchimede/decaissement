<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bons manquants – Ajout fichiers</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #fff 100%);
            font-family: system-ui, 'Segoe UI', sans-serif;
        }

        .card {
            background: #fff;
            border: 1px solid #facc15;
            border-radius: 14px;
            box-shadow: 0 4px 14px -2px rgba(0, 0, 0, .08);
        }

        .toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            background: #111;
            color: #fff;
            padding: .6rem .9rem;
            font-size: .75rem;
            border-radius: 8px;
            z-index: 60
        }
    </style>
</head>

<body class="min-h-screen">
    <header class="fixed top-0 inset-x-0 z-40 bg-white/80 border-b border-yellow-300 flex items-center h-14 px-3 app-nav">
        <a href="operateur1.php" class="px-3 py-1 text-xs rounded border border-yellow-400 text-yellow-800 hover:bg-yellow-50">Opér. 1</a>
        <h1 class="ml-3 text-sm font-extrabold tracking-wide text-yellow-700">Bons sans image</h1>
        <div class="ml-auto flex items-center gap-2">
            <a href="operateur2.php" class="px-3 py-1 text-xs rounded border border-yellow-400 text-yellow-800 hover:bg-yellow-50">Opér. 2</a>
            <a href="index.php" class="px-3 py-1 text-xs rounded border border-yellow-400 text-yellow-800 hover:bg-yellow-50">Accueil</a>
        </div>
    </header>
    <main class="pt-16 px-3 pb-10 max-w-6xl mx-auto w-full">
        <div class="card p-4 mb-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-end">
                <div class="flex-1">
                    <label class="text-xs font-semibold text-yellow-800">Rechercher</label>
                    <input id="q" type="text" placeholder="N° bon ou prestataire" class="mt-1 w-full border border-yellow-300 rounded px-3 py-2 text-sm focus:ring-yellow-400 focus:border-yellow-400" />
                </div>
                <div class="flex items-center gap-2">
                    <button id="btnRefresh" class="px-3 py-1 text-xs rounded border border-yellow-400 text-yellow-800 hover:bg-yellow-50">Rafraîchir</button>
                </div>
            </div>
        </div>
        <div id="list" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3"></div>
        <div id="empty" class="hidden text-center text-xs text-yellow-700 mt-6">Aucun bon manquant ✨</div>
    </main>
    <div id="toast" class="toast" hidden></div>
    <script>
        const API = 'api.php';
        const list = document.getElementById('list');
        const empty = document.getElementById('empty');
        const q = document.getElementById('q');
        const toastEl = document.getElementById('toast');

        function toast(msg, ok = true) {
            toastEl.textContent = msg;
            toastEl.style.background = ok ? '#111' : '#b91c1c';
            toastEl.hidden = false;
            setTimeout(() => toastEl.hidden = true, 3000);
        }

        function load() {
            const url = new URL(API, location.href);
            url.searchParams.set('action', 'listMissingBons');
            url.searchParams.set('limit', '200');
            if (q.value.trim() !== '') url.searchParams.set('q', q.value.trim());
            fetch(url).then(r => r.json()).then(j => {
                if (!j.ok) {
                    toast('Erreur chargement', false);
                    return;
                }
                const rows = j.data || [];
                if (!rows.length) {
                    list.innerHTML = '';
                    empty.classList.remove('hidden');
                    return;
                }
                empty.classList.add('hidden');
                list.innerHTML = rows.map(b => {
                    const id = b.bon_id;
                    const numero = b.numero || '-';
                    const prest = b.prestataire || '';
                    const voy = b.voyage_id ? ('#' + b.voyage_id) : '';
                    return `<div class='card p-3'>
            <div class='flex items-center justify-between'><span class='text-[10px] font-bold text-yellow-800'>Bon ${numero}</span><span class='text-[10px] text-slate-500'>${voy}</span></div>
            <div class='text-[10px] text-slate-600 mb-2'>${prest}</div>
            <div class='space-y-2'>
              <input type='file' accept='image/*,application/pdf' capture='environment' class='w-full border border-yellow-300 rounded px-2 py-1 text-sm bg-white' data-file='${id}' />
              <div class='flex items-center gap-2'>
                <button class='px-3 py-1 text-xs rounded bg-yellow-400 text-yellow-900 hover:bg-yellow-500' data-upload='${id}'>Ajouter</button>
                <a href='bon_file.php?bon_id=${id}' target='_blank' class='px-3 py-1 text-[11px] rounded border border-yellow-400 text-yellow-800 hover:bg-yellow-50' style='display:none' data-view='${id}'>Voir</a>
              </div>
              <div class='text-[10px] text-slate-500' data-status='${id}'></div>
            </div>
          </div>`;
                }).join('');
            });
        }

        function upload(bonId, file) {
            const fd = new FormData();
            fd.append('action', 'uploadBonFile');
            fd.append('bon_id', bonId);
            fd.append('bon_fichier', file);
            fetch(API, {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json()).then(j => {
                    const status = document.querySelector(`[data-status='${bonId}']`);
                    const view = document.querySelector(`[data-view='${bonId}']`);
                    if (!j.ok) {
                        status && (status.textContent = 'Erreur: ' + (j.error || 'upload'));
                        toast('Erreur upload', false);
                        return;
                    }
                    status && (status.textContent = 'Ajouté ✓');
                    view && (view.style.display = 'inline-flex');
                    toast('Fichier enregistré');
                    // retirer la carte après petit délai
                    setTimeout(() => {
                        const card = document.querySelector(`[data-upload='${bonId}']`)?.closest('.card');
                        if (card) card.remove();
                        if (!list.children.length) empty.classList.remove('hidden');
                    }, 600);
                })
                .catch(() => toast('Erreur réseau', false));
        }

        list.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-upload]');
            if (!btn) return;
            const id = btn.getAttribute('data-upload');
            const inp = document.querySelector(`[data-file='${id}']`);
            const f = inp && inp.files && inp.files[0];
            if (!f) {
                toast('Choisir un fichier', false);
                return;
            }
            upload(id, f);
        });

        document.getElementById('btnRefresh').addEventListener('click', load);
        q.addEventListener('input', () => {
            clearTimeout(window.__t);
            window.__t = setTimeout(load, 350);
        });

        load();
    </script>
</body>

</html>
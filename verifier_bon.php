<?php
// Page de vérification "Google-like" par numéro de reçu station
// Objectif: un champ unique, résultats clairs, design sobre et moderne

require_once __DIR__ . '/model/Database.php';

$q = isset($_GET['q']) ? (string)$_GET['q'] : '';

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function split_tokens(string $s): array
{
    $parts = preg_split('/[\s,;]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $norm = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t !== '') {
            $norm[] = mb_substr($t, 0, 64, 'UTF-8');
        }
    }
    $seen = [];
    $out = [];
    foreach ($norm as $t) {
        if (!isset($seen[$t])) {
            $seen[$t] = true;
            $out[] = $t;
        }
    }
    return array_slice($out, 0, 100);
}
$tokens = [];
$foundMap = []; // token => row|NULL
if (trim($q) !== '') {
    $tokens = split_tokens($q);
    if (!empty($tokens)) {
        try {
            $pdo = Database::getConnection();
            // Préparer IN (:nr0, :nr1, ...) et (:cb0, :cb1, ...)
            $nrPh = [];
            $cbPh = [];
            $params = [];
            foreach ($tokens as $i => $tok) {
                $nrPh[] = ":nr$i";
                $params[":nr$i"] = $tok;
                $cbPh[] = ":cb$i";
                $params[":cb$i"] = $tok;
            }
            // Si aucun token, on met un faux param pour éviter une erreur SQL
            if (empty($nrPh)) {
                $nrPh[] = ":nr0";
                $params[":nr0"] = "";
            }
            if (empty($cbPh)) {
                $cbPh[] = ":cb0";
                $params[":cb0"] = "";
            }
            $inNr = implode(',', $nrPh);
            $inCb = implode(',', $cbPh);

            $sql = "SELECT *
                    FROM demande_essence
                    WHERE (num_recu IN ($inNr))
                       OR (code_bon IN ($inCb))
                    ORDER BY date_demande DESC";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                $error = 'Erreur SQL: ' . $e->getMessage();
                $rows = [];
            }

            // Indexation: on retient la première occurrence (la plus récente grâce à l'ORDER BY)
            $byRecu = [];
            $byBon  = [];
            foreach ($rows as $r) {
                $nr = isset($r['num_recu']) ? trim((string)$r['num_recu']) : '';
                $cb = isset($r['code_bon']) ? trim((string)$r['code_bon']) : '';
                if ($nr !== '' && !isset($byRecu[$nr])) $byRecu[$nr] = $r;
                if ($cb !== '' && !isset($byBon[$cb])) $byBon[$cb] = $r;
            }
            // Résolution par token: priorité au match reçu puis code bon
            foreach ($tokens as $t) {
                if (isset($byRecu[$t])) {
                    $foundMap[$t] = $byRecu[$t];
                } elseif (isset($byBon[$t])) {
                    $foundMap[$t] = $byBon[$t];
                } else {
                    $foundMap[$t] = null;
                }
            }
        } catch (Throwable $e) {
            $error = 'Erreur PHP: ' . $e->getMessage();
        }
    }
}

// ...existing code...


?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification de Bon | Reçu Station</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff;
            --fg: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --accent: #0ea5e9;
            --ok: #10b981;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box
        }

        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--fg);
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, Ubuntu, "Apple Color Emoji", "Segoe UI Emoji"
        }

        .wrap {
            min-height: 100%;
            display: flex;
            flex-direction: column
        }

        header {
            padding: 20px 24px
        }

        .brand {
            display: flex;
            gap: 10px;
            align-items: center;
            color: var(--fg);
            text-decoration: none;
            font-weight: 600
        }

        .brand .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent);
            display: inline-block
        }

        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px
        }

        .hero-inner {
            width: 100%;
            max-width: 820px;
            text-align: center
        }

        .title {
            font-size: 34px;
            line-height: 1.2;
            margin: 0 0 10px
        }

        .subtitle {
            color: var(--muted);
            margin: 0 0 26px;
            font-size: 16px
        }

        .search {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center
        }

        .search-box {
            flex: 1;
            max-width: 820px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            flex-wrap: wrap
        }

        .search-box input[type="text"] {
            flex: 1 1 240px;
            min-width: 220px;
            border: 0;
            outline: 0;
            font-size: 18px;
            padding: 8px 2px;
            background: transparent;
            color: var(--fg)
        }

        .search-box button {
            border: 0;
            outline: 0;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            border-radius: 999px;
            padding: 10px 16px;
            cursor: pointer
        }

        .search-box button:hover {
            filter: brightness(0.95)
        }

        .result-panel {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(2, 6, 23, .06);
            overflow: hidden
        }

        .card-body {
            padding: 18px
        }

        .ok-row {
            display: flex;
            gap: 12px;
            align-items: center
        }

        .ok-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            background: rgba(16, 185, 129, .12);
            border: 1px solid rgba(16, 185, 129, .2)
        }

        .ok-icon svg {
            width: 22px;
            height: 22px;
            color: var(--ok)
        }

        .ok-title {
            font-weight: 600
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 14px
        }

        .meta .pill {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 6px 10px;
            background: #fafafa
        }

        .preview {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 16px
        }

        .frame {
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            height: 420px
        }

        .frame iframe {
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff
        }

        .frame-img {
            border: 1px solid var(--line);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            height: 420px
        }

        .frame-img img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain
        }

        .muted {
            color: var(--muted)
        }

        @media(min-width:960px) {
            .preview {
                grid-template-columns: 1fr 1fr
            }
        }

        footer {
            padding: 24px;
            text-align: center;
            color: var(--muted);
            font-size: 13px
        }

        .help {
            margin-top: 8px;
            color: var(--muted)
        }

        /* Tags (badges) */
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f3f4f6;
            border: 1px solid var(--line);
            color: #111827;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 14px
        }

        .chip .x {
            display: inline-grid;
            place-items: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #374151;
            cursor: pointer
        }

        .chip .x:hover {
            background: #d1d5db
        }
    </style>
</head>

<body>
    <div class="wrap">
        <header>
            <a class="brand" href="verifier_bon.php">
                <span class="dot"></span>
                <span>Vérification des Bons</span>
            </a>
        </header>

        <main class="hero">
            <div class="hero-inner">
                <h1 class="title">Vérifier un bon en un instant</h1>
                <p class="subtitle">Saisissez un ou plusieurs numéros (reçu station ou code bon), séparés par des virgules, espaces ou retours à la ligne.</p>

                <form id="verifyForm" class="search" method="get" action="verifier_bon.php" autocomplete="off">
                    <div class="search-box">
                        <div id="tagList" class="tag-list"></div>
                        <input id="tagInput" type="text" inputmode="text" placeholder="Saisir un numéro puis Entrée" autofocus>
                        <input id="hiddenQ" type="hidden" name="q" value="<?= e($q) ?>">
                        <button type="submit">Vérifier</button>
                    </div>
                </form>

                <?php if ($error): ?>
                    <div class="result-panel">
                        <div class="card">
                            <div class="card-body">
                                <div class="ok-row" style="color:var(--danger)">
                                    <div class="ok-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="ok-title">Erreur lors de la vérification</div>
                                        <div class="muted"><?= e($error) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (trim($q) !== ''): ?>
                    <div class="result-panel">
                        <?php if (empty($tokens)): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="ok-row">
                                        <div class="ok-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)">
                                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="ok-title">Aucun numéro détecté</div>
                                            <div class="muted">Saisissez un ou plusieurs numéros séparés par virgules, espaces ou retours à la ligne.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tokens as $tok): ?>
                                <?php $r = $foundMap[$tok] ?? null; ?>
                                <?php if (!$r): ?>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="ok-row">
                                                <div class="ok-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)">
                                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="ok-title">Non trouvé</div>
                                                    <div class="meta">
                                                        <div class="pill">Entrée: <strong><?= e($tok) ?></strong></div>
                                                    </div>
                                                    <div class="muted">Aucune ligne ne correspond à ce numéro (ni en reçu station, ni en code bon).</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $codeBon = (string)($r['code_bon'] ?? '');
                                    $nom = (string)($r['nom_beneficiaire'] ?? '');
                                    $montant = (float)($r['montant'] ?? 0);
                                    $dateDem = !empty($r['date_demande']) ? date('d/m/Y', strtotime($r['date_demande'])) : '';
                                    $img = isset($r['img_recu_station']) ? trim((string)$r['img_recu_station']) : '';
                                    $hasReceipt = $img !== '';
                                    ?>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="ok-row">
                                                <div class="ok-icon">
                                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M20 7L10 17l-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="ok-title">Bon trouvé en base</div>
                                                    <div class="meta">
                                                        <div class="pill">Entrée: <strong><?= e($tok) ?></strong></div>
                                                        <div class="pill">Code bon: <strong class="font-mono"><?= e($codeBon) ?></strong></div>
                                                        <?php if ($dateDem): ?><div class="pill">Date: <?= e($dateDem) ?></div><?php endif; ?>
                                                        <?php if ($nom): ?><div class="pill">Bénéficiaire: <?= e($nom) ?></div><?php endif; ?>
                                                        <div class="pill">Montant: <?= number_format($montant, 0, ',', ' ') ?> FCFA</div>
                                                        <div class="pill">Reçu station: <?= $hasReceipt ? '<span style="color:var(--ok);font-weight:600">disponible</span>' : '<span style="color:var(--muted)">non joint</span>' ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="preview">
                                                <div class="frame">
                                                    <iframe src="bon/bon_essence.php?id_bon=<?= urlencode($codeBon) ?>" title="Bon d'essence" loading="lazy"></iframe>
                                                </div>
                                                <div class="frame-img">
                                                    <?php if ($hasReceipt): ?>
                                                        <img src="https://fidest.ci/decaissement/uploads/recu_station/<?= e($img) ?>" alt="Reçu station" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="muted">Aucun reçu joint pour ce bon.</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="help">Astuce: tapez un numéro puis Entrée pour créer un badge. Vous pouvez aussi coller une liste (les entrées seront automatiquement converties en badges).</div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            © <?= date('Y') ?> Vérification des Bons — Contrôle rapide et fiable
        </footer>
    </div>

    <script>
        (function() {
            const hiddenQ = document.getElementById('hiddenQ');
            const tagInput = document.getElementById('tagInput');
            const tagList = document.getElementById('tagList');
            const form = document.getElementById('verifyForm');

            const MAX_ITEMS = 100;
            const MAX_LEN = 64;
            let tokens = [];

            function splitTokens(s) {
                if (!s) return [];
                return (s || '').split(/[\s,;]+/).map(t => t.trim()).filter(Boolean);
            }

            function addToken(raw) {
                let t = (raw || '').trim();
                if (!t) return;
                if (t.length > MAX_LEN) t = t.slice(0, MAX_LEN);
                if (tokens.includes(t)) return;
                if (tokens.length >= MAX_ITEMS) return;
                tokens.push(t);
                render();
            }

            function removeToken(t) {
                tokens = tokens.filter(x => x !== t);
                render();
            }

            function render() {
                tagList.innerHTML = '';
                const frag = document.createDocumentFragment();
                tokens.forEach(t => {
                    const chip = document.createElement('span');
                    chip.className = 'chip';
                    chip.innerHTML = `<span class="label"></span><span class="x" title="Supprimer">×</span>`;
                    chip.querySelector('.label').textContent = t;
                    chip.querySelector('.x').addEventListener('click', () => removeToken(t));
                    frag.appendChild(chip);
                });
                tagList.appendChild(frag);
                // Keep input visually after tags
                hiddenQ.value = tokens.join(',');
            }

            // Init from server value (q)
            tokens = splitTokens(hiddenQ.value).slice(0, MAX_ITEMS);
            render();

            tagInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
                    e.preventDefault();
                    const value = tagInput.value;
                    const batch = splitTokens(value);
                    if (batch.length > 1) {
                        batch.forEach(addToken);
                    } else {
                        addToken(value);
                    }
                    tagInput.value = '';
                }
            });

            tagInput.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (text && /[\s,;]/.test(text)) {
                    e.preventDefault();
                    splitTokens(text).forEach(addToken);
                    tagInput.value = '';
                }
            });

            form.addEventListener('submit', () => {
                // Ensure any pending value becomes a token
                const v = tagInput.value.trim();
                if (v) addToken(v);
                hiddenQ.value = tokens.join(',');
            });
        })();
    </script>

</body>

</html>
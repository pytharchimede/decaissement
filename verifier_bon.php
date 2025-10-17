<?php
// Page de vérification "Google-like" par numéro de reçu station
// Objectif: un champ unique, résultats clairs, design sobre et moderne

require_once __DIR__ . '/model/Database.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$results = [];
$error = '';

if ($q !== '') {
    try {
        $pdo = Database::getConnection();
        // Recherche stricte par numéro de reçu station
        $sql = "SELECT * FROM demande_essence WHERE num_recu = :nr ORDER BY date_demande DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nr' => $q]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $error = 'Une erreur est survenue lors de la vérification.';
    }
}

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

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
            max-width: 720px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04)
        }

        .search-box input {
            flex: 1;
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
                <p class="subtitle">Saisissez le numéro de reçu station pour confirmer la présence du bon et visualiser les pièces associées.</p>

                <form class="search" method="get" action="verifier_bon.php" autocomplete="off">
                    <div class="search-box">
                        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Numéro de reçu station (ex. RS-000123)" autofocus required>
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

                <?php if ($q !== ''): ?>
                    <div class="result-panel">
                        <?php if (empty($results)): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="ok-row">
                                        <div class="ok-icon" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)">
                                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="ok-title">Aucun enregistrement trouvé</div>
                                            <div class="muted">Aucune ligne ne correspond au numéro de reçu « <?= e($q) ?> ». Vérifiez la saisie et réessayez.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($results as $r): ?>
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
                                                    <div class="pill">N° reçu: <strong><?= e($q) ?></strong></div>
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
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="help">Astuce: vous pouvez coller un numéro au format exact du reçu station pour un contrôle immédiat.</div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            © <?= date('Y') ?> Vérification des Bons — Contrôle rapide et fiable
        </footer>
    </div>
</body>

</html>
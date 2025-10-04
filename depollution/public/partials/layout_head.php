<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Depollution';
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" href="/favicon.ico" />
    <!-- Tailwind CDN (could be swapped for compiled build later) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#0f766e'
                        }
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="/depollution/public/theme.css?v=1" />
    <style>
        body {
            font-feature-settings: "tnum" on, "lnum" on;
        }

        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: transparent;
        }
    </style>
</head>

<body class="min-h-full bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100">
    <?php include __DIR__ . '/layout_nav.php'; ?>
    <main id="app-main" class="pt-20 px-3 sm:px-6 pb-12 max-w-[1600px] mx-auto w-full transition-colors">
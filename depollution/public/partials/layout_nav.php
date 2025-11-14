<?php
$current = $_SERVER['REQUEST_URI'] ?? '';
function navItem($href, $label, $current)
{
    $active = strpos($current, $href) !== false ? 'bg-primary/15 text-primary font-semibold' : 'hover:bg-slate-200 dark:hover:bg-slate-700';
    return "<a href='$href' class='px-3 py-2 rounded-md text-sm $active transition-colors'>" . htmlspecialchars($label) . "</a>";
}
?>
<header class="fixed top-0 inset-x-0 z-40 backdrop-blur bg-white/75 dark:bg-slate-950/70 border-b border-slate-200 dark:border-slate-800">
    <div class="flex items-center gap-4 px-4 sm:px-6 h-16">
        <button id="hamburgerBtn" class="md:hidden p-2 rounded hover:bg-slate-200 dark:hover:bg-slate-700" aria-label="Menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <div class="flex items-center gap-2 font-semibold text-primary">
            <span class="text-xl">♻️ Dépollution</span>
            <span class="text-xs px-2 py-0.5 rounded bg-primary/10 text-primary">v1</span>
        </div>
        <nav id="mainNav" class="hidden md:flex items-center gap-1 text-slate-700 dark:text-slate-200">
            <?= navItem('/depollution/public/recap_carburant.php', 'Recap Carburant', $current) ?>
            <?= navItem('/depollution/public/attach_bons.php', 'Bons sans image', $current) ?>
            <?= navItem('/depollution/public/index.php', 'Dashboard', $current) ?>
            <?= navItem('/recap_carburant.php', 'Legacy Recap', $current) ?>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            <button id="themeToggle" class="p-2 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Basculer thème">
                <svg id="themeIconSun" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2m0 16v2m10-10h-2M4 12H2m15.07 7.07-1.42-1.42M8.35 8.35 6.93 6.93m0 10.14 1.42-1.42m10.14 0-1.42 1.42" />
                </svg>
                <svg id="themeIconMoon" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 0 1 11.21 3 7 7 0 1 0 21 12.79z" />
                </svg>
            </button>
        </div>
    </div>
    <nav id="mobileNav" class="md:hidden hidden px-4 pb-4 flex flex-col gap-1 text-slate-700 dark:text-slate-200">
        <?= navItem('/depollution/public/recap_carburant.php', 'Recap Carburant', $current) ?>
        <?= navItem('/depollution/public/attach_bons.php', 'Bons sans image', $current) ?>
        <?= navItem('/depollution/public/index.php', 'Dashboard', $current) ?>
        <?= navItem('/recap_carburant.php', 'Legacy Recap', $current) ?>
    </nav>
</header>
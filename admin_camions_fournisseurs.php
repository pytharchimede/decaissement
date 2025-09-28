<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/CamionFournisseurRepository.php';

$pdo = (new Database())->getConnection();
$repo = new CamionFournisseurRepository($pdo);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $mat = trim($_POST['matricule'] ?? '');
        $fou = trim($_POST['fournisseur'] ?? '');
        if ($repo->setMapping($mat, $fou)) {
            $message = 'Affectation enregistrée.';
        } else {
            $message = "Erreur lors de l'enregistrement.";
        }
    } elseif ($action === 'delete') {
        $mat = trim($_POST['matricule'] ?? '');
        if ($repo->deleteMapping($mat)) {
            $message = 'Affectation supprimée.';
        } else {
            $message = 'Suppression impossible.';
        }
    }
}

$mappings = $repo->getAllMappings();
ksort($mappings);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affectation Camions ↔ Fournisseurs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        body {
            background: #fafafa
        }
    </style>
</head>

<body class="p-4">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-extrabold text-yellow-700">Affectation des camions par fournisseur</h1>
            <a href="recap_carburant.php" class="text-sm text-blue-700 underline">← Retour récap</a>
        </div>
        <?php if ($message): ?>
            <div class="mb-3 p-2 rounded bg-yellow-50 text-yellow-800 border border-yellow-200 text-sm"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="bg-white rounded shadow p-3 mb-6">
            <form method="post" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                <div>
                    <label class="text-xs text-gray-600">Matricule</label>
                    <input type="text" name="matricule" class="border rounded px-2 py-1 w-full" placeholder="Ex: 1234-AB" required>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Fournisseur</label>
                    <input type="text" name="fournisseur" class="border rounded px-2 py-1 w-full" placeholder="Nom du fournisseur" required>
                </div>
                <div>
                    <input type="hidden" name="action" value="save">
                    <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded font-bold text-sm w-full">Enregistrer</button>
                </div>
            </form>
        </div>
        <div class="bg-white rounded shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-yellow-100">
                    <tr>
                        <th class="px-2 py-2 text-left">Matricule</th>
                        <th class="px-2 py-2 text-left">Fournisseur</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $mat => $fou): ?>
                        <tr class="border-b">
                            <td class="px-2 py-2 font-mono"><?= htmlspecialchars($mat) ?></td>
                            <td class="px-2 py-2"><?= htmlspecialchars($fou) ?></td>
                            <td class="px-2 py-2 text-right">
                                <form method="post" onsubmit="return confirm('Supprimer cette affectation ?');" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="matricule" value="<?= htmlspecialchars($mat) ?>">
                                    <button class="text-red-700 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($mappings)): ?>
                        <tr>
                            <td colspan="3" class="px-2 py-4 text-center text-gray-400">Aucune affectation pour le moment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
<?php
// filepath: c:\wamp\www\decaissement\test_valider_fiche_reparation.php
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Test Validation Fiche Réparation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>

<body class="bg-gray-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded shadow p-6">
        <h2 class="text-xl font-bold mb-4 text-yellow-700">Test Validation Fiche Réparation</h2>
        <form action="valider_fiche_reparation.php" method="get" class="space-y-4">
            <div>
                <label for="num_fiche" class="block text-sm font-medium text-gray-700">Numéro de fiche</label>
                <input type="text" id="num_fiche" name="num_fiche" required class="mt-1 block w-full border rounded px-3 py-2">
            </div>
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded font-bold">
                Valider la fiche
            </button>
        </form>
    </div>
</body>

</html>
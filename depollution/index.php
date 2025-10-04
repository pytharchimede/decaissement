<?php
// Front controller racine pour éviter l'index de répertoire Apache
// Redirige vers l'interface opérateur 1 (mobile) ou admin selon paramètre.
$target = 'public/index.php';
if (isset($_GET['role'])) {
    switch ($_GET['role']) {
        case 'admin':
            $target = 'public/admin.php';
            break;
        case 'op1':
            $target = 'public/operateur1.php';
            break;
        case 'op2':
            $target = 'public/operateur2.php';
            break;
    }
}
header('Location: ' . $target);
exit;

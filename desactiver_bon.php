<?php
// Script simple pour désactiver un bon via formulaire POST depuis l'UI (recap_carburant)
// Attend: id (id de la ligne) OU code_bon; optionnel: motif
// Redirige vers recap_carburant.php avec mêmes filtres si fournis

require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssenceRepository.php';

try {
    $repo = new DemandeEssenceRepository(Database::getConnection());
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $codeBon = isset($_POST['code_bon']) ? trim((string)$_POST['code_bon']) : '';
    $motif = isset($_POST['motif']) ? trim((string)$_POST['motif']) : '';

    if ($codeBon === '' && $id > 0) {
        $codeBon = $repo->resolveCodeBonById($id);
    }

    if ($codeBon !== '') {
        $repo->desactiverParCodeBon($codeBon, $motif !== '' ? $motif : null);
    }
} catch (Throwable $e) {
    // journaliser si besoin, mais ne pas interrompre la redirection
}

// Redirection simple
$q = $_SERVER['HTTP_REFERER'] ?? 'recap_carburant.php';
header('Location: ' . $q);
exit;

<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['companyName']) && !empty($_POST['companyName'])) {
        $_SESSION['companyName'] = $_POST['companyName'];
        echo json_encode(['success' => true, 'message' => 'Vous avez selectionne ' . $_SESSION['companyName'] . ' ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Le nom de l\'entreprise est requis.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}

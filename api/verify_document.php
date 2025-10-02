<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../model/DocumentVerifier.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }
    $templateKey = trim($_POST['template'] ?? '');
    if ($templateKey === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Paramètre template manquant']);
        exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Fichier manquant']);
        exit;
    }
    $tmp = $_FILES['file']['tmp_name'];
    $verifier = new DocumentVerifier();
    $res = $verifier->verify($templateKey, $tmp, []);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

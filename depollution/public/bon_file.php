<?php
// Serveur de fichiers pour les bons (photo/pdf) depuis storage/bons
// Usage: bon_file.php?id=123

require_once __DIR__ . '/../../model/Database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Paramètre id invalide';
    exit;
}

$pdo = Database::getConnection();
$st = $pdo->prepare('SELECT fichier_path FROM depollution_bon_sortie WHERE id=?');
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['fichier_path'])) {
    http_response_code(404);
    echo 'Fichier introuvable';
    exit;
}

$path = $row['fichier_path'];
// Sécurité: forcer que le chemin soit bien dans le dossier storage/bons
$storageDir = realpath(__DIR__ . '/../storage/bons');
$fileReal = realpath($path);
if ($storageDir === false || $fileReal === false || strpos($fileReal, $storageDir) !== 0 || !is_file($fileReal)) {
    http_response_code(404);
    echo 'Fichier non accessible';
    exit;
}

// Déterminer le MIME
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $det = finfo_file($finfo, $fileReal);
        if ($det) $mime = $det;
        finfo_close($finfo);
    }
}
// Forcer quelques cas courants
$ext = strtolower(pathinfo($fileReal, PATHINFO_EXTENSION));
if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
elseif ($ext === 'png') $mime = 'image/png';
elseif ($ext === 'gif') $mime = 'image/gif';
elseif ($ext === 'pdf') $mime = 'application/pdf';

// En-têtes
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fileReal));
header('Cache-Control: public, max-age=86400');
header('Accept-Ranges: bytes');

// Lecture
readfile($fileReal);
exit;

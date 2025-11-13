<?php
// Serveur de fichiers pour les bons (photo/pdf) depuis storage/bons
// Usage: bon_file.php?id=123 ou bon_file.php?bon_id=123

require_once __DIR__ . '/../../model/Database.php';

$id = 0;
if (isset($_GET['id'])) $id = (int)$_GET['id'];
elseif (isset($_GET['bon_id'])) $id = (int)$_GET['bon_id'];
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
// Résoudre correctement les chemins relatifs stockés (ex: "storage/bons/xxx")
$fileReal = false;
if ($path) {
    // Si chemin absolu
    if (preg_match('#^([a-zA-Z]:\\\\|/|\\\\)#', $path)) {
        $fileReal = realpath($path);
    }
    // Essai relatif par rapport au dossier public
    if ($fileReal === false) {
        $cand = realpath(__DIR__ . '/' . ltrim($path, '/\\'));
        if ($cand !== false) $fileReal = $cand;
    }
    // Essai relatif par rapport à la racine depollution (public/..)
    if ($fileReal === false) {
        $cand = realpath(__DIR__ . '/../' . ltrim($path, '/\\'));
        if ($cand !== false) $fileReal = $cand;
    }
}
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

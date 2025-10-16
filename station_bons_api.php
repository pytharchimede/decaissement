<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssenceRepository.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['station_ok']) || $_SESSION['station_ok'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$pdo = Database::getConnection();
$repo = new DemandeEssenceRepository($pdo);

$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit <= 0) $limit = 50;
if ($limit > 200) $limit = 200;

$filters = [
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'demandeur' => $_GET['demandeur'] ?? '',
    'motif' => $_GET['motif'] ?? '',
    'num_fiche' => $_GET['num_fiche'] ?? '',
    'code_bon' => $_GET['code_bon'] ?? '',
    'include_disabled' => (isset($_GET['include_disabled']) && $_GET['include_disabled'] == '1'),
    'receipt_status' => $_GET['receipt_status'] ?? '',
];

try {
    $stats = $repo->statsForStation($filters);
    $rows = $repo->searchForStationPaged($filters, $offset, $limit);
    echo json_encode([
        'ok' => true,
        'rows' => $rows,
        'stats' => $stats,
        'nextOffset' => $offset + count($rows),
        'hasMore' => count($rows) === $limit
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

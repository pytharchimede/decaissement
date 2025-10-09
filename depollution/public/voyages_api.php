<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../model/Database.php';
$pdo = (new Database())->getConnection();
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$where = [];
$params = [];
if ($date_debut) {
    $where[] = 'v.date_voyage >= :d1';
    $params[':d1'] = $date_debut;
}
if ($date_fin) {
    $where[] = 'v.date_voyage <= :d2';
    $params[':d2'] = $date_fin;
}
// multi prestataire (prestataire param multiple)
if (isset($_GET['prestataire'])) {
    $pList = (array)$_GET['prestataire'];
    if ($pList) {
        $parts = [];
        foreach ($pList as $i => $p) {
            $ph = ":p$i";
            $parts[] = $ph;
            $params[$ph] = $p;
        }
        $where[] = 'p.nom IN (' . implode(',', $parts) . ')';
    }
}
if (!empty($_GET['chauffeur'])) {
    $where[] = 'ch.nom = :chNom';
    $params[':chNom'] = $_GET['chauffeur'];
}
if (!empty($_GET['camion'])) {
    $where[] = 'c.matricule = :mat';
    $params[':mat'] = $_GET['camion'];
}
$includeCanceled = isset($_GET['include_canceled']) && $_GET['include_canceled'] === '1';
if (!$includeCanceled) {
    $where[] = "COALESCE(v.statut,'') <> 'ANNULE'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "SELECT v.*, p.nom AS prestataire, c.matricule, ch.nom AS chauffeur, ch.telephone, b.numero AS bon
        FROM depollution_voyage v
        LEFT JOIN depollution_prestataire p ON p.id=v.prestataire_id
        LEFT JOIN depollution_camion c ON c.id=v.camion_id
        LEFT JOIN depollution_chauffeur ch ON ch.id=v.chauffeur_id
        LEFT JOIN depollution_bon_sortie b ON b.id=v.bon_sortie_id
        $whereSql
        ORDER BY v.date_voyage ASC, v.id ASC
        LIMIT :off, :lim";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
}
$st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['rows' => $rows, 'offset' => $offset, 'count' => count($rows)]);

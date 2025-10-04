<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../model/Database.php';

session_start();
$pdo = (new Database())->getConnection();

// Support GET (legacy) & POST (avec charts_json + filters)
$inputMethod = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET';
$in = $inputMethod === 'POST' ? $_POST : $_GET;
if ($inputMethod === 'POST' && !empty($in['filters'])) {
    parse_str($in['filters'], $qs); // merge sans écraser POST explicite
    foreach ($qs as $k => $v) {
        if (!array_key_exists($k, $in)) $in[$k] = $v;
    }
}

$params = [];
$where = [];
if (!empty($in['date_debut'])) {
    $where[] = 'v.date_voyage >= :d1';
    $params[':d1'] = $in['date_debut'];
}
if (!empty($in['date_fin'])) {
    $where[] = 'v.date_voyage <= :d2';
    $params[':d2'] = $in['date_fin'];
}
// Multi prestataire
if (!empty($in['prestataire'])) {
    $prestList = (array)$in['prestataire'];
    $phs = [];
    foreach ($prestList as $i => $val) {
        $ph = ":pr_$i";
        $phs[] = $ph;
        $params[$ph] = $val;
    }
    if ($phs) $where[] = 'p.nom IN (' . implode(',', $phs) . ')';
}
if (!empty($in['chauffeur'])) {
    $where[] = 'ch.nom = :chauff';
    $params[':chauff'] = $in['chauffeur'];
}
if (!empty($in['camion'])) {
    $where[] = 'c.matricule = :camion';
    $params[':camion'] = $in['camion'];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT v.date_voyage,p.nom prestataire,ch.nom chauffeur,c.matricule,v.nombre_voyage,v.cubage,v.montant_origine,v.frais_route,v.carburant_montant,v.carburant_litre,v.reel_recu
        FROM depollution_voyage v
        LEFT JOIN depollution_prestataire p ON p.id=v.prestataire_id
        LEFT JOIN depollution_camion c ON c.id=v.camion_id
        LEFT JOIN depollution_chauffeur ch ON ch.id=v.chauffeur_id
        $whereSql
        ORDER BY v.date_voyage ASC, v.id ASC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Charts base64 envoyés
$charts = [];
if (!empty($in['charts_json'])) {
    $temp = json_decode($in['charts_json'], true);
    if (is_array($temp)) $charts = $temp;
}

$html = '<html><head><style>
body{font-family:DejaVu Sans, sans-serif;font-size:10px;color:#111;}
h1{font-size:16px;margin:0 0 8px;color:#d97706;}
.table{width:100%;border-collapse:collapse;}
.table th{background:#facc15;color:#000;padding:4px;font-size:9px;border:1px solid #e5e7eb;}
.table td{padding:3px;border:1px solid #e5e7eb;font-size:9px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}
.badge{background:#facc15;padding:2px 6px;border-radius:4px;font-size:8px;}
.footer{margin-top:8px;font-size:8px;color:#555;}
.charts{margin:10px 0 14px;display:flex;flex-wrap:wrap;gap:10px;}
.chart-box{flex:1 1 280px;border:1px solid #facc15;padding:4px;border-radius:6px;}
.chart-box img{max-width:100%;height:auto;display:block;}
</style></head><body>';
$html .= '<div class="header"><div><h1>Récap Voyages Carburant</h1><div class="badge">Export PDF</div></div><div style="text-align:right;font-size:9px;">Généré: ' . date('Y-m-d H:i') . '<br/>Total: ' . count($rows) . ' lignes</div></div>';
if ($charts) {
    $html .= '<div class="charts">';
    foreach ($charts as $id => $dataUrl) {
        if (strpos($dataUrl, 'data:image') === 0) {
            $html .= '<div class="chart-box"><div style="font-size:8px;font-weight:bold;margin-bottom:3px;">' . htmlspecialchars($id) . '</div><img src="' . $dataUrl . '" /></div>';
        }
    }
    $html .= '</div>';
}
$html .= '<table class="table"><thead><tr><th>Date</th><th>Prestataire</th><th>Chauffeur</th><th>Camion</th><th>Nb</th><th>Cubage</th><th>Montant</th><th>Frais</th><th>Carb</th><th>Litres</th><th>Réel</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $html .= '<tr>'
        . '<td>' . htmlspecialchars($r['date_voyage']) . '</td>'
        . '<td>' . htmlspecialchars($r['prestataire']) . '</td>'
        . '<td>' . htmlspecialchars($r['chauffeur']) . '</td>'
        . '<td>' . htmlspecialchars($r['matricule']) . '</td>'
        . '<td style="text-align:right">' . (int)$r['nombre_voyage'] . '</td>'
        . '<td style="text-align:right">' . number_format($r['cubage'], 2, ',', ' ') . '</td>'
        . '<td style="text-align:right">' . number_format($r['montant_origine'], 0, ',', ' ') . '</td>'
        . '<td style="text-align:right">' . number_format($r['frais_route'], 0, ',', ' ') . '</td>'
        . '<td style="text-align:right">' . number_format($r['carburant_montant'], 0, ',', ' ') . '</td>'
        . '<td style="text-align:right">' . number_format($r['carburant_litre'], 0, ',', ' ') . '</td>'
        . '<td style="text-align:right">' . number_format($r['reel_recu'], 0, ',', ' ') . '</td>'
        . '</tr>';
}
$html .= '</tbody></table><div class="footer">© ' . date('Y') . ' - Export interne</div></body></html>';

if (!class_exists('Dompdf\\Dompdf')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>Dompdf non installé</h3><p>Exécutez: <code>composer require dompdf/dompdf</code></p>';
    echo $html;
    exit;
}
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('recap_voyages.pdf', ['Attachment' => 1]);
exit;

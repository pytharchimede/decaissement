<?php
require_once __DIR__ . '/model/Database.php';

$pdo = Database::getConnection();

// Récupération des filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';

$conditions = [];
$params = [];
$scope = $_GET['scope'] ?? '';

// Filtre spécifique Dotation 50 l/j purge
$vehiculeFilter = "Dotation carburant (50 l/j) purge";
$conditions[] = "vehicule LIKE :vehiculeFilter";
$params[':vehiculeFilter'] = $vehiculeFilter . '%';
// Exclure les bons désactivés
$conditions[] = "(desactive IS NULL OR desactive = 0)";

// Si scope=batch, restreindre aux num_fiche issus du SQL batch en session
session_start();
if ($scope === 'batch' && !empty($_SESSION['batch_custom_sql']) && is_string($_SESSION['batch_custom_sql'])) {
    $batchSql = $_SESSION['batch_custom_sql'];
    $body = trim(rtrim($batchSql, ";\r\n\t "));
    $isSelect = stripos($body, 'select') === 0 && stripos(strtolower($body), ' from fiche') !== false;
    if ($isSelect) {
        try {
            $pdoF = Database::getConnection();
            $stmtF = $pdoF->query($body);
            $ficheRows = $stmtF ? $stmtF->fetchAll(PDO::FETCH_ASSOC) : [];
            $nums = [];
            foreach ($ficheRows as $fr) {
                if (isset($fr['num_fiche'])) $nums[] = (string)$fr['num_fiche'];
            }
            $nums = array_values(array_unique(array_filter($nums, fn($v) => $v !== '')));
            if (!empty($nums)) {
                $ph = [];
                foreach ($nums as $i => $n) {
                    $k = ":nf$i";
                    $ph[] = $k;
                    $params[$k] = $n;
                }
                $conditions[] = 'num_fiche IN (' . implode(',', $ph) . ')';
            } else {
                $conditions[] = '1=0';
            }
        } catch (Throwable $e) {
        }
    }
}

if ($date_debut) {
    $conditions[] = "date_demande >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if ($date_fin) {
    $conditions[] = "date_demande <= :date_fin";
    $params[':date_fin'] = $date_fin;
}
if ($demandeur) {
    $conditions[] = "nom_beneficiaire LIKE :demandeur";
    $params[':demandeur'] = "%$demandeur%";
}
if ($motif) {
    $conditions[] = "motif LIKE :motif";
    $params[':motif'] = "%$motif%";
}
if ($num_fiche) {
    $conditions[] = "num_fiche = :num_fiche";
    $params[':num_fiche'] = $num_fiche;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql = "SELECT e.code_bon, e.num_fiche, e.date_demande, e.nom_beneficiaire, e.vehicule, e.quantite, e.montant, e.motif, f.precision_fiche 
    FROM demande_essence e 
    LEFT JOIN fiche f ON f.num_fiche = e.num_fiche 
    $where GROUP BY e.id ORDER BY e.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=recap_carburant.csv');
$output = fopen('php://output', 'w');

// BOM UTF-8 pour Excel
fwrite($output, chr(239) . chr(187) . chr(191));

// Entêtes
fputcsv($output, [
    'Code bon',
    'N° fiche',
    'Date',
    'Bénéficiaire',
    'Chauffeur',
    'Matricule',
    'Quantité m3',
    'Voyages (équiv.)',
    'Litres (équiv.)',
    'Frais route',
    'Solde',
    'Montant',
    'Motif'
], ';');

// Helpers parse
function parse_fields($motif, $precision)
{
    $t = trim(implode("\n", array_filter([(string)$motif, (string)$precision])));
    $out = ['nom' => '', 'matricule' => '', 'frais' => '', 'solde' => '', 'quantite' => ''];
    if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) {
        $out['nom'] = trim($m[1]);
    }
    if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) {
        $out['matricule'] = trim($m[1]);
    }
    if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)/mi', $t, $m)) {
        $out['quantite'] = str_replace('.', ',', $m[1]);
    }
    if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $out['frais'] = preg_replace('/\D+/', '', $m[1]);
    }
    if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) {
        $out['solde'] = preg_replace('/\D+/', '', $m[1]);
    }
    return $out;
}

// Constantes métier
$basePerTrip = 33750; // FCFA par voyage (50 L)
$litersPerTrip = 50;

foreach ($rows as $r) {
    $pf = parse_fields($r['motif'] ?? '', $r['precision_fiche'] ?? '');
    $m = isset($r['montant']) ? (float)$r['montant'] : 0.0;
    $trEq = $m > 0 ? ($m / $basePerTrip) : 0;
    $trEqInt = (int)round($trEq);
    $litEq = $trEqInt * $litersPerTrip;
    fputcsv($output, [
        $r['code_bon'],
        $r['num_fiche'],
        date('d/m/Y H:i', strtotime($r['date_demande'])),
        $r['nom_beneficiaire'],
        $pf['nom'],
        $pf['matricule'],
        $pf['quantite'] !== '' ? $pf['quantite'] : str_replace('.', ',', (string)($r['quantite'] ?? '0')),
        $trEqInt,
        $litEq,
        $pf['frais'],
        $pf['solde'],
        (string)$r['montant'],
        $r['motif'],
    ], ';');
}

fclose($output);
exit;

<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssence.php';
require __DIR__ . '/vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Récupération des filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';
$filtre_fournisseur = $_GET['fournisseur'] ?? '';

$pdo = (new Database())->getConnection();
$conditions = [];
$params = [];
$scope = $_GET['scope'] ?? '';

// Filtre spécifique chantier: Dotation carburant (50 l/j) purge
$vehiculeFilter = "Dotation carburant (50 l/j) purge";
$conditions[] = "e.vehicule LIKE :vehiculeFilter";
$params[':vehiculeFilter'] = $vehiculeFilter . '%';
// Exclure les bons désactivés
$conditions[] = "(e.desactive IS NULL OR e.desactive = 0)";

// Si scope=batch, restreindre aux num_fiche issus du SQL de la page batch (en session)
if ($scope === 'batch' && !empty($_SESSION['batch_custom_sql']) && is_string($_SESSION['batch_custom_sql'])) {
    $body = trim(rtrim($_SESSION['batch_custom_sql'], ";\r\n\t "));
    $isSelect = stripos($body, 'select') === 0 && stripos(strtolower($body), ' from fiche') !== false;
    if ($isSelect) {
        try {
            $pdoF = (new Database())->getConnection();
            $rows = $pdoF->query($body)->fetchAll(PDO::FETCH_ASSOC);
            $nums = [];
            foreach ($rows as $r) if (isset($r['num_fiche'])) $nums[] = (string)$r['num_fiche'];
            $nums = array_values(array_unique(array_filter($nums, fn($v) => $v !== '')));
            if ($nums) {
                $ph = [];
                foreach ($nums as $i => $n) {
                    $k = ":nf$i";
                    $ph[] = $k;
                    $params[$k] = $n;
                }
                $conditions[] = 'e.num_fiche IN (' . implode(',', $ph) . ')';
            } else {
                $conditions[] = '1=0';
            }
        } catch (Throwable $e) {
        }
    }
}

if ($date_debut) {
    $conditions[] = "e.date_demande >= :date_debut";
    $params[':date_debut'] = $date_debut;
}
if ($date_fin) {
    $conditions[] = "e.date_demande <= :date_fin";
    $params[':date_fin'] = $date_fin;
}
if ($demandeur) {
    $conditions[] = "e.nom_beneficiaire LIKE :demandeur";
    $params[':demandeur'] = "%$demandeur%";
}
if ($motif) {
    $conditions[] = "e.motif LIKE :motif";
    $params[':motif'] = "%$motif%";
}
if ($num_fiche) {
    $conditions[] = "e.num_fiche = :num_fiche";
    $params[':num_fiche'] = $num_fiche;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
// Jointure pour accéder à precision_fiche
$sql = "SELECT e.*, f.precision_fiche FROM demande_essence e LEFT JOIN fiche f ON f.num_fiche = e.num_fiche $where GROUP BY e.id ORDER BY e.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helpers parsing depuis motif + precision
function xl_parse_fields(?string $motif, ?string $precision): array
{
    $out = ['nom' => '', 'matricule' => '', 'telephone' => '', 'quantite' => null, 'frais' => null, 'solde' => null];
    $t = trim(implode("\n", array_filter([(string)($motif ?? ''), (string)($precision ?? '')])));
    if ($t === '') return $out;
    if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) $out['nom'] = trim($m[1]);
    if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) $out['matricule'] = trim($m[1]);
    if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)/mi', $t, $m)) $out['quantite'] = (float)str_replace(',', '.', $m[1]);
    if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['frais'] = (int)preg_replace('/\D+/', '', $m[1]);
    if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['solde'] = (int)preg_replace('/\D+/', '', $m[1]);
    return $out;
}

// Création du fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- EN-TÊTE DESIGN ---
$row = 1;
$sheet->mergeCells("A$row:M$row");
$sheet->setCellValue("A$row", "Liste des Bons Carburant");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('78350F');
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FACC15');
$row++;

// Paramètres de recherche
$paramText = [];
if ($date_debut || $date_fin) {
    $txt = "Période : ";
    if ($date_debut) $txt .= date('d/m/Y', strtotime($date_debut));
    if ($date_debut && $date_fin) $txt .= " au ";
    if ($date_fin) $txt .= date('d/m/Y', strtotime($date_fin));
    $paramText[] = $txt;
}
if ($demandeur) $paramText[] = "Demandeur : $demandeur";
if ($motif) $paramText[] = "Motif : $motif";
if ($num_fiche) $paramText[] = "N° Fiche : $num_fiche";
if ($paramText) {
    $sheet->mergeCells("A$row:M$row");
    $sheet->setCellValue("A$row", implode("   |   ", $paramText));
    $sheet->getStyle("A$row")->getFont()->setItalic(true)->setSize(10);
    $row++;
}
$sheet->mergeCells("A$row:M$row");
$sheet->setCellValue("A$row", "Date d'export : " . date('d/m/Y H:i'));
$sheet->getStyle("A$row")->getFont()->setSize(10)->getColor()->setRGB('374151');
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$row += 2;

// --- COLONNES ---
$headers = [
    'N° Bon',
    'N° Fiche',
    'Date',
    'Bénéficiaire',
    'Chauffeur',
    'Matricule',
    'Fournisseur',
    'Quantité m3',
    'Voyages (équiv.)',
    'Litres (équiv.)',
    'Frais route',
    'Solde',
    'Montant',
];
$sheet->fromArray($headers, null, "A$row");
$sheet->getStyle("A$row:M$row")->getFont()->setBold(true)->getColor()->setRGB('78350F');
$sheet->getStyle("A$row:M$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FACC15');
$sheet->getStyle("A$row:M$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// --- DONNÉES ---
$totalMontant = 0;
$totalQte = 0.0;
$totalTripsEq = 0;
$totalLitresEq = 0;
// Constantes métier
$basePerTrip = 33750; // FCFA par voyage
$litersPerTrip = 50;

// Mapping fournisseur
require_once __DIR__ . '/model/CamionFournisseurRepository.php';
$repo = new CamionFournisseurRepository($pdo);
$mapF = $repo->getAllMappings();
$norm = function ($m) {
    $m = strtoupper(trim((string)$m));
    return preg_replace('/\s+/', '', $m);
};

foreach ($demandes as $d) {
    $pf = xl_parse_fields($d['motif'] ?? '', $d['precision_fiche'] ?? '');
    $fourn = '';
    if (!empty($pf['matricule'])) {
        $k = $norm($pf['matricule']);
        if (isset($mapF[$k])) $fourn = $mapF[$k];
    }
    if ($filtre_fournisseur !== '' && strcasecmp($fourn, $filtre_fournisseur) !== 0) continue;
    $q = isset($d['quantite']) && $d['quantite'] !== '' && $d['quantite'] !== null ? (float)$d['quantite'] : (float)($pf['quantite'] ?? 0);
    $fr = isset($pf['frais']) ? (int)$pf['frais'] : null;
    $sd = isset($pf['solde']) ? (int)$pf['solde'] : null;
    $mval = (float)($d['montant'] ?? 0);
    $trEq = $mval > 0 ? ($mval / $basePerTrip) : 0;
    $trEqInt = (int)round($trEq);
    $litEq = $trEqInt * $litersPerTrip;
    $sheet->fromArray([
        $d['code_bon'],
        $d['num_fiche'],
        date('d/m/Y', strtotime($d['date_demande'])),
        $d['nom_beneficiaire'],
        $pf['nom'],
        $pf['matricule'],
        $fourn,
        $q,
        $trEqInt,
        $litEq,
        $fr,
        $sd,
        (float)$d['montant'],
    ], null, "A$row");
    $totalMontant += (float)$d['montant'];
    $totalQte += (float)$q;
    $totalTripsEq += $trEqInt;
    $totalLitresEq += $litEq;
    $row++;
}

// --- TOTAL ---
// Totaux
$sheet->setCellValue("G$row", "Totaux");
$sheet->setCellValue("H$row", $totalQte);
$sheet->setCellValue("I$row", $totalTripsEq);
$sheet->setCellValue("J$row", $totalLitresEq);
$sheet->setCellValue("M$row", "=SUM(M" . ($row - count($demandes)) . ":M" . ($row - 1) . ")");
$sheet->getStyle("G$row:M$row")->getFont()->setBold(true)->getColor()->setRGB('78350F');
$sheet->getStyle("G$row:M$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FACC15');

// --- LARGEURS AUTOMATIQUES ---
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --- BORDURES ---
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => 'FFB45309'],
        ],
    ],
];
$sheet->getStyle("A" . ($row - count($demandes)) . ":M$row")->applyFromArray($styleArray);

// --- TÉLÉCHARGEMENT ---
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="recap_carburant.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

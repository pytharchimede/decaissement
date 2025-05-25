<?php
require_once 'model/Database.php';
require_once 'model/DemandeEssence.php';
require 'vendor/autoload.php'; // PhpSpreadsheet

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

$pdo = (new Database())->getConnection();
$conditions = [];
$params = [];

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
$sql = "SELECT * FROM demande_essence $where ORDER BY date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Création du fichier Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- EN-TÊTE DESIGN ---
$row = 1;
$sheet->mergeCells("A$row:G$row");
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
    $sheet->mergeCells("A$row:G$row");
    $sheet->setCellValue("A$row", implode("   |   ", $paramText));
    $sheet->getStyle("A$row")->getFont()->setItalic(true)->setSize(10);
    $row++;
}
$sheet->mergeCells("A$row:G$row");
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
    'Motif',
    'Montant',
    'Statut'
];
$sheet->fromArray($headers, null, "A$row");
$sheet->getStyle("A$row:G$row")->getFont()->setBold(true)->getColor()->setRGB('78350F');
$sheet->getStyle("A$row:G$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FACC15');
$sheet->getStyle("A$row:G$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// --- DONNÉES ---
$totalMontant = 0;
foreach ($demandes as $d) {
    $statut = (empty($d['desactive']) || $d['desactive'] == 0) ? 'Actif' : 'Désactivé';
    // Motif limité à 30 caractères
    $motif = $d['motif'];
    $maxMotifLen = 30;
    if (mb_strlen($motif) > $maxMotifLen) {
        $motif = mb_substr($motif, 0, $maxMotifLen - 3) . '...';
    }
    $sheet->fromArray([
        $d['code_bon'],
        $d['num_fiche'],
        date('d/m/Y', strtotime($d['date_demande'])),
        $d['nom_beneficiaire'],
        $motif,
        $d['montant'],
        $statut
    ], null, "A$row");
    $totalMontant += floatval($d['montant']);
    $row++;
}

// --- TOTAL ---
$sheet->setCellValue("E$row", "Total");
$sheet->setCellValue("F$row", "=SUM(F" . ($row - count($demandes)) . ":F" . ($row - 1) . ")");
$sheet->getStyle("E$row:F$row")->getFont()->setBold(true)->getColor()->setRGB('78350F');
$sheet->getStyle("E$row:F$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FACC15');

// --- LARGEURS AUTOMATIQUES ---
foreach (range('A', 'G') as $col) {
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
$sheet->getStyle("A" . ($row - count($demandes)) . ":G$row")->applyFromArray($styleArray);

// --- TÉLÉCHARGEMENT ---
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="recap_carburant.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

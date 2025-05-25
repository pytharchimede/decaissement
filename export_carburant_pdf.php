<?php

require_once 'model/Database.php';
require_once 'model/DemandeEssence.php';
require('fpdf/fpdf.php');

// Charte graphique + Arial
class PDF extends FPDF
{
    function Header()
    {
        $this->SetFillColor(250, 204, 21);
        $this->Rect(0, 0, 210, 20, 'F');
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(120, 53, 15);
        $titre = mb_convert_encoding('Liste des Bons Carburant', 'ISO-8859-1', 'UTF-8');
        $this->Cell(0, 12, $titre, 0, 1, 'C');
        $this->Ln(2);
    }
}

$pdf = new PDF();
$pdf->SetFont('Arial', '', 10);
$pdf->AddPage();

// Récupération des données filtrées
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


// Filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';

// Affichage des paramètres de recherche
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(55, 65, 81);
$paramText = '';
if ($date_debut || $date_fin) {
    $paramText .= 'Période : ';
    if ($date_debut) $paramText .= date('d/m/Y', strtotime($date_debut));
    if ($date_debut && $date_fin) $paramText .= ' au ';
    if ($date_fin) $paramText .= date('d/m/Y', strtotime($date_fin));
    $paramText .= '   ';
}
if ($demandeur) $paramText .= 'Demandeur : ' . $demandeur . '   ';
if ($motif) $paramText .= 'Motif : ' . $motif . '   ';
if ($num_fiche) $paramText .= 'N° Fiche : ' . $num_fiche . '   ';
if ($paramText) {
    $pdf->MultiCell(0, 7, mb_convert_encoding(trim($paramText), 'ISO-8859-1', 'UTF-8'));
}
$pdf->Cell(0, 8, mb_convert_encoding('Date d\'export : ' . date('d/m/Y H:i'), 'ISO-8859-1', 'UTF-8'), 0, 1, 'R');
$pdf->Ln(2);

// En-têtes
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(250, 204, 21);
$pdf->SetTextColor(120, 53, 15);
$pdf->Cell(28, 7, mb_convert_encoding('N° Bon', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(22, 7, mb_convert_encoding('N° Fiche', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(22, 7, 'Date', 1, 0, 'C', true);
$pdf->Cell(38, 7, mb_convert_encoding('Bénéficiaire', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(38, 7, mb_convert_encoding('Motif', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(22, 7, 'Montant', 1, 0, 'C', true);
$pdf->Cell(18, 7, 'Statut', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(55, 65, 81);
$totalMontant = 0;
foreach ($demandes as $d) {
    $statut = (empty($d['desactive']) || $d['desactive'] == 0) ? 'Actif' : 'Désactivé';

    // Préparation du motif (max 2 lignes, points de suspension si trop long)
    $motif = mb_convert_encoding($d['motif'], 'ISO-8859-1', 'UTF-8');
    $maxMotifLen = 40; // Ajuste selon la largeur de ta colonne
    if (mb_strlen($motif) > $maxMotifLen) {
        $motif = mb_substr($motif, 0, $maxMotifLen - 3) . '...';
    }

    // Calcul de la hauteur (1 ou 2 lignes max)
    $lineHeight = 6;
    $nbLines = ceil($pdf->GetStringWidth($motif) / 38); // 38 = largeur colonne motif
    $nbLines = min($nbLines, 2);
    $cellHeight = $lineHeight * $nbLines;

    // Sauvegarde la position courante
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // Colonnes fixes
    $pdf->Cell(28, $cellHeight, mb_convert_encoding($d['code_bon'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(22, $cellHeight, mb_convert_encoding($d['num_fiche'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(22, $cellHeight, date('d/m/Y', strtotime($d['date_demande'])), 1, 0);
    $pdf->Cell(38, $cellHeight, mb_convert_encoding($d['nom_beneficiaire'], 'ISO-8859-1', 'UTF-8'), 1, 0);

    // Motif (MultiCell)
    $pdf->SetXY($x + 28 + 22 + 22 + 38, $y);
    $pdf->MultiCell(38, $lineHeight, $motif, 0, 'L');
    $pdf->SetXY($x + 28 + 22 + 22 + 38 + 38, $y);

    // Colonnes finales
    $pdf->Cell(22, $cellHeight, number_format($d['montant'], 0, ',', ' '), 1, 0, 'R');
    $pdf->Cell(18, $cellHeight, mb_convert_encoding($statut, 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');

    $totalMontant += floatval($d['montant']);
}

// Total
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(120, 53, 15);
$pdf->Cell(148, 7, mb_convert_encoding('Total', 'ISO-8859-1', 'UTF-8'), 1);
$pdf->Cell(22, 7, number_format($totalMontant, 0, ',', ' '), 1, 0, 'R');
$pdf->Cell(18, 7, '', 1, 1);

$pdf->Output('D', 'recap_carburant.pdf');
exit;

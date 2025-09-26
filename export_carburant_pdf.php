<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssence.php';
require __DIR__ . '/fpdf/fpdf.php';

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

// Filtres
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$demandeur = $_GET['demandeur'] ?? '';
$motif = $_GET['motif'] ?? '';
$num_fiche = $_GET['num_fiche'] ?? '';
$scope = $_GET['scope'] ?? '';

// Récupération des données filtrées
$pdo = (new Database())->getConnection();
$conditions = [];
$params = [];

// Filtre chantier
$vehiculeFilter = "Dotation carburant (50 l/j) purge";
$conditions[] = "e.vehicule LIKE :vehiculeFilter";
$params[':vehiculeFilter'] = $vehiculeFilter . '%';

// Scope batch → restriction num_fiche
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
$sql = "SELECT e.*, f.precision_fiche FROM demande_essence e LEFT JOIN fiche f ON f.num_fiche = e.num_fiche $where ORDER BY e.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Affichage des paramètres de recherche
$pdf = new PDF();
$pdf->SetFont('Arial', '', 9);
$pdf->AddPage();
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

// En-têtes (étendues)
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(250, 204, 21);
$pdf->SetTextColor(120, 53, 15);
$pdf->Cell(18, 7, mb_convert_encoding('N° Bon', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(16, 7, mb_convert_encoding('N° Fiche', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(16, 7, 'Date', 1, 0, 'C', true);
$pdf->Cell(26, 7, mb_convert_encoding('Bénéficiaire', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(26, 7, mb_convert_encoding('Chauffeur', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(20, 7, mb_convert_encoding('Matricule', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(12, 7, mb_convert_encoding('m³', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(16, 7, mb_convert_encoding('Frais', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(16, 7, mb_convert_encoding('Solde', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
$pdf->Cell(24, 7, 'Montant', 1, 1, 'C', true);

// Helpers parsing
function pdf_parse_fields(?string $motif, ?string $precision): array
{
    $out = ['nom' => '', 'matricule' => '', 'quantite' => 0.0, 'frais' => 0, 'solde' => 0];
    $t = trim(implode("\n", array_filter([(string)($motif ?? ''), (string)($precision ?? '')])));
    if ($t !== '') {
        if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) $out['nom'] = trim($m[1]);
        if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) $out['matricule'] = trim($m[1]);
        if (preg_match('/Quantit[ée]\s*charg[ée]e?\s*:\s*([0-9]+(?:[\.,][0-9]+)?)/mi', $t, $m)) $out['quantite'] = (float)str_replace(',', '.', $m[1]);
        if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['frais'] = (int)preg_replace('/\D+/', '', $m[1]);
        if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['solde'] = (int)preg_replace('/\D+/', '', $m[1]);
    }
    return $out;
}

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(55, 65, 81);
$totalMontant = 0;
$totalQte = 0.0;
foreach ($demandes as $d) {
    $pf = pdf_parse_fields($d['motif'] ?? '', $d['precision_fiche'] ?? '');
    $q = isset($d['quantite']) && $d['quantite'] !== '' && $d['quantite'] !== null ? (float)$d['quantite'] : (float)($pf['quantite'] ?? 0);
    $lineHeight = 6;
    $cellHeight = $lineHeight; // simple ligne par enregistrement

    $pdf->Cell(18, $cellHeight, mb_convert_encoding($d['code_bon'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(16, $cellHeight, mb_convert_encoding($d['num_fiche'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(16, $cellHeight, date('d/m/Y', strtotime($d['date_demande'])), 1, 0, 'C');
    $pdf->Cell(26, $cellHeight, mb_convert_encoding($d['nom_beneficiaire'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(26, $cellHeight, mb_convert_encoding($pf['nom'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(20, $cellHeight, mb_convert_encoding($pf['matricule'], 'ISO-8859-1', 'UTF-8'), 1, 0);
    $pdf->Cell(12, $cellHeight, rtrim(rtrim(number_format($q, 2, ',', ' '), '0'), ','), 1, 0, 'R');
    $pdf->Cell(16, $cellHeight, number_format((int)($pf['frais'] ?? 0), 0, ',', ' '), 1, 0, 'R');
    $pdf->Cell(16, $cellHeight, number_format((int)($pf['solde'] ?? 0), 0, ',', ' '), 1, 0, 'R');
    $pdf->Cell(24, $cellHeight, number_format((float)($d['montant'] ?? 0), 0, ',', ' '), 1, 1, 'R');

    $totalMontant += (float)($d['montant'] ?? 0);
    $totalQte += (float)$q;
}

// Total
// Totaux
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(120, 53, 15);
// Colonnes cumulées jusqu'à Matricule (18+16+16+26+26+20 = 122)
$pdf->Cell(122, 7, mb_convert_encoding('Totaux', 'ISO-8859-1', 'UTF-8'), 1);
$pdf->Cell(12, 7, rtrim(rtrim(number_format($totalQte, 2, ',', ' '), '0'), ','), 1, 0, 'R');
$pdf->Cell(16, 7, '', 1, 0);
$pdf->Cell(16, 7, '', 1, 0);
$pdf->Cell(24, 7, number_format($totalMontant, 0, ',', ' '), 1, 1, 'R');

$pdf->Output('D', 'recap_carburant.pdf');
exit;

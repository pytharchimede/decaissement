<?php
session_start();
require_once __DIR__ . '/model/Database.php';
require_once __DIR__ . '/model/DemandeEssence.php';
require __DIR__ . '/fpdf/fpdf.php';

// Charte graphique + Arial
class PDF extends FPDF
{
    // Helpers table multi-lignes
    protected $widths;
    protected $aligns;

    function Header()
    {
        $this->SetFillColor(250, 204, 21);
        $this->Rect(0, 0, 210, 20, 'F');
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(120, 53, 15);
        $titre = mb_convert_encoding('Récap Carburant — Transport', 'ISO-8859-1', 'UTF-8');
        $this->Cell(0, 12, $titre, 0, 1, 'C');
        $this->Ln(2);
    }

    function SetWidths($w)
    {
        // Tableau des largeurs de colonnes
        $this->widths = $w;
    }

    function SetAligns($a)
    {
        // Tableau des alignements de colonnes
        $this->aligns = $a;
    }

    function Row($data, $lineHeight = 5)
    {
        // Calcule le nb de lignes max nécessaire
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = $lineHeight * $nb;
        // Saut de page si nécessaire
        $this->CheckPageBreak($h);
        // Dessine les cellules
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            // Bordure
            $this->Rect($x, $y, $w, $h);
            // Texte
            $this->MultiCell($w, $lineHeight, $data[$i], 0, $a);
            // Retour au début de la ligne, + largeur
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    function NbLines($w, $txt)
    {
        // Calcule le nombre de lignes qu'occupe un MultiCell de largeur w
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * 0.5) * 1000 / $this->FontSize; // marges internes
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) $i++;
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // Mini charts (simples) pour PDF
    function drawLineChart($x, $y, $w, $h, $labels, $values, $color = [202, 138, 4])
    {
        if (empty($values)) return;
        $this->SetDrawColor(200, 200, 200);
        $this->Rect($x, $y, $w, $h);
        $min = 0;
        $max = max($values);
        if ($max <= 0) $max = 1;
        $n = count($values);
        $stepX = $n > 1 ? $w / ($n - 1) : 0;
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $x + $stepX * $i;
            $x2 = $x + $stepX * ($i + 1);
            $y1 = $y + $h - ($values[$i] - $min) / ($max - $min) * $h;
            $y2 = $y + $h - ($values[$i + 1] - $min) / ($max - $min) * $h;
            $this->Line($x1, $y1, $x2, $y2);
        }
    }

    function drawBarChart($x, $y, $w, $h, $labels, $values, $color = [250, 204, 21])
    {
        if (empty($values)) return;
        $this->SetDrawColor(200, 200, 200);
        $this->Rect($x, $y, $w, $h);
        $max = max($values);
        if ($max <= 0) $max = 1;
        $n = count($values);
        $barW = $n > 0 ? ($w / max(1, $n * 1.5)) : 0; // espacement
        $gap = $barW * 0.5;
        $this->SetFillColor($color[0], $color[1], $color[2]);
        for ($i = 0; $i < $n; $i++) {
            $bh = ($values[$i] / $max) * ($h - 2);
            $bx = $x + $gap + ($barW + $gap) * $i;
            $by = $y + $h - $bh;
            $this->Rect($bx, $by, $barW, $bh, 'F');
        }
    }

    function drawHBarChart($x, $y, $w, $h, $labels, $values, $color = [134, 239, 172])
    {
        if (empty($values)) return;
        $n = count($values);
        $rowH = $h / max(1, $n);
        $max = max($values);
        if ($max <= 0) $max = 1;
        for ($i = 0; $i < $n; $i++) {
            $vy = $y + $rowH * $i + 1;
            $vh = $rowH - 2;
            $vw = ($values[$i] / $max) * ($w - 40);
            $this->SetFillColor($color[0], $color[1], $color[2]);
            $this->Rect($x + 40, $vy, $vw, $vh, 'F');
            // label
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(55, 65, 81);
            $this->SetXY($x + 1, $vy);
            $this->MultiCell(38, 3.5, mb_convert_encoding($labels[$i], 'ISO-8859-1', 'UTF-8'));
        }
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

// Agrégations pour KPI & charts
$totalBons = count($demandes);
$totalMontant = 0;
$totalQte = 0.0;
$sumFraisRoute = 0;
$sumSolde = 0;
$totalLitresCarburant = $totalBons * 50;
$byDayMontant = [];
$byDayQuantite = [];
$byBenefMontant = [];

// Parsing robuste (même logique que sur la page)
function pdf_parse_fields(?string $motif, ?string $precision): array
{
    $out = ['nom' => '', 'matricule' => '', 'quantite' => 0.0, 'frais' => 0, 'solde' => 0];
    $t = trim(implode("\n", array_filter([(string)($motif ?? ''), (string)($precision ?? '')])));
    if ($t !== '') {
        if (preg_match('/Nom\s*:\s*(.+)$/mi', $t, $m)) $out['nom'] = trim($m[1]);
        if (preg_match('/Matricule\s*:\s*([^\r\n]+)/mi', $t, $m)) $out['matricule'] = trim($m[1]);
        // quantité tolérante
        if (preg_match('/Quantit[ée][^\r\n:]*:?\s*([0-9]+(?:[\.,][0-9]+)?)\s*(?:m3|m³|m[èe]tres?\s*cubes?)?/mi', $t, $m)) {
            $out['quantite'] = (float)str_replace(',', '.', $m[1]);
        } elseif (preg_match('/([0-9]+(?:[\.,][0-9]+)?)\s*(?:m3|m³|m[èe]tres?\s*cubes?)\b/mi', $t, $m)) {
            $out['quantite'] = (float)str_replace(',', '.', $m[1]);
        }
        if (preg_match('/Frais\s*de\s*route\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['frais'] = (int)preg_replace('/\D+/', '', $m[1]);
        if (preg_match('/Solde\s*:\s*([0-9\s\.,]+)/mi', $t, $m)) $out['solde'] = (int)preg_replace('/\D+/', '', $m[1]);
    }
    return $out;
}

foreach ($demandes as $d) {
    $pf = pdf_parse_fields($d['motif'] ?? '', $d['precision_fiche'] ?? '');
    $qDb = $d['quantite'] ?? null;
    $q = (is_numeric($qDb) && (float)$qDb > 0) ? (float)$qDb : (float)($pf['quantite'] ?? 0);
    $m = (float)($d['montant'] ?? 0);
    $totalMontant += $m;
    $totalQte += $q;
    $sumFraisRoute += (int)($pf['frais'] ?? 0);
    $sumSolde += (int)($pf['solde'] ?? 0);
    $day = date('Y-m-d', strtotime($d['date_demande']));
    if (!isset($byDayMontant[$day])) $byDayMontant[$day] = 0;
    if (!isset($byDayQuantite[$day])) $byDayQuantite[$day] = 0;
    $byDayMontant[$day] += $m;
    $byDayQuantite[$day] += $q;
    $bn = trim((string)($d['nom_beneficiaire'] ?? 'Inconnu'));
    if (!isset($byBenefMontant[$bn])) $byBenefMontant[$bn] = 0;
    $byBenefMontant[$bn] += $m;
}
ksort($byDayMontant);
ksort($byDayQuantite);
arsort($byBenefMontant);
$topBenefLabels = array_keys(array_slice($byBenefMontant, 0, 7, true));
$topBenefValues = array_values(array_slice($byBenefMontant, 0, 7, true));
$chartDays = array_keys($byDayMontant);
$chartMontants = array_values($byDayMontant);
$chartQuantites = array_values($byDayQuantite);

// KPIs
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(120, 53, 15);
$pdf->Cell(0, 7, mb_convert_encoding("Dotation carburant (50 l/j) purge — Récap", 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(55, 65, 81);
$pdf->Cell(48, 6, mb_convert_encoding('Bons: ' . number_format($totalBons, 0, ',', ' '), 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(72, 6, mb_convert_encoding('Montant total: ' . number_format($totalMontant, 0, ',', ' ') . ' FCFA', 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(70, 6, mb_convert_encoding('Carburant estimé: ' . number_format($totalLitresCarburant, 0, ',', ' ') . ' L', 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Cell(48, 6, mb_convert_encoding('m³ transportés: ' . rtrim(rtrim(number_format($totalQte, 2, ',', ' '), '0'), ','), 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(72, 6, mb_convert_encoding('Frais de route: ' . number_format($sumFraisRoute, 0, ',', ' ') . ' FCFA', 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(70, 6, mb_convert_encoding('Solde: ' . number_format($sumSolde, 0, ',', ' ') . ' FCFA', 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Ln(2);

// Graphiques (simples)
$x = 10;
$w = 190;
$h = 45;
$gapY = 6;
$y = $pdf->GetY();
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(55, 65, 81);
$pdf->Cell(0, 5, mb_convert_encoding('Montants par jour', 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->drawLineChart($x, $pdf->GetY(), $w, $h, $chartDays, $chartMontants, [202, 138, 4]);
$pdf->Ln($h + $gapY);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, mb_convert_encoding('Top bénéficiaires (montants)', 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->drawHBarChart($x, $pdf->GetY(), $w, 40, $topBenefLabels, $topBenefValues, [134, 239, 172]);
$pdf->Ln(42 + $gapY);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(0, 5, mb_convert_encoding('Quantités par jour (m³)', 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->drawBarChart($x, $pdf->GetY(), $w, $h, $chartDays, $chartQuantites, [250, 204, 21]);
$pdf->Ln($h + $gapY);

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

// Tableau détaillé avec retours à la ligne
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(55, 65, 81);
$pdf->SetWidths([18, 16, 16, 26, 26, 20, 12, 16, 16, 24]);
$pdf->SetAligns(['L', 'L', 'C', 'L', 'L', 'L', 'R', 'R', 'R', 'R']);
foreach ($demandes as $d) {
    $pf = pdf_parse_fields($d['motif'] ?? '', $d['precision_fiche'] ?? '');
    $qDb = $d['quantite'] ?? null;
    $q = (is_numeric($qDb) && (float)$qDb > 0) ? (float)$qDb : (float)($pf['quantite'] ?? 0);
    $row = [
        mb_convert_encoding((string)$d['code_bon'], 'ISO-8859-1', 'UTF-8'),
        mb_convert_encoding((string)$d['num_fiche'], 'ISO-8859-1', 'UTF-8'),
        date('d/m/Y', strtotime($d['date_demande'])),
        mb_convert_encoding((string)$d['nom_beneficiaire'], 'ISO-8859-1', 'UTF-8'),
        mb_convert_encoding((string)$pf['nom'], 'ISO-8859-1', 'UTF-8'),
        mb_convert_encoding((string)$pf['matricule'], 'ISO-8859-1', 'UTF-8'),
        rtrim(rtrim(number_format($q, 2, ',', ' '), '0'), ','),
        number_format((int)($pf['frais'] ?? 0), 0, ',', ' '),
        number_format((int)($pf['solde'] ?? 0), 0, ',', ' '),
        number_format((float)($d['montant'] ?? 0), 0, ',', ' '),
    ];
    $pdf->Row($row, 5);
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

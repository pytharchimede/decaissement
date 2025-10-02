<?php
// Vérifie si un fichier a été uploadé
if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filePath = $uploadDir . basename($_FILES['receipt']['name']);
    move_uploaded_file($_FILES['receipt']['tmp_name'], $filePath);

    // Exécuter Tesseract OCR (assurez-vous que tesseract est installé et dans le PATH)
    $outputFile = $filePath . "_ocr";
    $cmd = "tesseract " . escapeshellarg($filePath) . " " . escapeshellarg($outputFile) . " -l fra";
    exec($cmd);

    // Lire le texte OCR
    $ocrText = file_get_contents($outputFile . ".txt");

    // Tableau de résultats
    $data = [
        "receipt_number" => null,
        "amount" => null,
        "date" => null,
        "signature_present" => false,
        "raw_text" => $ocrText // utile pour debug
    ];

    // Numéro reçu (5 ou 6 chiffres)
    if (preg_match("/\b\d{5,6}\b/", $ocrText, $matches)) {
        $data["receipt_number"] = $matches[0];
    }

    // Montant (ex: 25 000 FCFA ou 25000 F)
    if (preg_match("/(\d[\d\s]+)\s?(FCFA|F)/i", $ocrText, $matches)) {
        $data["amount"] = trim($matches[0]);
    }

    // Date (formats 12/09/2023 ou 1-2-23 etc.)
    if (preg_match("/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/", $ocrText, $matches)) {
        $data["date"] = $matches[0];
    }

    // Signature ou cachet
    if (stripos($ocrText, "signature") !== false || stripos($ocrText, "cachet") !== false) {
        $data["signature_present"] = true;
    }

    // Résultat en JSON
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Formulaire d'upload si aucun fichier n'est encore envoyé
?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <title>Test OCR Reçu</title>
    </head>

    <body>
        <h2>Test OCR/IA sur reçu</h2>
        <form method="post" enctype="multipart/form-data">
            <label for="receipt">Image du reçu :</label>
            <input type="file" name="receipt" id="receipt" required>
            <button type="submit">Analyser</button>
        </form>
    </body>

    </html>
<?php
}

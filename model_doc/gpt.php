<?php
require_once __DIR__ . '/../model/Config.php';
require_once __DIR__ . '/../model/OcrReceiptAnalyzer.php';

class OpenAiClient
{
  private string $apiKey;
  private string $baseUrl;
  private string $model;

  public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $model = null)
  {
    $this->apiKey = $apiKey ?? AppConfig::openAiApiKey();
    $this->baseUrl = rtrim($baseUrl ?? AppConfig::openAiBaseUrl(), '/');
    $this->model = $model ?? AppConfig::openAiModel();
  }

  public function chat(array $messages, array $opts = []): array
  {
    if ($this->apiKey === '') {
      return ['ok' => false, 'error' => 'OPENAI_API_KEY manquante'];
    }
    $payload = [
      'model' => $this->model,
      'messages' => $messages,
      'temperature' => $opts['temperature'] ?? 0,
    ];

    $ch = curl_init($this->baseUrl . '/chat/completions');
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->apiKey,
        'Content-Type: application/json',
      ],
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
      return ['ok' => false, 'error' => 'cURL: ' . $err];
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
      return ['ok' => false, 'error' => 'Réponse invalide (' . $code . '): ' . substr($raw, 0, 500)];
    }
    $content = $json['choices'][0]['message']['content'] ?? '';
    if ($content === '') {
      return ['ok' => false, 'error' => 'Pas de contenu retourné', 'response' => $json];
    }
    return ['ok' => true, 'content' => $content, 'response' => $json];
  }
}

function buildPrompt(string $ocrText): array
{
  $sys = 'Tu es un assistant qui structure des reçus Vinko Petroleum. Ne renvoie que du JSON valide.';
  $user = <<<TXT
Tu es un assistant qui lit des reçus scannés de la station-service Vinko Petroleum.
Voici le texte OCR brut :

$ocrText

Extrait les informations clés et retourne un JSON strictement au format suivant (renseigne ce que tu trouves, sinon mets null/vides) :

{
  "issuer": {
  "name": "Vinko Petroleum",
  "brand": "VINKO PETROLEUM",
  "station_label": "",
  "address": "",
  "email": null,
  "telephone": null,
  "fax": null
  },
  "receipt": {
  "type": "",
  "receipt_number": ""
  },
  "vehicle": {
  "vehicle_number": ""
  },
  "destination": { "text": "" },
  "driver": { "name": "" },
  "items": [
  { "field": "PRODUIT", "value": "" },
  { "field": "QUANTITE", "value": "" },
  { "field": "VALEUR", "value": { "amount": 0, "currency": "XOF" } },
  { "field": "HUILE", "value": null }
  ],
  "date": { "raw": "" },
  "raw_text": ""
}
Ne mets rien d’autre que le JSON final.
TXT;
  return [
    ['role' => 'system', 'content' => $sys],
    ['role' => 'user', 'content' => $user],
  ];
}

$engine = method_exists('AppConfig', 'ocrEngine') ? AppConfig::ocrEngine() : 'tesseract';
$tessPath = AppConfig::ocrTesseractPath();
$lang = AppConfig::ocrLang();
$psm = AppConfig::ocrPsm();
$openaiModel = AppConfig::openAiModel();
$openaiBase = AppConfig::openAiBaseUrl();
// Permettre une clé saisie via formulaire (non persistée)
$apiKeyForm = isset($_POST['openai_api_key']) ? trim((string)$_POST['openai_api_key']) : '';
$effectiveApiKey = $apiKeyForm !== '' ? $apiKeyForm : AppConfig::openAiApiKey();
$apiKeySet = $effectiveApiKey !== '';
// Statut lisible
$apiKeyStatus = $apiKeySet
  ? ($apiKeyForm !== '' ? 'OK (formulaire)' : 'OK (env)')
  : 'Manquante';

$ocrText = null;
$gptJson = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
  if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $error = 'Upload échoué';
  } else {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    $filePath = $uploadDir . DIRECTORY_SEPARATOR . (uniqid('doc_', true) . '_' . basename($_FILES['document']['name']));
    if (!@move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
      $error = 'Impossible de déplacer le fichier';
    } else {
      $an = new OcrReceiptAnalyzer();
      $ocrRes = $an->ocrImage($filePath);
      if (!($ocrRes['ok'] ?? false)) {
        $error = 'OCR échec: ' . ($ocrRes['error'] ?? 'inconnu');
      } else {
        $ocrText = (string)($ocrRes['text'] ?? '');
        // Utiliser la clé effective (form > env)
        $client = new OpenAiClient($effectiveApiKey);
        if (!$apiKeySet) {
          $error = 'OPENAI_API_KEY non configurée (voir variables d\'environnement).';
        } else {
          $messages = buildPrompt($ocrText);
          $ai = $client->chat($messages, ['temperature' => 0]);
          if (!($ai['ok'] ?? false)) {
            $error = 'Erreur OpenAI: ' . ($ai['error'] ?? 'inconnue');
          } else {
            $gptJson = $ai['content'];
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Test OCR + GPT (Vinko)</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    pre {
      white-space: pre-wrap
    }
  </style>
</head>

<body>
  <div class="wrap">
    <?php if (file_exists(__DIR__ . '/menu.php')) include __DIR__ . '/menu.php'; ?>
    <h1>OCR + GPT</h1>

    <div class="card">
      <div><strong>Moteur OCR:</strong> <?= htmlspecialchars($engine) ?> | <strong>Lang:</strong> <?= htmlspecialchars($lang) ?> | <strong>PSM:</strong> <?= htmlspecialchars($psm) ?></div>
      <div><strong>Tesseract:</strong> <?= htmlspecialchars($tessPath) ?></div>
      <div><strong>OpenAI Base:</strong> <?= htmlspecialchars($openaiBase) ?> | <strong>Model:</strong> <?= htmlspecialchars($openaiModel) ?> | <strong>API Key:</strong> <?= htmlspecialchars($apiKeyStatus) ?></div>
    </div>

    <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="card">
      <form method="POST" enctype="multipart/form-data" class="row">
        <input type="file" name="document" accept="image/*" required>
        <input type="password" name="openai_api_key" placeholder="Clé OpenAI (optionnel)" autocomplete="off">
        <small>Astuce: si la variable d'environnement n'est pas configurée, vous pouvez coller une clé ici pour ce seul envoi. Elle n'est ni loggée ni stockée.</small>
        <button type="submit" class="btn">Analyser</button>
      </form>
    </div>

    <?php if ($ocrText !== null): ?>
      <div class="card">
        <h3>Texte OCR</h3>
        <pre><?= htmlspecialchars($ocrText) ?></pre>
      </div>
    <?php endif; ?>

    <?php if ($gptJson !== null): ?>
      <div class="card">
        <h3>JSON structuré</h3>
        <pre><?= htmlspecialchars($gptJson) ?></pre>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>
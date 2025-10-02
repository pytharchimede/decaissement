<?php

/**
 * Client simple pour un service HTTP PaddleOCR local
 * - Reste 100% open-source
 * - Déployable en local (Docker) et appelé depuis PHP
 * - Si non configuré, ne sera pas utilisé
 */
class OcrPaddleClient
{
    private string $endpoint;

    public function __construct(string $endpoint)
    {
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * Appelle le service PaddleOCR: POST /ocr avec multipart file
     * @return array{ok: bool, text?: string, error?: string}
     */
    public function ocr(string $imagePath): array
    {
        if (!is_file($imagePath)) return ['ok' => false, 'error' => 'Image introuvable'];
        $boundary = '----paddleocr-' . bin2hex(random_bytes(6));
        $body = '';
        $content = @file_get_contents($imagePath);
        if ($content === false) return ['ok' => false, 'error' => 'Lecture image échouée'];
        $filename = basename($imagePath);
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $content . "\r\n";
        $body .= "--$boundary--\r\n";

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\n",
                'content' => $body,
                'timeout' => 30,
            ]
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($this->endpoint . '/ocr', false, $ctx);
        if ($res === false) return ['ok' => false, 'error' => 'Appel PaddleOCR échoué'];
        $json = json_decode($res, true);
        if (isset($json['text'])) return ['ok' => true, 'text' => (string)$json['text']];
        return ['ok' => false, 'error' => 'Réponse OCR invalide'];
    }
}

<?php
// Récupérer les données POST envoyées par Twilio
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Vérifier si les données sont bien reçues
if (isset($data['MessageSid']) && isset($data['MessageStatus'])) {
    $messageSid = $data['MessageSid'];
    $status = $data['MessageStatus'];
    $to = $data['To'];
    $from = $data['From'];

    // Connexion à la base de données (exemple)
    $pdo = new PDO('mysql:host=localhost;dbname=decaissement', 'user', 'password');

    // Mise à jour du statut du message dans la base de données
    $stmt = $pdo->prepare("UPDATE messages SET status = :status WHERE sid = :sid");
    $stmt->execute([
        ':status' => $status,
        ':sid' => $messageSid
    ]);

    // Log pour debug (facultatif)
    error_log("Message SID: $messageSid - Status: $status - To: $to - From: $from");
} else {
    error_log("Callback reçu sans les données nécessaires !");
}

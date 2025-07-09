<?php

require_once 'vendor/autoload.php'; // chemin vers autoload de Twilio


require_once 'twilio/src/Twilio/autoload.php';

use Twilio\Rest\Client;

$sid = 'ACded19f6cd55b2ba3d18c13f438f1e878';
$token = '7f1136b112e6d8cb4a6af94223d0872e';
$from = 'whatsapp:+2250711048002';
$to = 'whatsapp:+2250748367710';

$client = new Client($sid, $token);


try {
    $message = $client->messages->create(
        $to,
        [
            "from" => $from,
            "contentSid" => "HX367253747273a87870d79e9dc8ea677f",
            "contentVariables" => json_encode([
                "1" => "CISSE OUSMANE",
                "2" => "REF123456"
            ])
        ]
    );

    echo "Message envoyé, SID : " . $message->sid . PHP_EOL;
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}

// Tester l'API comme Flutter (POST JSON)
$url = 'https://fidest.ci/decaissement/api/api.php?endpoint=confirmation_carburant';

$data = [
    "telephone" => "+2250748367710",
    "nom" => "CISSE OUSMANE"
];

$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true // pour lire la réponse même en cas d'erreur HTTP
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    echo "Erreur lors de l'appel API\n";
} else {
    echo "Réponse API : $result\n";
    // Pour debug, tu peux aussi faire :
    // $response = json_decode($result, true);
    // var_dump($response);
}

<?php

require_once 'vendor/autoload.php'; // chemin vers autoload de Twilio


require_once 'twilio/src/Twilio/autoload.php';

use Twilio\Rest\Client;

$sid = 'ACded19f6cd55b2ba3d18c13f438f1e878';
$token = '7f1136b112e6d8cb4a6af94223d0872e';
$from = 'whatsapp:+2250711048002';
$to = 'whatsapp:+2250700000001';

$client = new Client($sid, $token);


try {
    $message = $client->messages->create(
        $to,
        [
            "from" => $from,
            "contentSid" => "HX367253747273a87870d79e9dc8ea677f",
            "contentVariables" => json_encode([
                "CISSE OUSMANE",
                "REF123456"
            ])
        ]
    );

    echo "Message envoyé, SID : " . $message->sid . PHP_EOL;
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . PHP_EOL;
}

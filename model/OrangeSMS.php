<?php

class OrangeSMS
{
    private $clientId;
    private $clientSecret;
    private $token;

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->authenticate();
    }

    // Méthode pour obtenir le token d'authentification
    private function authenticate()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.orange.com/oauth/v3/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials'
        ]));
        curl_setopt($ch, CURLOPT_USERPWD, "$this->clientId:$this->clientSecret");

        //Désactive la vérification de certificat
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (isset($responseData['access_token'])) {
            $this->token = $responseData['access_token'];
        } else {
            throw new Exception("Échec de l'obtention du token.");
        }
    }

    // Méthode pour envoyer un SMS
    public function sendSMS($recipientPhoneNumber, $senderPhoneNumber, $message)
    {
        $ch = curl_init();

        // Utiliser simplement le numéro de l'expéditeur pour l'URL sans 'tel:'
        curl_setopt($ch, CURLOPT_URL, "https://api.orange.com/smsmessaging/v1/outbound/" . urlencode($senderPhoneNumber) . "/requests");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        //Désactive la vérification de certificat
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Conserver le format correct sans 'tel:tel:'
        $data = [
            'outboundSMSMessageRequest' => [
                'address' => $recipientPhoneNumber,
                'senderAddress' => $senderPhoneNumber,
                'outboundSMSTextMessage' => [
                    'message' => $message
                ]
            ]
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }


    // Méthode pour vérifier le solde SMS
    public function getSMSBalance()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.orange.com/sms/admin/v1/contracts");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }

    // Méthode pour vérifier l'usage des SMS
    public function getSMSUsage()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.orange.com/sms/admin/v1/statistics");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }
}

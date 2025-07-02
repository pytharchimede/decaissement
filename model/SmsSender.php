<?php

class SmsSender
{
    private $apiKey = "IbILNr1bs1sCV1RNuvaB7amMDS9cUGG3";
    private $apiToken = "bOdV1680020257";
    private $senderId = "BANAMUR";
    private $apiUrl = "https://panel.smsing.app/smsAPI";

    /**
     * Envoie un SMS professionnel avec OTP au gérant de station service.
     *
     * @param string $phoneNumber Numéro du destinataire (format international, ex : 2250700000000)
     * @param string $otp Code OTP à transmettre
     * @param string $stationManagerName Nom du gérant de la station
     * @return mixed Réponse de l'API ou false en cas d'erreur
     */

    public function sendOtpToStationManager($phoneNumber, $otp, $stationManagerName)
    {
        $message = "Bonjour $stationManagerName,

Votre code OTP pour la validation de la demande de carburant de BANAMUR Industries est : $otp.

Merci de procéder à la vérification et à la délivrance du carburant conformément à la procédure.

Cordialement,
M Alex BRAUD
Directeur Général de BANAMUR INDUSTRIES";

        $messageEncoded = urlencode($message);
        $url = "{$this->apiUrl}?sendsms&apikey={$this->apiKey}&apitoken={$this->apiToken}&type=sms&from={$this->senderId}&to=$phoneNumber&text=$messageEncoded";

        // Envoi via cURL avec gestion d'erreur
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // AJOUTE CETTE LIGNE POUR TEST
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return "Erreur cURL : $error\nURL : $url";
        }

        curl_close($ch);
        return $response;
    }


    public function sendBonEssenceToGerant(
        $phoneNumber,
        $codeBon,
        $numFiche,
        $nomBeneficiaire,
        $vehicule,
        $quantite,
        $montant,
        $dateDemande,
        $motif
    ) {
        $message = "Nouveau bon d'essence émis :\n"
            . "Bon : $codeBon\n"
            . "Fiche : $numFiche\n"
            . "Bénéficiaire : $nomBeneficiaire\n"
            . "Véhicule : $vehicule\n"
            . "Qté : $quantite\n"
            . "Montant : $montant FCFA\n"
            . "Date : $dateDemande\n"
            . "Motif : $motif\n"
            . "Merci de préparer la livraison.";

        $messageEncoded = urlencode($message);
        $url = "{$this->apiUrl}?sendsms&apikey={$this->apiKey}&apitoken={$this->apiToken}&type=sms&from={$this->senderId}&to=$phoneNumber&text=$messageEncoded";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return "Erreur cURL : $error\nURL : $url";
        }

        curl_close($ch);
        return $response;
    }
}

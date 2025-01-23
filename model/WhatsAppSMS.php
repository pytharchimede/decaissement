<?php

require_once __DIR__ . '/../twilio/src/Twilio/autoload.php';

use Twilio\Rest\Client;

class WhatsAppSMS
{
    private $client;
    private $from;

    public function __construct($sid, $token, $from)
    {
        $this->client = new Client($sid, $token);
        $this->from = $from;
    }

    // Méthode d'envoi simple de message WhatsApp
    public function sendWhatsAppMessage($to, $message)
    {
        try {
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from, // Utiliser votre numéro validé
                    "body" => $message
                ]
            );

            error_log("Message WhatsApp SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    // Méthode d'envoi de message avec bouton interactif (Twilio API ne supporte pas encore les boutons dans certains pays)
    // Méthode d'envoi de message avec un template et bouton interactif
    public function sendWhatsAppMessageWithTemplateAndButton($to, $nom_personnel, $id_feb, $codeAutorisation, $buttonText, $buttonUrl)
    {
        try {
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "body" => "Bonjour, voici votre code d'autorisation: $codeAutorisation", // Un corps de message de base
                    "template" => [
                        "name" => "validation_fiche_expression_besoin", // Nom du template validé
                        "language" => ["code" => "fr"], // Langue du template
                        "components" => [
                            [
                                "type" => "body",
                                "parameters" => [
                                    ["type" => "text", "text" => $nom_personnel],
                                    ["type" => "text", "text" => $id_feb],
                                    ["type" => "text", "text" => $codeAutorisation]
                                ]
                            ],
                            [
                                "type" => "button",
                                "sub_type" => "url",
                                "index" => 0,
                                "parameters" => [
                                    ["type" => "text", "text" => $buttonUrl] // Lien du bouton
                                ]
                            ]
                        ]
                    ]
                ]
            );


            error_log("Message WhatsApp avec template et bouton SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message template avec bouton: " . $e->getMessage());
            return null;
        }
    }


    // Méthode pour envoyer un message avec un template et des variables dynamiques
    public function sendWhatsAppTemplateMessage($to, $nom_personnel, $id_feb, $codeAutorisation)
    {
        try {
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "template" => [
                        "name" => "validation_fiche_expression_besoin", // Nom du template WhatsApp approuvé
                        "language" => ["code" => "fr"], // Langue du template
                        "components" => [
                            [
                                "type" => "body",
                                "parameters" => [
                                    ["type" => "text", "text" => $nom_personnel],      // {{1}} : nom_personnel
                                    ["type" => "text", "text" => $id_feb],            // {{2}} : id_feb
                                    ["type" => "text", "text" => $codeAutorisation]   // {{3}} : codeAutorisation
                                ]
                            ]
                        ]
                    ]
                ]
            );

            error_log("Message WhatsApp avec template SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message template WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    // Méthode pour envoyer un message avec un template WhatsApp validé
    public function sendWhatsAppOnlyTextTemplateMessage($to, $templateSid)
    {
        try {
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => $templateSid // Utilise le SID du template validé
                ]
            );

            error_log("Message WhatsApp avec template SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message template WhatsApp: " . $e->getMessage());
            return null;
        }
    }
    public function sendvalidation_fiche_expression_besoinTemplateMessage($to,  $nom_personnel, $id_feb)
    {
        try {
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HX07c775f18324f4cc0c2dc5457987bfcc", // Utilisation du SID du template
                    "contentVariables" => json_encode([
                        "1" => $id_feb,
                        "3" => $nom_personnel
                    ])
                ]
            );

            error_log("Message WhatsApp avec nouveau template SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message avec le nouveau template WhatsApp: " . $e->getMessage());
            return null;
        }
    }

    public function sendconfirmation_decaissementTemplateMessage($to,  $montant)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HXe8c2f9200f47d43b24e6879cc176ad00", // SID du template
                    "contentVariables" => json_encode([
                        "2" => $montant
                    ])
                ]
            );

            return $message->sid; // Retourne l'ID du message pour vérification
        } catch (Exception $e) {
            error_log("Erreur d'envoi du message : " . $e->getMessage());
            return false;
        }
    }

    public function sendConfirmationSoumissionFicheDecaissement($to, $nom_personnel, $id_demande)
    {
        try {
            // Envoi du message avec le template "confirm_soumission_fiche_decaissement"
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HX8d84a9d2b5b6e173bc13150aa35bd0e1", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $nom_personnel, // Remplace {{1}} par le nom du personnel
                        "2" => $id_demande      // Remplace {{2}} par l'identifiant de la demande
                    ])
                ]
            );

            error_log("Message WhatsApp avec template confirm_soumission_fiche_decaissement SID: " . $whatsappMessage->sid);
            return $whatsappMessage;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message confirm_soumission_fiche_decaissement: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Envoie un message WhatsApp avec le rapport de décaissements journalier
     *
     * @param string $recipientNumber Numéro de téléphone du destinataire (format international)
     * @param string $recipientName Nom du destinataire ({{1}})
     * @return mixed SID du message ou false en cas d'erreur
     */
    public function sendDailyExpenseReport($recipientNumber, $recipientName)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$recipientNumber",
                [
                    "from" => $this->from,
                    "contentSid" => "HX1892b7c28b64cdce6b9850c4ee15c7c4", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $recipientName
                    ]),
                    "persistentAction" => ["https://fidest.ci/logi/gestion_cron/exportation/pdf/gen_point.php"]
                ]
            );

            error_log("Message WhatsApp envoyé avec succès, SID: " . $message->sid);
            return [
                'status' => 'success',
                'messageSid' => $message->sid,
                'message' => 'Message envoyé avec succès !'
            ];
        } catch (\Exception $e) {
            error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }


    /**
     * Envoie un message WhatsApp en utilisant le template "confirm_decaissement"
     *
     * @param string $to Numéro de téléphone du destinataire (format international)
     * @param string $nom_personnel Nom du personnel ({{1}})
     * @param string $num_fiche Numéro de la fiche ({{2}})
     * @param string $motif Décaissement/motif ({{3}})
     * @return mixed ID du message ou false en cas d'erreur
     */
    public function sendConfirmationDecaissement($to, $nom_personnel, $num_fiche, $motif)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HX96cba150c794b21e5bdd8becf2f94fee", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $nom_personnel,
                        "2" => $num_fiche,
                        "3" => $motif
                    ])
                ]
            );

            error_log("Message WhatsApp envoyé avec succès, SID: " . $message->sid);
            return $message->sid;
        } catch (\Exception $e) {
            error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
            return false;
        }
    }
}

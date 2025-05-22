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

    /**
     * Envoie un message WhatsApp basé sur le template "alerte_approbation_en_attente"
     *
     * @param string $recipientNumber Numéro de téléphone du destinataire (format international)
     * @param string $submitterName Nom de la personne ayant soumis la fiche ({{1}})
     * @param string $fileNumber Numéro de la fiche ({{2}})
     * @param string $amount Montant de la fiche ({{3}})
     * @param string $purpose Objectif ou description de la fiche ({{4}})
     * @return array Résultat de l'envoi avec le statut et le SID du message
     */
    public function sendCarburantApprovalAlert($recipientNumber, $submitterName, $fileNumber, $amount, $purpose)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$recipientNumber",
                [
                    "from" => $this->from,
                    "contentSid" => "HXf1d82b34be70c53a7e1da5f30eca373d", // SID du template Twilio
                    "contentVariables" => json_encode([
                        "1" => $submitterName,
                        "2" => $fileNumber,
                        "3" => $amount,
                        "4" => $purpose
                    ]),
                    "statusCallback" => "https://fidest.ci/decaissement/service/webhook_whatsapp.php?recipientNumber=$recipientNumber" // URL du webhook avec recipientNumber    
                ]
            );

            return [
                'status' => 'success',
                'messageSid' => $message->sid,
                'message' => 'Message envoyé avec succès !'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Envoie un message WhatsApp basé sur le template "copy_alerte_fiche_carburant"
     *
     * @param string $recipientNumber Numéro de téléphone du destinataire (format international)
     * @param string $fileNumber Numéro de la fiche ({{1}})
     * @param string $amount Montant de la fiche ({{2}})
     * @param string $submitterName Nom de la personne ayant soumis la fiche ({{3}})
     * @param string $purpose Objectif ou description de la fiche ({{4}})
     * @return array Résultat de l'envoi avec le statut et le SID du message
     */
    public function sendApprovalAlert($recipientNumber, $fileNumber, $amount, $submitterName, $purpose)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$recipientNumber",
                [
                    "from" => $this->from,
                    "contentSid" => "HXc44063b98e2c4a66d07e26e1e45468bc", // SID du template Twilio
                    "contentVariables" => json_encode([
                        "1" => $fileNumber,
                        "2" => $amount,
                        "3" => $submitterName,
                        "4" => $purpose
                    ]),
                    "statusCallback" => "https://fidest.ci/decaissement/service/webhook_whatsapp.php?recipientNumber=$recipientNumber"
                ]
            );

            return [
                'status' => 'success',
                'messageSid' => $message->sid,
                'message' => 'Message envoyé avec succès !'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Envoie un message WhatsApp en fonction de la réponse à une question du chatbot.
     *
     * @param string $to Numéro du destinataire (format international).
     * @param string $question La question posée au chatbot.
     *
     * @return mixed Le SID du message en cas de succès, ou null en cas d'erreur.
     */
    public function sendMessage($to, $question)
    {
        try {
            // 1. Connecter à la base de données pour interroger la réponse
            $response = $this->getBotResponseFromDB($question);

            // 2. Si une réponse est trouvée dans la base de données
            if ($response) {
                $messageBody = $response;
            } else {
                // 3. Si aucune réponse n'est trouvée, proposer une liste des questions possibles
                $messageBody = "Désolé, je n'ai pas compris votre question. Voici une liste des commandes disponibles :\n\n";
                $messageBody .= "- *Recap du jour*\n";
                $messageBody .= "- *Visualiser fiche N°XXXX*\n";
                $messageBody .= "- *Approuver fiche N°XXXX*\n";
                $messageBody .= "- *Fiche personnel matricule XXXXXX*\n";
                $messageBody .= "- *Calendrier personnel matricule XXXX*\n";
                $messageBody .= "- *Valider fiche N°XXXX*\n";
                $messageBody .= "- *Reporter fiche N°XXXX au jj/mm/aaaa*\n";
                $messageBody .= "- *Refuser fiche N°XXXX*\n";
                $messageBody .= "- *Décaisser fiche N°XXXX*\n";
            }

            // 4. Créer le message à envoyer via Twilio
            $whatsappMessage = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "body" => $messageBody
                ]
            );

            // 5. Retourner le SID du message envoyé
            error_log("Message envoyé avec succès, SID : " . $whatsappMessage->sid);
            return $whatsappMessage->sid;
        } catch (Exception $e) {
            error_log("Erreur lors de l'envoi du message WhatsApp : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère la réponse du chatbot depuis la base de données en fonction de la question.
     *
     * @param string $question La question posée au chatbot.
     *
     * @return string La réponse à la question.
     */
    private function getBotResponseFromDB($question)
    {
        // 1. Préparer la connexion à la base de données (vous devez remplacer cela par votre propre connexion)
        $pdo = new PDO("mysql:host=localhost;dbname=fidestci_app_db", "fidestci_ulrich", "@Succes2019"); // Remplacez par vos infos
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. Requête SQL pour récupérer la réponse à la question
        $stmt = $pdo->prepare("SELECT response FROM chatbot_responses WHERE question LIKE :question LIMIT 1");
        $stmt->bindValue(':question', "%$question%", PDO::PARAM_STR);
        $stmt->execute();

        // 3. Vérifier si une réponse est trouvée
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. Retourner la réponse ou null si aucune correspondance
        return $result ? $result['response'] : null;
    }

    public function askQuestion($to, $currentStep)
    {
        $questions = [
            1 => "Quel est le nom de l'entreprise ?",
            2 => "Quel est le nom de la personne ?",
            3 => "Quel est le numéro de téléphone ?",
            4 => "Quelle est l'affectation ?",
            5 => "Quelle est la catégorie ?",
            6 => "Quel est le motif ?",
            7 => "Veuillez fournir une précision.",
            8 => "Quel est le montant ?",
            9 => "Quel est le mode de paiement ?"
        ];

        // Si on est encore dans la liste des questions
        if (array_key_exists($currentStep, $questions)) {
            $question = $questions[$currentStep];
            $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "body" => $question
                ]
            );
        } else {
            // Toutes les questions sont terminées, envoyer le récapitulatif
            $this->sendSummary($to);
        }
    }

    public function processResponse($to, $response, $currentStep)
    {
        // Enregistrer la réponse selon l'étape
        $responses = $_SESSION['responses'] ?? [];
        $responses[$currentStep] = $response;
        $_SESSION['responses'] = $responses;

        // Passer à l'étape suivante
        $nextStep = $currentStep + 1;
        $this->askQuestion($to, $nextStep);
    }

    public function sendSummary($to)
    {
        $responses = $_SESSION['responses'] ?? [];
        $messageBody = "- *Voici les informations fournies :*\n";
        $messageBody .= "  - entreprise : " . ($responses[1] ?? "Non spécifiée") . "\n";
        $messageBody .= "  - nom : " . ($responses[2] ?? "Non spécifié") . "\n";
        $messageBody .= "  - téléphone : " . ($responses[3] ?? "Non spécifié") . "\n";
        $messageBody .= "  - affectation : " . ($responses[4] ?? "Non spécifiée") . "\n";
        $messageBody .= "  - catégorie : " . ($responses[5] ?? "Non spécifiée") . "\n";
        $messageBody .= "  - motif : " . ($responses[6] ?? "Non spécifié") . "\n";
        $messageBody .= "  - précision : " . ($responses[7] ?? "Non spécifiée") . "\n";
        $messageBody .= "  - montant : " . ($responses[8] ?? "Non spécifié") . "\n";
        $messageBody .= "  - Mode de paiement : " . ($responses[9] ?? "Non spécifié");

        $this->client->messages->create(
            "whatsapp:$to",
            [
                "from" => $this->from,
                "body" => $messageBody . "\n\nRépondez 'OK' pour confirmer ou 'NON' pour recommencer."
            ]
        );
    }


    /**
     * Envoie un message WhatsApp en utilisant le template "confirm_approbation"
     *
     * @param string $to Numéro de téléphone du destinataire (format international)
     * @param string $nom_demandeur Nom du demandeur ({{1}})
     * @param string $num_fiche Numéro de la fiche ({{2}})
     * @param string $nom_validateur nom du validateur ({{3}})
     * @return mixed ID du message ou false en cas d'erreur
     */
    public function sendConfirmationApprobation($to, $nom_demandeur, $num_fiche, $nom_validateur)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HXc46e319ab68ea78e9849c67055b01ff3", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $nom_demandeur,
                        "2" => $num_fiche,
                        "3" => $nom_validateur
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

    /**
     * Envoie un message WhatsApp en utilisant le template "confirm_approbation"
     *
     * @param string $to Numéro de téléphone du destinataire (format international)
     * @param string $nom_demandeur Nom du demandeur ({{1}})
     * @param string $num_fiche Numéro de la fiche ({{2}})
     * @param string $nom_validateur nom du validateur ({{3}})
     * @return mixed ID du message ou false en cas d'erreur
     */
    public function sendInformRefus($to, $nom_demandeur, $num_fiche, $nom_validateur)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HXfe73b838665cf8a952fcac7806b38009", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $nom_demandeur,
                        "2" => $num_fiche,
                        "3" => $nom_validateur
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

    /**
     * Envoie un message WhatsApp en utilisant le template "confirm_approbation"
     *
     * @param string $to Numéro de téléphone du destinataire (format international)
     * @param string $nom_demandeur Nom du demandeur ({{1}})
     * @param string $num_fiche Numéro de la fiche ({{2}})
     * @param string $nom_validateur nom du validateur ({{3}})
     * @param string $date_new nom du validateur ({{4}})
     * @return mixed ID du message ou false en cas d'erreur
     */
    public function sendInformReport($to, $nom_demandeur, $num_fiche, $nom_validateur, $date_new)
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:$to",
                [
                    "from" => $this->from,
                    "contentSid" => "HX2671bf49532d6c862932287632cf42c9", // SID du template
                    "contentVariables" => json_encode([
                        "1" => $nom_demandeur,
                        "2" => $num_fiche,
                        "3" => $nom_validateur,
                        "4" => $date_new
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

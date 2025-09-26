<?php
// filepath: c:\wamp\www\decaissement\model\DemandesCarburantAttenteController.php
require_once __DIR__ . '/Database.php';

class DemandesCarburantAttenteController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            // Recherche insensible à la casse dans precision_fiche OU designation_fiche
            // Inclut les fiches où approuve est 0 ou NULL (en attente)
            $sql = "SELECT * FROM fiche
                    WHERE (approuve = 0 OR approuve IS NULL)
                      AND (
                        LOWER(COALESCE(precision_fiche, '')) LIKE :motclef
                        OR LOWER(COALESCE(designation_fiche, '')) LIKE :motclef
                      )
                    ORDER BY date_creat_fiche DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':motclef' => '%carburant%']);
            $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $demandes
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => "Erreur lors de la récupération des demandes : " . $e->getMessage()
            ]);
        }
    }

    /**
     * Accepter une demande de carburant (certifier, approuver, valider)
     * @param string $num_fiche
     */
    public function accepter()
    {
        // Récupération du num_fiche depuis POST ou GET
        $num_fiche = $_POST['num_fiche'] ?? $_GET['num_fiche'] ?? null;
        $secur = $_POST['secur'] ?? 'SYSTEM';
        $adresse_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $port = $_SERVER['REMOTE_PORT'] ?? '';

        if (!$num_fiche) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'num_fiche manquant']);
            return;
        }

        require_once __DIR__ . '/Fiche.php';

        $ficheObj = new Fiche($this->pdo);

        // 1. Certifier conforme
        $ficheObj->certifierConformeFiche($num_fiche, $secur, $adresse_ip, $port);

        // 2. Approuver
        $ficheObj->approveFicheByNum($num_fiche, $secur);

        // 3. Valider 
        $success = $ficheObj->validerFicheByNum($num_fiche, $secur, $adresse_ip, $port);

        if ($success) {
            // Récupérer infos de la fiche
            $fiche = $ficheObj->getByNumFiche($num_fiche);
            if (!$fiche) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Fiche non trouvée après validation']);
                return;
            }

            // Générer le code_bon (même codification que valider_fiche_carburant.php)
            $date = new \DateTime($fiche['date_creat_fiche']);
            $code_bon = 'BE-' . $date->format('ym') . '-' . substr(str_pad($num_fiche, 5, '0', STR_PAD_LEFT), -5);

            // Créer l'enregistrement demande_essence
            require_once __DIR__ . '/DemandeEssence.php';
            $demandeEssenceObj = new DemandeEssence($this->pdo);
            $data = [
                'num_fiche'        => $num_fiche,
                'code_bon'         => $code_bon,
                'nom_beneficiaire' => $fiche['beficiaire_fiche'],
                'vehicule'         => $fiche['designation_fiche'] ?? '',
                'quantite'         => 0,
                'montant'          => $fiche['montant_fiche'],
                'date_demande'     => $fiche['date_creat_fiche'],
                'motif'            => $fiche['precision_fiche'] ?? '',
                'dg_nom'           => 'M. Alex Braud'
            ];
            $demandeEssenceObj->create($data);

            // Envoi WhatsApp (template) au bénéficiaire et à la gérante
            require_once __DIR__ . '/WhatsAppSMS.php';
            require_once __DIR__ . '/Config.php';
            $whatsapp = new WhatsAppSMS(AppConfig::twilioSid(), AppConfig::twilioToken(), AppConfig::whatsappFrom());

            // Bénéficiaire
            $raw = preg_replace('/\D+/', '', (string)$fiche['tel_beneficiaire_fiche']);
            if (strpos($raw, '225') === 0) {
                $raw = substr($raw, 3);
            }
            $raw = ltrim($raw, '0');
            $whatsappNumber = "+225" . $raw;
            $nom_demandeur = $fiche['beficiaire_fiche'];
            $resultBon = $whatsapp->sendCarburantBon($whatsappNumber, $nom_demandeur, $code_bon);

            // Gérante
            $numeroGerant = AppConfig::geranteSms();
            $numeroGerantWhatsApp = AppConfig::geranteWhatsapp();
            $whatsapp->sendNotifCreatToGerant(
                $numeroGerantWhatsApp,
                $code_bon,
                $fiche['beficiaire_fiche'],
                (string)$fiche['montant_fiche'],
                (new \DateTime($fiche['date_creat_fiche']))->format('d/m/Y H:i'),
                $code_bon,
                $code_bon
            );

            // SMS legacy au gérant
            require_once __DIR__ . '/SmsSender.php';
            $smsSender = new SmsSender();
            $smsSender->sendBonEssenceToGerant(
                $numeroGerant,
                $code_bon,
                $num_fiche,
                $fiche['beficiaire_fiche'],
                $fiche['designation_fiche'] ?? '',
                0,
                $fiche['montant_fiche'],
                $fiche['date_creat_fiche'],
                $fiche['precision_fiche'] ?? ''
            );

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => "Demande carburant $num_fiche acceptée; bon généré et notifications envoyées",
                'code_bon' => $code_bon,
                'whatsapp_benef' => is_array($resultBon) ? $resultBon : ['status' => ($resultBon ? 'success' : 'error')]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Erreur lors de la validation de la fiche $num_fiche"]);
        }
    }

    public function refuser()
    {
        $num_fiche = $_POST['num_fiche'] ?? $_GET['num_fiche'] ?? null;
        $secur = $_POST['secur'] ?? 'SYSTEM';

        if (!$num_fiche) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'num_fiche manquant']);
            return;
        }

        require_once __DIR__ . '/Fiche.php';
        $ficheObj = new Fiche($this->pdo);

        // Mettre à jour la fiche comme refusée
        $data = [
            'etat_fiche' => 2,
            'approuve' => 2,
            'secur_desapprouve' => $secur,
            'date_desapprouve' => date('Y-m-d H:i:s')
        ];
        $fiche = $ficheObj->getByNumFiche($num_fiche);
        if (!$fiche) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Fiche non trouvée']);
            return;
        }
        $ficheObj->update($fiche['id_fiche'], $data);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => "Demande carburant $num_fiche refusée"]);
    }
}

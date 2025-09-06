<?php
require_once 'Database.php';

class OtpController
{
    public function validateOtp()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['otp']) || !isset($input['num_beneficiaire'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres manquants']);
            return;
        }

        $otp = $input['otp'];
        $numBeneficiaire = $input['num_beneficiaire'];

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM rechargement_carburant WHERE num_beneficiaire = :num AND otp = :otp AND valid_otp = 0 LIMIT 1");
            $stmt->execute(['num' => $numBeneficiaire, 'otp' => $otp]);
            $rechargement = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rechargement) {
                // Marquer OTP comme validé
                $update = $pdo->prepare("UPDATE rechargement_carburant SET valid_otp = 1 WHERE id_rechargement_carburant = :id");
                $update->execute(['id' => $rechargement['id_rechargement_carburant']]);

                echo json_encode(['status' => 'success', 'message' => 'OTP validé avec succès']);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'OTP invalide ou déjà utilisé']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur serveur']);
        }
    }


    public function sendConfirmationCarburant()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        // Vérification des paramètres requis
        if (!isset($input['telephone']) || !isset($input['nom']) || !isset($input['montant'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres manquants']);
            return;
        }

        $telephone = $input['telephone'];
        $nom = $input['nom'];
        $montant = $input['montant'];
        $otp = rand(100000, 999999);

        // 1. Récupérer la station du gérant via le téléphone
        $pdo = Database::getConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT id FROM stations_service WHERE telephone_gerant = :tel LIMIT 1");
        $stmt->execute(['tel' => $telephone]);
        $station = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$station) {
            http_response_code(404);
            echo json_encode(['error' => "Aucune station trouvée pour ce gérant"]);
            return;
        }

        $station_id = $station['id'];

        // 2. (Désactivé) Envoi OTP WhatsApp
        require_once __DIR__ . '/WhatsAppSMS.php';
        $twilioSid = "...";
        $twilioToken = "...";
        $whatsappFrom = "...";
        $whatsapp = new WhatsAppSMS($twilioSid, $twilioToken, $whatsappFrom);
        $response = $whatsapp->sendOtpRechargeCarburant($telephone, $nom, $otp);

        // 3. Enregistrement en BDD
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO rechargement_carburant (num_beneficiaire, otp, valid_otp, date_enregistrement, montant_rechargement_carburant, station_id)
                 VALUES (:num, :otp, 0, NOW(), :montant, :station_id)"
            );
            $stmt->execute([
                'num' => $telephone,
                'otp' => $otp,
                'montant' => $montant,
                'station_id' => $station_id
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur lors de l'enregistrement du rechargement : " . $e->getMessage()]);
            error_log("Erreur insertion rechargement : " . $e->getMessage());
            return;
        }

        // Réponse sans envoi OTP
        echo json_encode([
            'status' => 'success',
            'message' => 'Rechargement enregistré en base (WhatsApp non envoyé)'
        ]);
    }
}

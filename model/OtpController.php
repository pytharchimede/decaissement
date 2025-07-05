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

                echo json_encode(['success' => true, 'message' => 'OTP validé avec succès']);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'OTP invalide ou déjà utilisé']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }


    public function sendConfirmationCarburant()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['telephone']) || !isset($input['nom'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres manquants']);
            return;
        }

        $telephone = $input['telephone'];
        $nom = $input['nom'];
        $otp = rand(100000, 999999);

        require_once __DIR__ . '/WhatsAppSMS.php';
        $twilioSid = "ACded19f6cd55b2ba3d18c13f438f1e878";
        $twilioToken = "7f1136b112e6d8cb4a6af94223d0872e";
        $whatsappFrom = "whatsapp:+2250711048002";

        $pdo = Database::getConnection();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $stmt = $pdo->prepare("INSERT INTO rechargement_carburant (num_beneficiaire, otp, valid_otp, date_enregistrement) VALUES (:num, :otp, 0, NOW())");
            $stmt->execute([
                'num' => $telephone,
                'otp' => $otp
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur lors de l'enregistrement de l'OTP : " . $e->getMessage()]);
            error_log("Erreur insertion OTP : " . $e->getMessage());
            return;
        }

        $whatsapp = new WhatsAppSMS($twilioSid, $twilioToken, $whatsappFrom);
        $response = $whatsapp->sendOtpRechargeCarburant($telephone, $nom, $otp);

        if ($response) {
            echo json_encode([
                'status' => 'success',
                'message' => 'OTP envoyé avec succès'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => "Échec de l'envoi de l'OTP"]);
        }
    }
}

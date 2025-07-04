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
}

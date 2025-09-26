<?php
require_once __DIR__ . '/Database.php';

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
        // Supporte JSON (application/json) et formulaire (multipart/form-data)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

        if ($isMultipart) {
            $telephone = $_POST['telephone'] ?? null;
            $nom = $_POST['nom'] ?? null;
            $montant = $_POST['montant'] ?? null;
        } else {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $telephone = $input['telephone'] ?? null;
            $nom = $input['nom'] ?? null;
            $montant = $input['montant'] ?? null;
        }

        // Vérification des paramètres requis
        if (!$telephone || !$nom || $montant === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Paramètres manquants']);
            return;
        }

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

        // 3. Upload de l'image du reçu si fournie
        $imgFileName = null;
        try {
            $basePath = dirname(__DIR__); // vers racine du projet decaissement
            $uploadDir = $basePath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recharge_recu' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            // Cas multipart: fichier direct
            if ($isMultipart && isset($_FILES['img_recu']) && $_FILES['img_recu']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['img_recu']['tmp_name'];
                $orig = basename($_FILES['img_recu']['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed, true)) {
                    $imgFileName = 'recharge_' . date('Ymd_His') . '_' . substr(md5($telephone . $nom . microtime(true)), 0, 6) . '.' . $ext;
                    $dest = $uploadDir . $imgFileName;
                    if (!move_uploaded_file($tmp, $dest)) {
                        $imgFileName = null; // upload échoué, on ignore
                    }
                }
            }

            // Cas JSON: base64 optionnelle
            if (!$isMultipart && !empty($input['img_recu_base64'])) {
                if (preg_match('/^data:image\/(png|jpeg|jpg|webp|gif);base64,/', $input['img_recu_base64'], $m)) {
                    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                    $data = substr($input['img_recu_base64'], strpos($input['img_recu_base64'], ',') + 1);
                    $bin = base64_decode($data);
                    if ($bin !== false) {
                        $imgFileName = 'recharge_' . date('Ymd_His') . '_' . substr(md5($telephone . $nom . microtime(true)), 0, 6) . '.' . $ext;
                        file_put_contents($uploadDir . $imgFileName, $bin);
                    }
                }
            }
        } catch (Exception $e) {
            // Ne pas bloquer si upload échoue
            error_log('Upload recu rechargement échoué: ' . $e->getMessage());
            $imgFileName = null;
        }

        // 4. Enregistrement en BDD
        try {
            // Cherche la station
            $stmt = $pdo->prepare(
                "INSERT INTO rechargement_carburant (num_beneficiaire, otp, valid_otp, date_enregistrement, montant_rechargement_carburant, station_id)
                 VALUES (:num, :otp, 0, NOW(), :montant, :station_id)"
            );
            $params = [
                'num' => $telephone,
                'otp' => $otp,
                'montant' => $montant,
                'station_id' => $station_id
            ];

            // Tente d'ajouter le nom de l'image si la colonne 'img_recu' existe
            if ($imgFileName) {
                $hasImgCol = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rechargement_carburant' AND COLUMN_NAME = 'img_recu' LIMIT 1");
                $hasImgCol->execute();
                if ($hasImgCol->fetchColumn()) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO rechargement_carburant (num_beneficiaire, otp, valid_otp, date_enregistrement, montant_rechargement_carburant, station_id, img_recu)
                         VALUES (:num, :otp, 0, NOW(), :montant, :station_id, :img)"
                    );
                    $params['img'] = $imgFileName;
                }
            }

            $stmt->execute($params);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => "Erreur lors de l'enregistrement du rechargement : " . $e->getMessage()]);
            error_log("Erreur insertion rechargement : " . $e->getMessage());
            return;
        }

        // Réponse
        echo json_encode([
            'status' => 'success',
            'message' => 'Rechargement enregistré',
            'img_recu' => $imgFileName,
            'note' => $imgFileName ? null : "Ajoutez une colonne TEXT 'img_recu' à la table pour conserver l'image (facultatif)"
        ]);
    }
}

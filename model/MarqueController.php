<?php
// filepath: c:\wamp\www\decaissement\model\MarqueController.php

class MarqueController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM marque ORDER BY nom ASC");
            $marques = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'status' => 'success',
                'data' => $marques
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => "Erreur lors de la récupération : " . $e->getMessage()
            ]);
        }
    }


    public function ajouterMarque()
    {
        if (!isset($_POST['nom'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nom de la marque requis']);
            return;
        }
        $nom = trim($_POST['nom']);
        $logo_url = null;

        // Gestion de l'upload du logo (optionnel)
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/marques/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        // On continue sans logo si le dossier ne peut pas être créé
                        $logo_url = null;
                    }
                }
                $filename = uniqid('marque_') . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $logo_url = 'uploads/marques/' . $filename;
                }
            }
            // Si le format n'est pas conforme, on ignore simplement le logo
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO marque (nom, logo_url) VALUES (:nom, :logo_url)");
            $stmt->execute([
                'nom' => $nom,
                'logo_url' => $logo_url
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Marque ajoutée']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
        }
    }
}

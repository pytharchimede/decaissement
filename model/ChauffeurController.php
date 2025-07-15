<?php
// filepath: c:\wamp\www\decaissement\model\ChauffeurController.php

class ChauffeurController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM chauffeur ORDER BY nom ASC");
            $chauffeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'status' => 'success',
                'data' => $chauffeurs
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => "Erreur lors de la récupération : " . $e->getMessage()
            ]);
        }
    }


    public function ajouterChauffeur()
    {
        if (!isset($_POST['nom'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nom du chauffeur requis']);
            return;
        }
        $nom = trim($_POST['nom']);
        $telephone = $_POST['telephone'] ?? null;
        $permis = $_POST['permis'] ?? null;
        $groupe_sanguin = $_POST['groupe_sanguin'] ?? null;
        $photo_url = null;

        // Gestion de l'upload de la photo (optionnel)
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/chauffeurs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = uniqid('chauffeur_') . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $photo_url = 'uploads/chauffeurs/' . $filename;
                }
            }
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO chauffeur (nom, telephone, permis, groupe_sanguin, photo_url) VALUES (:nom, :telephone, :permis, :groupe_sanguin, :photo_url)");
            $stmt->execute([
                'nom' => $nom,
                'telephone' => $telephone,
                'permis' => $permis,
                'groupe_sanguin' => $groupe_sanguin,
                'photo_url' => $photo_url
            ]);
            $chauffeur_id = $this->pdo->lastInsertId();

            // Gestion des pièces jointes (ex: CNI, autres)
            if (!empty($_FILES['pieces']) && is_array($_FILES['pieces']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/chauffeurs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                foreach ($_FILES['pieces']['name'] as $i => $pieceName) {
                    if ($_FILES['pieces']['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($pieceName, PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (in_array($ext, $allowed)) {
                            $filename = uniqid('piece_') . '.' . $ext;
                            $dest = $uploadDir . $filename;
                            if (move_uploaded_file($_FILES['pieces']['tmp_name'][$i], $dest)) {
                                $piece_url = 'uploads/chauffeurs/' . $filename;
                                $type_piece = $_POST['pieces_types'][$i] ?? 'Document';
                                // Enregistrement en base
                                $stmtPiece = $this->pdo->prepare("INSERT INTO chauffeur_piece (chauffeur_id, type, url) VALUES (?, ?, ?)");
                                $stmtPiece->execute([$chauffeur_id, $type_piece, $piece_url]);
                            }
                        }
                    }
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Chauffeur ajouté']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Erreur ajout : " . $e->getMessage()]);
        }
    }


    public function getPieces()
    {
        if (!isset($_GET['chauffeur_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'chauffeur_id requis']);
            return;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM chauffeur_piece WHERE chauffeur_id = ?");
            $stmt->execute([$_GET['chauffeur_id']]);
            $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'status' => 'success',
                'data' => $pieces
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

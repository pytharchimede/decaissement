<?php
// filepath: c:\wamp\www\decaissement\model\MaterielController.php

class MaterielController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Liste des matériels
    public function getAll()
    {
        $sql = "SELECT m.id, m.nom, c.libelle AS categorie, m.etat, m.quantite, m.emplacement
                FROM materiel m
                JOIN materiel_categorie c ON m.categorie_id = c.id
                ORDER BY m.nom ASC";
        $stmt = $this->pdo->query($sql);
        $materiels = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $materiels
        ]);
    }

    // Liste des catégories
    public function getCategories()
    {
        try {
            $sql = "SELECT id, libelle FROM materiel_categorie ORDER BY libelle ASC";
            $stmt = $this->pdo->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $categories
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // Ajouter un matériel
    public function ajouter()
    {
        $nom = $_POST['nom'] ?? null;
        $categorie = $_POST['categorie'] ?? null; // Peut être id ou libelle
        $etat = $_POST['etat'] ?? null;
        $quantite = $_POST['quantite'] ?? 1;
        $emplacement = $_POST['emplacement'] ?? null;

        if (!$nom || !$categorie || !$etat || !$emplacement) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tous les champs sont requis']);
            return;
        }

        // Si la catégorie est un libellé, on récupère son id
        if (!is_numeric($categorie)) {
            $stmt = $this->pdo->prepare("SELECT id FROM materiel_categorie WHERE libelle = ?");
            $stmt->execute([$categorie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $categorie = $row['id'];
            } else {
                // Création de la catégorie si elle n'existe pas
                $stmt = $this->pdo->prepare("INSERT INTO materiel_categorie (libelle) VALUES (?)");
                $stmt->execute([$_POST['categorie']]);
                $categorie = $this->pdo->lastInsertId();
            }
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO materiel (nom, categorie_id, etat, quantite, emplacement) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $categorie, $etat, $quantite, $emplacement]);
            echo json_encode(['status' => 'success', 'message' => 'Matériel ajouté']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

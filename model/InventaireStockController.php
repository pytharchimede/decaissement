<?php
// filepath: c:\wamp\www\decaissement\model\InventaireStockController.php

class InventaireStockController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Liste des inventaires (filtrage possible par date)
    public function getAll()
    {
        $date = $_GET['date'] ?? null;
        $where = '';
        $params = [];
        if ($date) {
            $where = "WHERE date_inventaire = :date";
            $params['date'] = $date;
        }

        $sql = "SELECT i.id, i.date_inventaire, c.libelle AS categorie, i.designation, i.quantite, i.emplacement, i.photo, i.qrcode
                FROM inventaire_stock i
                JOIN inventaire_stock_categorie c ON i.categorie_id = c.id
                $where
                ORDER BY i.date_inventaire DESC, i.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $inventaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $inventaires
        ]);
    }

    // Liste des catégories
    public function getCategories()
    {
        try {
            $sql = "SELECT id, libelle FROM inventaire_stock_categorie ORDER BY libelle ASC";
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

    // Ajouter un inventaire
    public function ajouter()
    {
        $date = $_POST['date_inventaire'] ?? null;
        $categorie = $_POST['categorie'] ?? null; // id ou libelle
        $designation = $_POST['designation'] ?? null;
        $quantite = $_POST['quantite'] ?? 0;
        $emplacement = $_POST['emplacement'] ?? null;
        $qrcode = $_POST['qrcode'] ?? null;
        $photo = $_POST['photo'] ?? null; // chemin ou nom du fichier

        if (!$date || !$categorie || !$designation || !$emplacement || !$qrcode) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tous les champs requis']);
            return;
        }

        // Si la catégorie est un libellé, on récupère son id
        if (!is_numeric($categorie)) {
            $stmt = $this->pdo->prepare("SELECT id FROM inventaire_stock_categorie WHERE libelle = ?");
            $stmt->execute([$categorie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $categorie = $row['id'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO inventaire_stock_categorie (libelle) VALUES (?)");
                $stmt->execute([$categorie]);
                $categorie = $this->pdo->lastInsertId();
            }
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO inventaire_stock (date_inventaire, categorie_id, designation, quantite, emplacement, photo, qrcode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $categorie, $designation, $quantite, $emplacement, $photo, $qrcode]);
            echo json_encode(['status' => 'success', 'message' => 'Inventaire ajouté']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

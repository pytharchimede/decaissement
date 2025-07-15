<?php
// filepath: c:\wamp\www\decaissement\model\StockController.php

class StockController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Liste valorisée des stocks
    public function getAll()
    {
        $sql = "SELECT s.id, c.libelle AS categorie, s.designation, s.quantite, s.prix_unitaire, s.emplacement
                FROM stock s
                JOIN stock_categorie c ON s.categorie_id = c.id
                ORDER BY c.libelle, s.designation";
        $stmt = $this->pdo->query($sql);
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcul du total
        $total = 0;
        foreach ($stocks as $s) {
            $total += intval($s['quantite']) * intval($s['prix_unitaire']);
        }

        echo json_encode([
            'status' => 'success',
            'total' => $total,
            'data' => $stocks
        ]);
    }

    // Liste des catégories
    public function getCategories()
    {
        $sql = "SELECT id, libelle FROM stock_categorie ORDER BY libelle ASC";
        $stmt = $this->pdo->query($sql);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    // Ajouter ou modifier un stock
    public function ajouterOuModifier()
    {
        $id = $_POST['id'] ?? null;
        $categorie = $_POST['categorie'] ?? null; // id ou libelle
        $designation = $_POST['designation'] ?? null;
        $quantite = $_POST['quantite'] ?? 0;
        $prix_unitaire = $_POST['prix_unitaire'] ?? 0;
        $emplacement = $_POST['emplacement'] ?? null;

        if (!$categorie || !$designation || !$emplacement) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Champs requis manquants']);
            return;
        }

        // Si la catégorie est un libellé, on récupère son id
        if (!is_numeric($categorie)) {
            $stmt = $this->pdo->prepare("SELECT id FROM stock_categorie WHERE libelle = ?");
            $stmt->execute([$categorie]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $categorie = $row['id'];
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO stock_categorie (libelle) VALUES (?)");
                $stmt->execute([$categorie]);
                $categorie = $this->pdo->lastInsertId();
            }
        }

        try {
            if ($id) {
                // Modification
                $stmt = $this->pdo->prepare("UPDATE stock SET categorie_id=?, designation=?, quantite=?, prix_unitaire=?, emplacement=? WHERE id=?");
                $stmt->execute([$categorie, $designation, $quantite, $prix_unitaire, $emplacement, $id]);
                echo json_encode(['status' => 'success', 'message' => 'Stock modifié']);
            } else {
                // Ajout
                $stmt = $this->pdo->prepare("INSERT INTO stock (categorie_id, designation, quantite, prix_unitaire, emplacement) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$categorie, $designation, $quantite, $prix_unitaire, $emplacement]);
                echo json_encode(['status' => 'success', 'message' => 'Stock ajouté']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

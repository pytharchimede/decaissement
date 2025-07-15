<?php
// filepath: c:\wamp\www\decaissement\model\BonEntreeController.php

class BonEntreeController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }


    // Liste des catégories de bon d'entrée
    public function getCategories()
    {
        $sql = "SELECT id, libelle FROM bon_entree_categorie ORDER BY libelle ASC";
        $stmt = $this->pdo->query($sql);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    // Liste des bons d'entrée
    public function getAll()
    {
        $sql = "SELECT b.*, c.lib_chantier AS chantier_libelle
                FROM bon_entree b
                LEFT JOIN chantier c ON b.chantier_id = c.id_chantier
                ORDER BY b.date_entree DESC, b.id DESC";
        $stmt = $this->pdo->query($sql);
        $bons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $bons
        ]);
    }

    // Ajouter un bon d'entrée et impacter le stock
    public function ajouter()
    {
        $numero = $_POST['numero'] ?? null;
        $date = $_POST['date_entree'] ?? null;
        $fournisseur = $_POST['fournisseur'] ?? null;
        $categorie = $_POST['categorie'] ?? null;
        $quantite = $_POST['quantite'] ?? 0;
        $piece = $_POST['piece'] ?? null;
        $commentaire = $_POST['commentaire'] ?? null;
        $affectation = $_POST['affectation'] ?? null;
        $chantier_id = $_POST['chantier_id'] ?? null;

        if (!$numero || !$date || !$fournisseur || !$categorie || !$quantite || !$affectation) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Champs requis manquants']);
            return;
        }

        try {
            // 1. Ajout du bon d'entrée
            $stmt = $this->pdo->prepare("INSERT INTO bon_entree (numero, date_entree, fournisseur, categorie, quantite, piece, commentaire, affectation, chantier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$numero, $date, $fournisseur, $categorie, $quantite, $piece, $commentaire, $affectation, $chantier_id]);

            // 2. Impact sur le stock (uniquement si affectation = "En stock")
            if ($affectation === "En stock") {
                // On cherche si le stock existe déjà pour cette catégorie/produit
                $stmt = $this->pdo->prepare("SELECT id, quantite FROM stock WHERE designation = ? LIMIT 1");
                $stmt->execute([$categorie]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($stock) {
                    // Mise à jour de la quantité
                    $newQuantite = intval($stock['quantite']) + intval($quantite);
                    $stmt = $this->pdo->prepare("UPDATE stock SET quantite = ? WHERE id = ?");
                    $stmt->execute([$newQuantite, $stock['id']]);
                } else {
                    // Création d'une nouvelle ligne de stock (catégorie_id à adapter si besoin)
                    $categorie_id = null;
                    $stmtCat = $this->pdo->prepare("SELECT id FROM stock_categorie WHERE libelle = ?");
                    $stmtCat->execute([$categorie]);
                    $catRow = $stmtCat->fetch(PDO::FETCH_ASSOC);
                    if ($catRow) {
                        $categorie_id = $catRow['id'];
                    } else {
                        $stmtCat = $this->pdo->prepare("INSERT INTO stock_categorie (libelle) VALUES (?)");
                        $stmtCat->execute([$categorie]);
                        $categorie_id = $this->pdo->lastInsertId();
                    }
                    $stmt = $this->pdo->prepare("INSERT INTO stock (categorie_id, designation, quantite, prix_unitaire, emplacement) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$categorie_id, $categorie, $quantite, 0, '']);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Bon d\'entrée ajouté et stock mis à jour']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

<?php
// filepath: c:\wamp\www\decaissement\model\BonSortieController.php

class BonSortieController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // Liste des motifs de sortie
    public function getMotifs()
    {
        $sql = "SELECT id, libelle FROM bon_sortie_motif ORDER BY libelle ASC";
        $stmt = $this->pdo->query($sql);
        $motifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $motifs
        ]);
    }

    // Liste des bons de sortie
    public function getAll()
    {
        $sql = "SELECT b.*, c.lib_chantier AS chantier_libelle
                FROM bon_sortie b
                LEFT JOIN chantier c ON b.chantier_id = c.id_chantier
                ORDER BY b.date_sortie DESC, b.id DESC";
        $stmt = $this->pdo->query($sql);
        $bons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $bons
        ]);
    }

    // Ajouter un bon de sortie et impacter le stock
    public function ajouter()
    {
        $numero = $_POST['numero'] ?? null;
        $date = $_POST['date_sortie'] ?? null;
        $beneficiaire = $_POST['beneficiaire'] ?? null;
        $categorie = $_POST['categorie'] ?? null;
        $quantite = $_POST['quantite'] ?? 0;
        $motif = $_POST['motif'] ?? null;
        $piece = $_POST['piece'] ?? null;
        $commentaire = $_POST['commentaire'] ?? null;
        $affectation = $_POST['affectation'] ?? null;
        $chantier_id = $_POST['chantier_id'] ?? null;

        if (!$numero || !$date || !$beneficiaire || !$categorie || !$quantite || !$motif || !$affectation) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Champs requis manquants']);
            return;
        }

        try {
            // 1. Ajout du bon de sortie
            $stmt = $this->pdo->prepare("INSERT INTO bon_sortie (numero, date_sortie, beneficiaire, categorie, quantite, motif, piece, commentaire, affectation, chantier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$numero, $date, $beneficiaire, $categorie, $quantite, $motif, $piece, $commentaire, $affectation, $chantier_id]);

            // 2. Impact sur le stock (on retire la quantité si "En stock" ou "Sur chantier" ou "Pour le bureau")
            if (in_array($affectation, ["En stock", "Sur chantier", "Pour le bureau"])) {
                $stmt = $this->pdo->prepare("SELECT id, quantite FROM stock WHERE designation = ? LIMIT 1");
                $stmt->execute([$categorie]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($stock) {
                    $newQuantite = intval($stock['quantite']) - intval($quantite);
                    if ($newQuantite < 0) $newQuantite = 0;
                    $stmt = $this->pdo->prepare("UPDATE stock SET quantite = ? WHERE id = ?");
                    $stmt->execute([$newQuantite, $stock['id']]);
                }
                // Si le stock n'existe pas, on ne fait rien (ou on peut retourner une erreur selon la politique)
            }

            echo json_encode(['status' => 'success', 'message' => 'Bon de sortie ajouté et stock mis à jour']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

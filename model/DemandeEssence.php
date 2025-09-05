<?php

class DemandeEssence
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Récupérer une demande par son ID
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM demande_essence WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCodeBon($codeBon)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM demande_essence WHERE code_bon = ?");
        $stmt->execute([$codeBon]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Ajouter une nouvelle demande
    public function create($data)
    {
        $sql = "INSERT INTO demande_essence (num_fiche, code_bon, nom_beneficiaire, vehicule, quantite, montant, date_demande, motif, dg_nom)
                VALUES (:num_fiche, :code_bon, :nom_beneficiaire, :vehicule, :quantite, :montant, :date_demande, :motif, :dg_nom)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':num_fiche'        => $data['num_fiche'],
            ':code_bon'         => $data['code_bon'],
            ':nom_beneficiaire' => $data['nom_beneficiaire'],
            ':vehicule'         => $data['vehicule'],
            ':quantite'         => $data['quantite'],
            ':montant'          => $data['montant'],
            ':date_demande'     => $data['date_demande'],
            ':motif'            => $data['motif'],
            ':dg_nom'           => $data['dg_nom']
        ]);
    }

    // Lister toutes les demandes
    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT * FROM demande_essence ORDER BY date_demande DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

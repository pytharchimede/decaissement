<?php
// filepath: c:\wamp\www\decaissement\model\DemandesCarburantAttenteController.php

class DemandesCarburantAttenteController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function getAll()
    {
        try {
            // Recherche insensible à la casse sur precision_fiche
            $sql = "SELECT * FROM fiche 
                    WHERE approuve = 0 
                    AND LOWER(precision_fiche) LIKE :motclef
                    ORDER BY date_creat_fiche DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['motclef' => '%carburant%']);
            $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $demandes
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => "Erreur lors de la récupération des demandes : " . $e->getMessage()
            ]);
        }
    }

    /**
     * Accepter une demande de carburant (certifier, approuver, valider)
     * @param string $num_fiche
     */
    public function accepter()
    {
        // Récupération du num_fiche depuis POST ou GET
        $num_fiche = $_POST['num_fiche'] ?? $_GET['num_fiche'] ?? null;
        $secur = $_POST['secur'] ?? 'SYSTEM';
        $adresse_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $port = $_SERVER['REMOTE_PORT'] ?? '';

        if (!$num_fiche) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'num_fiche manquant']);
            return;
        }

        require_once __DIR__ . '/Fiche.php';

        $ficheObj = new Fiche($this->pdo);

        // 1. Certifier conforme
        $ficheObj->certifierConformeFiche($num_fiche, $secur, $adresse_ip, $port);

        // 2. Approuver
        $ficheObj->approveFicheByNum($num_fiche, $secur);

        // 3. Valider 
        $success = $ficheObj->validerFicheByNum($num_fiche, $secur, $adresse_ip, $port);

        if ($success) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => "Demande carburant $num_fiche acceptée"]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => "Erreur lors de la validation de la fiche $num_fiche"]);
        }
    }

    public function refuser()
    {
        $num_fiche = $_POST['num_fiche'] ?? $_GET['num_fiche'] ?? null;
        $secur = $_POST['secur'] ?? 'SYSTEM';

        if (!$num_fiche) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'num_fiche manquant']);
            return;
        }

        require_once __DIR__ . '/Fiche.php';
        $ficheObj = new Fiche($this->pdo);

        // Mettre à jour la fiche comme refusée
        $data = [
            'etat_fiche' => 2,
            'approuve' => 2,
            'secur_desapprouve' => $secur,
            'date_desapprouve' => date('Y-m-d H:i:s')
        ];
        $fiche = $ficheObj->getByNumFiche($num_fiche);
        if (!$fiche) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Fiche non trouvée']);
            return;
        }
        $ficheObj->update($fiche['id_fiche'], $data);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => "Demande carburant $num_fiche refusée"]);
    }
}

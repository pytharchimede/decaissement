<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DemandeEssenceRepository.php';

class DemandeEssenceController
{
    public function getAll()
    {
        try {
            $repo = new DemandeEssenceRepository();
            $demandes = $repo->getAllActive();
            echo json_encode(['data' => $demandes]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur']);
        }
    }

    public function desactiver()
    {
        try {
            $repo = new DemandeEssenceRepository();
            $raw = file_get_contents('php://input') ?: '';
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            $input = [];
            if (stripos($ct, 'application/json') !== false) {
                $input = json_decode($raw, true) ?: [];
            } else {
                $input = $_POST ?: [];
                if (empty($input) && $raw) parse_str($raw, $input);
            }
            $codeBon = trim((string)($input['code_bon'] ?? ''));
            $motif = isset($input['motif']) ? trim((string)$input['motif']) : null;
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($codeBon === '' && $id > 0) {
                $codeBon = $repo->resolveCodeBonById($id);
            }
            if ($codeBon === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'code_bon requis']);
                return;
            }
            $updated = $repo->desactiverParCodeBon($codeBon, $motif);
            if ($updated === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Aucun bon trouvé pour ce code']);
            } else {
                echo json_encode(['status' => 'success', 'updated' => $updated]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur serveur']);
        }
    }
}

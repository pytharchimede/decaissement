<?php

class CamionFournisseurRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS camion_fournisseur (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matricule VARCHAR(100) NOT NULL UNIQUE,
            fournisseur VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $e) {
            // silencieux: si la table ne peut pas être créée, on ne bloque pas le chargement
        }
    }

    private function norm(string $matricule): string
    {
        $m = trim($matricule);
        // Normalisation simple: uppercase et suppression des espaces
        $m = strtoupper($m);
        $m = preg_replace('/\s+/', '', $m);
        return $m;
    }

    /**
     * Retourne un tableau associatif [matricule_normalise => fournisseur]
     */
    public function getAllMappings(): array
    {
        $out = [];
        try {
            $stmt = $this->pdo->query("SELECT matricule, fournisseur FROM camion_fournisseur");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[$this->norm($r['matricule'])] = $r['fournisseur'];
            }
        } catch (Throwable $e) {
        }
        return $out;
    }

    public function setMapping(string $matricule, string $fournisseur): bool
    {
        if ($matricule === '' || $fournisseur === '') return false;
        $mat = $this->norm($matricule);
        try {
            $stmt = $this->pdo->prepare("INSERT INTO camion_fournisseur (matricule, fournisseur) VALUES (:m, :f)
                ON DUPLICATE KEY UPDATE fournisseur = VALUES(fournisseur)");
            return $stmt->execute([':m' => $mat, ':f' => $fournisseur]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function deleteMapping(string $matricule): bool
    {
        $mat = $this->norm($matricule);
        try {
            $stmt = $this->pdo->prepare("DELETE FROM camion_fournisseur WHERE matricule = :m");
            return $stmt->execute([':m' => $mat]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

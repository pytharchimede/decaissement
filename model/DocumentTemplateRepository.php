<?php

require_once __DIR__ . '/Database.php';

class DocumentTemplateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getConnection();
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS document_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(200) NOT NULL,
            config JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo->exec($sql);
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, `key`, name, config, created_at, updated_at FROM document_templates ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, `key`, name, config FROM document_templates WHERE `key` = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(string $key, string $name, array $config): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO document_templates (`key`, name, config) VALUES (:k, :n, CAST(:c AS JSON))
            ON DUPLICATE KEY UPDATE name = VALUES(name), config = VALUES(config)');
        return $stmt->execute([
            ':k' => $key,
            ':n' => $name,
            ':c' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function delete(string $key): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM document_templates WHERE `key` = :k');
        return $stmt->execute([':k' => $key]);
    }
}

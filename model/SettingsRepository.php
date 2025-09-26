<?php
require_once __DIR__ . '/Database.php';

class SettingsRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(128) NOT NULL UNIQUE,
            value TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->pdo->exec($sql);
    }

    public function get(string $name): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => $name]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string)$val;
    }

    public function set(string $name, ?string $value): bool
    {
        $sql = 'INSERT INTO settings (name, value) VALUES (:n, :v)
                ON DUPLICATE KEY UPDATE value = VALUES(value)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':n' => $name, ':v' => $value]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT name, value FROM settings ORDER BY name');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$r['name']] = $r['value'];
        }
        return $out;
    }
}

<?php
class Database {
    private static $pdo;

    public static function getConnection() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    'mysql:host=localhost;dbname=fidestci_app_db',
                    'fidestci_ulrich',
                    '@Succes2019'
                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
?>

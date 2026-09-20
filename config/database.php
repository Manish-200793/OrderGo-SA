<?php
/**
 * OrderGo Database Connection Provider
 * Using PHP Data Objects (PDO) with MySQL
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = DB_HOST;
    $port = DB_PORT;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    try {
        // First, attempt to connect to the specific database
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        // If database does not exist (error 1049), connect without dbname and create it
        if ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database')) {
            try {
                $initDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $initPdo = new PDO($initDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $initPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Reconnect with new database
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                return $pdo;
            } catch (PDOException $initEx) {
                die("Database Initialization Error: " . htmlspecialchars($initEx->getMessage()));
            }
        }
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    }
}

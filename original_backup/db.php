<?php
// db.php: Handle SQLite database connection for local testing
header('Content-Type: application/json');
$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection failed."]);
    exit();
}
?>

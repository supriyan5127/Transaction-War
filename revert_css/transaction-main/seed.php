<?php
// seed.php: Setup database and create seed accounts
require_once __DIR__ . '/db.php';

// Create tables
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(255) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        balance REAL DEFAULT 100.0,
        bio TEXT,
        profile_image VARCHAR(255)
    );
    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER,
        receiver_id INTEGER,
        amount REAL NOT NULL,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    );
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        webpage VARCHAR(255),
        username VARCHAR(255),
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ");
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error creating schema: " . $e->getMessage()]);
    exit;
}

// Seed accounts
$seed_users = [
    ['username' => 'alice', 'email' => 'alice@example.com', 'password' => 'password123'],
    ['username' => 'bob', 'email' => 'bob@example.com', 'password' => 'password123'],
];

$stmt = $pdo->prepare("INSERT INTO users (username, email, password, balance) VALUES (:username, :email, :password, 100.0)");

foreach ($seed_users as $user) {
    $check = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
    $check->execute([$user['username']]);
    if (!$check->fetch()) {
        try {
            $stmt->execute([
                ':username' => $user['username'],
                ':email' => $user['email'],
                ':password' => password_hash($user['password'], PASSWORD_DEFAULT)
            ]);
        } catch (PDOException $e) {}
    }
}
echo json_encode(["status" => "success", "message" => "Database seeded locally for testing!"]);
?>

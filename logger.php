<?php
// logger.php: Logs user activity
require_once __DIR__ . '/db.php';

function log_activity($webpage, $username = 'Guest') {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (webpage, username) VALUES (?, ?)");
    try {
        $stmt->execute([$webpage, $username]);
    } catch (PDOException $e) {
        // Silently fail logging in production
    }
}
?>

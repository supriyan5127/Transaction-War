<?php
// logger.php: Logs user activity
require_once __DIR__ . '/db.php';

function log_activity($webpage, $username = 'Guest') {
    global $pdo;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (webpage, username, ip_address) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$webpage, $username, $ip_address]);
    } catch (PDOException $e) {
        // Silently fail logging in production to not disrupt user flow, or log to file
    }
}
?>

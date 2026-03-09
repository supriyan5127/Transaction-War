<?php
// logger.php: Logs user activity
require_once __DIR__ . '/db.php';

function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For can contain a list of IPs. The true client is usually the first.
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    }
    return $ip;
}

function log_activity($webpage, $username = 'Guest') {
    global $pdo;
    $ip_address = get_client_ip();
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (webpage, username, ip_address) VALUES (?, ?, ?)");
    try {
        $stmt->execute([$webpage, $username, $ip_address]);
    } catch (PDOException $e) {
        // Silently fail logging in production
    }
}
?>

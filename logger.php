<?php
// logger.php: Logs user activity
require_once __DIR__ . '/db.php';

function get_client_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',        // NGINX Proxy
        'HTTP_X_FORWARDED_FOR',  // Standard Proxy
        'REMOTE_ADDR'            // Fallback
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]); // Get the true client at the start of the list
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
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

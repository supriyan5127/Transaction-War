<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../logger.php';
session_start();

// 10-minute session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 600)) {
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$_SESSION['last_activity'] = time();

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'search') {
    $search = trim($_GET['q'] ?? '');
    if ($search === '') {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    if (is_numeric($search)) {
        $stmt = $pdo->prepare("SELECT id, username, profile_image FROM users WHERE LOWER(username) LIKE LOWER(?) OR id = ?");
        $stmt->execute(["%$search%", $search]);
    } else {
        $stmt = $pdo->prepare("SELECT id, username, profile_image FROM users WHERE LOWER(username) LIKE LOWER(?)");
        $stmt->execute(["%$search%"]);
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // log_activity is removed, so we won't log here
    echo json_encode(['status' => 'success', 'data' => $results]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    require_once __DIR__ . '/../crypto.php';
    
    $raw_input = file_get_contents('php://input');
    $input = decrypt_payload($raw_input);
    
    $receiver_identifier = trim($input['receiver'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $comment = trim($input['comment'] ?? '');

    if (empty($receiver_identifier) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiver or amount must be > 0.']);
        exit;
    }

    // Lock user for transaction checking
    $stmt = $pdo->prepare("SELECT id, balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_rec = $pdo->prepare("SELECT id, username FROM users WHERE id = ? OR LOWER(username) = LOWER(?)");
    $stmt_rec->execute([$receiver_identifier, $receiver_identifier]);
    $receiver = $stmt_rec->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        echo json_encode(['status' => 'error', 'message' => 'Receiver not found.']);
        exit;
    }
    if ($receiver['id'] == $user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Cannot transfer money to yourself.']);
        exit;
    }
    if ($sender['balance'] < $amount) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient balance. Current: Rs. ' . number_format($sender['balance'], 2)]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $deduct = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $deduct->execute([$amount, $user_id]);

        $add = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $add->execute([$amount, $receiver['id']]);

        $log_txn = $pdo->prepare("INSERT INTO transactions (sender_id, receiver_id, amount, comment) VALUES (?, ?, ?, ?)");
        $log_txn->execute([$user_id, $receiver['id'], $amount, $comment]);

        $pdo->commit();
        log_activity('api/transfer/send', $_SESSION['username']);
        
        echo json_encode(['status' => 'success', 'message' => 'Successfully transferred Rs. ' . number_format($amount, 2) . ' to ' . htmlspecialchars($receiver['username'])]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Transfer failed due to server error.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

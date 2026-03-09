<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../logger.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, username, email, balance, bio, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        log_activity('api/user/profile', $user['username']);
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    exit;
}

if ($action === 'public_profile' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_id = $_GET['id'] ?? null;
    if (!$target_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID is required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, bio, profile_image FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($target) {
        echo json_encode(['status' => 'success', 'data' => $target]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
    exit;
}

if ($action === 'transactions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT t.*, u1.username as sender, u2.username as receiver 
                           FROM transactions t
                           LEFT JOIN users u1 ON t.sender_id = u1.id
                           LEFT JOIN users u2 ON t.receiver_id = u2.id
                           WHERE t.sender_id = ? OR t.receiver_id = ?
                           ORDER BY t.created_at DESC LIMIT 15");
    $stmt->execute([$user_id, $user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $transactions, 'current_id' => $user_id]);
    exit;
}

// Update profile including file upload
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email cannot be empty']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
        exit;
    }

    // Get current profile image
    $stmt_img = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt_img->execute([$user_id]);
    $profile_image = $stmt_img->fetchColumn();

    // Handle upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
        
        $tmp_name = $_FILES['profile_image']['tmp_name'];
        $name = basename($_FILES['profile_image']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                if ($profile_image && file_exists(__DIR__ . '/../' . $profile_image)) {
                    @unlink(__DIR__ . '/../' . $profile_image);
                }
                $profile_image = 'uploads/' . $new_name;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload image']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
            exit;
        }
    }

    $update = $pdo->prepare("UPDATE users SET email = ?, bio = ?, profile_image = ? WHERE id = ?");
    $update->execute([$email, $bio, $profile_image, $user_id]);
    log_activity('api/user/update', $_SESSION['username']);
    
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully', 'profile_image' => $profile_image]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

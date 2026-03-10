<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../logger.php';
session_start();

// 10-minute session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 600)) {
    session_unset();
    session_destroy();
    if (isset($_GET['action']) && $_GET['action'] !== 'login' && $_GET['action'] !== 'register') {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit;
    }
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../crypto.php';
    
    $raw_input = file_get_contents('php://input');
    $input = decrypt_payload($raw_input);

    if ($action === 'register') {
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
            exit;
        }

        if (!str_ends_with(strtolower($email), '@gmail.com')) {
            echo json_encode(['status' => 'error', 'message' => 'Only @gmail.com email addresses are allowed for registration.']);
            exit;
        }

        // Strict Password Validation
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[^a-zA-Z\d\s:]/', $password)) {
             echo json_encode([
                 'status' => 'error', 
                 'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one special symbol.'
             ]);
             exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Username or Email already exists.']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, balance) VALUES (?, ?, ?, 100.0)");
        try {
            $insert->execute([$username, $email, $hash]);
            log_activity('api/auth/register', $username);
            echo json_encode(['status' => 'success', 'message' => 'Register Successful. Welcome! You have been credited Rs. 100.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed. ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Username and password required.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            log_activity('api/auth/login', $username);
            echo json_encode([
                'status' => 'success', 
                'message' => 'Welcome ' . htmlspecialchars($user['username']) . '!', 
                'user' => ['id' => $user['id'], 'username' => $user['username']]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid username or password.']);
        }
        exit;
    }
    
    if ($action === 'logout') {
        log_activity('api/auth/logout', $_SESSION['username'] ?? 'Guest');
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'check') {
    if (isset($_SESSION['user_id'])) {
        $remaining = 600 - (time() - $_SESSION['login_time']);
        if ($remaining < 0) $remaining = 0;
        echo json_encode(['status' => 'success', 'user' => ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username']], 'remaining' => $remaining]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);

<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    unset($_SESSION['username']);
    echo json_encode(['success' => true, 'message' => 'Kijelentkezve']);
    exit;
}

require_once "../config/db.php";
require_once "../config/jwt_helper.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$email_or_username = trim($input['email_or_username'] ?? '');
$password = $input['password'] ?? '';

if (empty($email_or_username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Email/felhasználónév és jelszó megadása kötelező'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT id, username, email, password_hash, full_name, role, is_active
        FROM users
        WHERE (username = ? OR email = ?) AND is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email_or_username, $email_or_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Hibás bejelentkezési adatok'
        ]);
        exit;
    }

    $updateStmt = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();
    $updateStmt->close();

    $token = createJWT([
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ]);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['username'] = $user['username'];

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt a bejelentkezés során'
    ]);
}

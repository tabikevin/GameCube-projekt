<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$full_name = trim($input['full_name'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';
$password_confirm = $input['password_confirm'] ?? '';

$errors = [];

if (empty($full_name)) {
    $errors[] = 'A teljes név megadása kötelező';
}

if (strlen($username) < 3) {
    $errors[] = 'A felhasználónév legalább 3 karakter legyen';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Érvényes e-mail cím megadása kötelező';
}

if (strlen($password) < 6) {
    $errors[] = 'A jelszó legalább 6 karakter legyen';
}

if ($password !== $password_confirm) {
    $errors[] = 'A két jelszó nem egyezik';
}

if (!empty($phone) && strlen($phone) < 7) {
    $errors[] = 'A telefonszám legalább 7 karakter legyen';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'A felhasználónév vagy e-mail cím már foglalt'
        ]);
        exit;
    }
    $stmt->close();

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';
    $is_active = 1;

    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, full_name, phone, role, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssi", $username, $email, $password_hash, $full_name, $phone, $role, $is_active);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.'
        ]);
    } else {
        throw new Exception('Registration failed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Hiba történt a regisztráció során'
    ]);
}

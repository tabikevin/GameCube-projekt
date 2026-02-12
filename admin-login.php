<?php
header("Content-Type: application/json");
require_once "../../config/admin_auth.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Add meg a jelszót!']);
    exit;
}

if (adminLogin($password)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Hibás jelszó!']);
}

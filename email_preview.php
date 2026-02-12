<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$safeName    = $_GET['name']    ?? "Teszt Felhasználó";
$safeEmail   = $_GET['email']   ?? "teszt@example.com";
$safeSubject = $_GET['subject'] ?? "Általános kérdés";
$safeMessage = $_GET['message'] ?? "Sziasztok! Érdeklődnék, hogy a Cyberpunk 2077 kulcs Steam-re vagy GOG-ra szól? Illetve mennyi ideig érvényes a kulcs aktiválás után?\n\nKöszönöm előre is a választ!";
$dateTime    = date('Y. m. d. H:i');

echo json_encode([
    'success' => true,
    'data' => [
        'name'    => htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8'),
        'email'   => htmlspecialchars($safeEmail, ENT_QUOTES, 'UTF-8'),
        'subject' => htmlspecialchars($safeSubject, ENT_QUOTES, 'UTF-8'),
        'message' => nl2br(htmlspecialchars($safeMessage, ENT_QUOTES, 'UTF-8')),
        'date'    => $dateTime
    ]
], JSON_UNESCAPED_UNICODE);

<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Csak POST kérés engedélyezett']);
    exit;
}

require_once "../config/db.php";
require_once "../config/smtp_mailer.php";

$gmailAddress  = "Gamecube172604@gmail.com";
$gmailAppPass  = "acyw cyeg zpnf inmc";
$receiverEmail = "Gamecube172604@gmail.com";

$data = json_decode(file_get_contents("php://input"), true);

$name    = trim($data['name'] ?? '');
$email   = trim($data['email'] ?? '');
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');

if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Minden mező kitöltése kötelező!']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen email cím!']);
    exit;
}

if (mb_strlen($message) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Az üzenet legalább 10 karakter legyen!']);
    exit;
}

$subjectLabels = [
    'general'   => 'Általános kérdés',
    'order'     => 'Rendeléssel kapcsolatos',
    'technical' => 'Technikai probléma',
    'complaint' => 'Reklamáció',
    'other'     => 'Egyéb'
];
$subjectLabel = $subjectLabels[$subject] ?? $subject;

try {
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $subjectLabel, $message);
    $stmt->execute();
    $stmt->close();
} catch (Exception $e) {
    $conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $name, $email, $subjectLabel, $message);
    $stmt->execute();
    $stmt->close();
}

$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeSubject = htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8');
$dateTime = date('Y. m. d. H:i');

$emailBody = <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin: 0; padding: 0; background-color: #0f0520; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #0f0520 0%, #1a0b2e 100%); padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e1040, #2d1560); border-radius: 16px 16px 0 0; padding: 30px 40px; border-bottom: 2px solid #a855f7; text-align: center;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #a855f7, #06b6d4); border-radius: 12px; display: inline-block; margin-bottom: 15px;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 800; letter-spacing: 2px;">GameCube</h1>
                                        <p style="margin: 8px 0 0; color: #a855f7; font-size: 13px; text-transform: uppercase; letter-spacing: 3px;">Új üzenet érkezett</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- TÁRGY BANNER -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #a855f7, #7c3aed); padding: 16px 40px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 16px; font-weight: 600;">📋 {$safeSubject}</p>
                        </td>
                    </tr>

                    <!-- BODY -->
                    <tr>
                        <td style="background-color: #160a2e; padding: 35px 40px;">
                            
                            <!-- Feladó info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 12px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding-bottom: 12px;">
                                                    <span style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">👤 Feladó neve</span><br>
                                                    <span style="color: #f1f5f9; font-size: 16px; font-weight: 600;">{$safeName}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 12px; border-top: 1px solid rgba(168, 85, 247, 0.15); padding-top: 12px;">
                                                    <span style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">📧 Email cím</span><br>
                                                    <a href="mailto:{$safeEmail}" style="color: #06b6d4; font-size: 16px; font-weight: 600; text-decoration: none;">{$safeEmail}</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="border-top: 1px solid rgba(168, 85, 247, 0.15); padding-top: 12px;">
                                                    <span style="color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">🕐 Időpont</span><br>
                                                    <span style="color: #f1f5f9; font-size: 15px;">{$dateTime}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Üzenet -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 25px;">
                                <tr>
                                    <td>
                                        <p style="color: #a855f7; font-size: 13px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 12px; font-weight: 700;">💬 Üzenet tartalma</p>
                                        <div style="background: rgba(255, 255, 255, 0.03); border-left: 3px solid #a855f7; border-radius: 0 12px 12px 0; padding: 20px 25px;">
                                            <p style="color: #e2e8f0; font-size: 15px; line-height: 1.7; margin: 0;">{$safeMessage}</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Válasz gomb -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-top: 10px;">
                                        <a href="mailto:{$safeEmail}?subject=RE: {$safeSubject} - GameCube" 
                                           style="display: inline-block; background: linear-gradient(135deg, #a855f7, #7c3aed); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 10px; font-size: 15px; font-weight: 700; letter-spacing: 0.5px;">
                                            ✉️ Válasz küldése
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background-color: #0d0620; border-radius: 0 0 16px 16px; padding: 25px 40px; border-top: 1px solid rgba(168, 85, 247, 0.15); text-align: center;">
                            <p style="margin: 0 0 5px; color: #64748b; font-size: 12px;">Ez az üzenet a GameCube weboldal kapcsolati űrlapjáról érkezett.</p>
                            <p style="margin: 0; color: #475569; font-size: 11px;">© 2026 GameCube - Digitális Játék Kulcsok</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

// Email küldés
$emailSubject = "[GameCube Kapcsolat] " . $subjectLabel . " - " . $name;

$mailer = new SmtpMailer('smtp.gmail.com', 465, $gmailAddress, $gmailAppPass);
$mailer->setHtml(true);
$result = $mailer->send(
    $gmailAddress,
    "GameCube Weboldal",
    $receiverEmail,
    $emailSubject,
    $emailBody,
    $email
);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Üzeneted sikeresen elküldtük! Hamarosan válaszolunk.'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Üzeneted rögzítettük! Hamarosan válaszolunk.',
        'mail_error' => $result['error'] ?? null
    ]);
}

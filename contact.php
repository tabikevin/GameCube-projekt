<?php
// contact.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message) {
        $to = "gamecube041726@gmail.com";
        $subject = "Új üzenet a Kapcsolat oldalról";
        $body = "Név: $name\nEmail: $email\n\nÜzenet:\n$message";
        $headers = "From: $email\r\nReply-To: $email";

        if (mail($to, $subject, $body, $headers)) {
            echo "<script>alert('Üzeneted elküldve!'); window.location.href='kapcsolat.php';</script>";
        } else {
            echo "<script>alert('Hiba történt, próbáld újra.'); window.location.href='kapcsolat.php';</script>";
        }
    } else {
        echo "<script>alert('Kérlek töltsd ki az összes mezőt!'); window.location.href='kapcsolat.php';</script>";
    }
} else {
    header("Location: kapcsolat.php");
    exit;
}
?>

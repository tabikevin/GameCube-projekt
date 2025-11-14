<?php
$db_host = "localhost";
$db_user = "root";
$db_password = "root";
$db_name = "gamecube";
$db_port = 8889;

$conn = new mysqli($db_host, $db_user, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die("Adatbázis hiba.");
}

$conn->set_charset("utf8mb4");
?>

<?php
header("Content-Type: application/json");
require_once "../../config/admin_auth.php";

echo json_encode(['admin' => isAdminLoggedIn()]);

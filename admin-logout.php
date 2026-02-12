<?php
header("Content-Type: application/json");
require_once "../../config/admin_auth.php";

adminLogout();
echo json_encode(['success' => true]);

<?php
session_start();

define('ADMIN_PASSWORD', 'Gamecube041726');

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function adminLogin(string $password): bool {
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function adminLogout(): void {
    session_unset();
    session_destroy();
}

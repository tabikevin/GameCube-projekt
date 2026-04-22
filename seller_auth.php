<?php
function getSellerAuth($pdo) {
    require_once __DIR__ . "/jwt_helper.php";

    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $hdrs = apache_request_headers();
        foreach ($hdrs as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    } elseif (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    }

    if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        $p = verifyJWT($m[1]);
        if ($p) {
            $role = $p['role'] ?? ($p['data']['role'] ?? '');
            if (in_array($role, ['seller', 'admin'])) {
                return ['user_id' => (int)($p['user_id'] ?? 0), 'role' => $role, 'username' => $p['username'] ?? ''];
            }
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_role'])) {
        $role = $_SESSION['user_role'];
        if (in_array($role, ['seller', 'admin'])) {
            return ['user_id' => (int)$_SESSION['user_id'], 'role' => $role, 'username' => $_SESSION['username'] ?? ''];
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, role, username FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $u = $stmt->fetch();
            if ($u && in_array($u['role'], ['seller', 'admin'])) {
                return ['user_id' => (int)$u['id'], 'role' => $u['role'], 'username' => $u['username']];
            }
        } catch (Exception $e) {}
    }

    return null;
}

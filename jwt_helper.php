<?php
$JWT_SECRET = "GcUbE_2026_BackendAuth_9f3KxA7Qm2!";
$JWT_ISSUER = "gamecube";
$JWT_EXPIRE = 3600; 

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function createJWT($payload) {
    global $JWT_SECRET, $JWT_ISSUER, $JWT_EXPIRE;

    $header = ["alg" => "HS256", "typ" => "JWT"];
    $payload["iss"] = $JWT_ISSUER;
    $payload["exp"] = time() + $JWT_EXPIRE;

    $h = base64UrlEncode(json_encode($header));
    $p = base64UrlEncode(json_encode($payload));

    $s = base64UrlEncode(
        hash_hmac("sha256", "$h.$p", $JWT_SECRET, true)
    );

    return "$h.$p.$s";
}

function verifyJWT($token) {
    global $JWT_SECRET;

    $parts = explode(".", $token);
    if (count($parts) !== 3) return false;

    [$h, $p, $s] = $parts;

    $check = base64UrlEncode(
        hash_hmac("sha256", "$h.$p", $JWT_SECRET, true)
    );

    if (!hash_equals($check, $s)) return false;

    $data = json_decode(base64UrlDecode($p), true);
    if (!$data || $data["exp"] < time()) return false;

    return $data;
}

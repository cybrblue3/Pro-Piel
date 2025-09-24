<?php
// auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

function require_role($roles) {
    if (!is_array($roles)) $roles = [$roles];
    $r = $_SESSION['role'] ?? null;
    if (!in_array($r, $roles)) {
        http_response_code(403);
        echo "Acceso no autorizado.";
        exit;
    }
}

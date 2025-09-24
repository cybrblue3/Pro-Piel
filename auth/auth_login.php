<?php
// auth/auth_login.php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// CSRF
$csrf_post = $_POST['csrf_token'] ?? '';
if (empty($csrf_post) || $csrf_post !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: ../login.php?error=1');
    exit;
}

$userInput = trim($_POST['user'] ?? '');
$password = $_POST['password'] ?? '';
if ($userInput === '' || $password === '') {
    header('Location: ../login.php?error=1');
    exit;
}

// Buscar por email o phone (limpiar espacios para phone)
$userInputClean = preg_replace('/\s+/', '', $userInput);

$stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, specialty_id, phone FROM users WHERE email = ? OR phone = ? LIMIT 1");
$stmt->execute([$userInput, $userInputClean]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../login.php?error=1');
    exit;
}

// Revisar is_active si existe
try {
    $colStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'is_active'");
    $colStmt->execute();
    $hasIsActive = (bool)$colStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $hasIsActive = false;
}
if ($hasIsActive) {
    $s2 = $pdo->prepare("SELECT is_active FROM users WHERE id = ? LIMIT 1");
    $s2->execute([(int)$user['id']]);
    $row2 = $s2->fetch(PDO::FETCH_ASSOC);
    if ($row2 && isset($row2['is_active']) && !$row2['is_active']) {
        header('Location: ../login.php?error=1');
        exit;
    }
}

// Verificar contraseña
if (!password_verify($password, $user['password_hash'])) {
    header('Location: ../login.php?error=1');
    exit;
}

// Login correcto
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['role'] = $user['role'];
$_SESSION['specialty_id'] = $user['specialty_id'] ?? null;
$_SESSION['user_phone'] = $user['phone'] ?? null;

// Asegurar CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));

// Actualizar last_login (si existe)
try {
    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
} catch (Exception $e) {
    // silent
}

// Redirigir
if ($_SESSION['role'] === 'admin') header('Location: ../admin/dashboard.php');
else header('Location: ../med/dashboard.php');

exit;

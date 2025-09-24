<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    else header('Location: med/dashboard.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Propiel - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="card mx-auto" style="max-width:420px;">
      <div class="card-body">
        <h4 class="card-title mb-3">Acceso al sistema</h4>

        <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-danger">Credenciales inválidas.</div>
        <?php endif; ?>

        <form method="POST" action="auth/auth_login.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
          <div class="mb-3">
            <label class="form-label">Usuario (correo o teléfono)</label>
            <input name="user" type="text" class="form-control" required autofocus placeholder="correo@ejemplo.com o 7551234567">
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Entrar</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>

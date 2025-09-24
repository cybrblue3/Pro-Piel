<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$user_name = $_SESSION['user_name'] ?? 'Usuario';
$role = $_SESSION['role'] ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Propiel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="/admin/dashboard.php">Panel</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin/create_user.php">Usuarios</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Especialidades</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Reportes</a></li>
        <?php elseif ($role === 'medic'): ?>
          <li class="nav-item"><a class="nav-link" href="/med/dashboard.php">Agenda</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Mis pacientes</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Recetas</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Asistencias</a></li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item pe-3"><span class="navbar-text small text-muted"><?=htmlspecialchars($user_name)?></span></li>
        <li class="nav-item"><a class="btn btn-outline-secondary btn-sm" href="/xampp/propiel/auth/logout.php">Salir</a></li>
      </ul>
    </div>
  </div>
</nav>

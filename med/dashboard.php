<?php
require_once __DIR__ . '/../auth_check.php';
require_role('medic');
require_once __DIR__ . '/../config/db.php';

$spec_id = $_SESSION['specialty_id'] ?? null;
$uid = $_SESSION['user_id'];
// Intentamos usar doctor_id si existe; sino filtramos por specialty_id
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Médico — Agenda</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../partials/nav.php'; ?>

<div class="container py-4">
  <h3>Agenda de <?=htmlspecialchars($_SESSION['user_name'] ?? '')?></h3>
  <p class="text-muted">Especialidad: <?= htmlspecialchars($spec_id ?? 'No asignada') ?></p>

  <div class="card mb-4">
    <div class="card-body">
      <h5>Próximas citas</h5>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead><tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Tel</th><th>Estado</th></tr></thead>
          <tbody>
<?php
// Consulta inteligente: si appointments tiene doctor_id usamos eso, si no filtramos por specialty_id
$hasDoctorCol = false;
try {
    $c = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'doctor_id'")->fetch(PDO::FETCH_ASSOC);
    $hasDoctorCol = (bool)$c;
} catch (Exception $e) { $hasDoctorCol = false; }

// IMPORTANT: mostrar SOLO citas confirmadas/atendidas y pacientes confirmados (is_confirmed = 1)
// Esto evita que el médico vea citas pendientes por confirmar
if ($hasDoctorCol) {
    $stmt = $pdo->prepare("
        SELECT a.appointment_date, a.appointment_time, p.full_name AS patient_name, p.phone AS patient_phone, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        WHERE a.doctor_id = :uid
          AND a.status IN ('confirmed','attended')
          AND p.is_confirmed = 1
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 50
    ");
    $stmt->execute(['uid'=>$uid]);
} else {
    // fallback: filtrar por specialty_id
    $stmt = $pdo->prepare("
        SELECT a.appointment_date, a.appointment_time, p.full_name AS patient_name, p.phone AS patient_phone, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        WHERE a.specialty_id = :spec
          AND a.status IN ('confirmed','attended')
          AND p.is_confirmed = 1
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 50
    ");
    $stmt->execute(['spec'=>$spec_id]);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "<tr><td colspan='5' class='text-muted'>No hay próximas citas</td></tr>";
} else {
    foreach ($rows as $r) {
        echo "<tr><td>".htmlspecialchars($r['appointment_date'])."</td><td>".htmlspecialchars($r['appointment_time'])."</td><td>".htmlspecialchars($r['patient_name'])."</td><td>".htmlspecialchars($r['patient_phone'])."</td><td>".htmlspecialchars($r['status'])."</td></tr>";
    }
}
?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- tarjetas rápidas -->
  <div class="row">
    <div class="col-md-6">
      <div class="card p-3 mb-3">
        <h6>Pacientes recientes</h6>
        <ul class="list-group list-group-flush">
<?php
// listar últimos pacientes atendidos por esta especialidad
$stmt2 = $pdo->prepare("
  SELECT p.id, p.full_name, p.phone
  FROM patients p
  JOIN appointments a ON a.patient_id = p.id
  WHERE a.specialty_id = :spec
    AND a.status IN ('confirmed','attended')
    AND p.is_confirmed = 1
  GROUP BY p.id
  ORDER BY MAX(a.appointment_date) DESC
  LIMIT 10
");
$stmt2->execute(['spec'=>$spec_id]);
$ps = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (!$ps) {
    echo "<li class='list-group-item text-muted'>No hay pacientes</li>";
} else {
    foreach ($ps as $p) {
        echo "<li class='list-group-item'>".htmlspecialchars($p['full_name'])." <small class='text-muted'>".htmlspecialchars($p['phone'])."</small></li>";
    }
}
?>
        </ul>
      </div>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

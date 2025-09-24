<?php
session_start();
include("../config/db.php");

function bad($msg) {
    echo "<!DOCTYPE html><html lang='es'><head>
        <meta charset='UTF-8'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head><body class='bg-light'>
        <div class='container mt-5'>
          <div class='alert alert-danger'>
            <h4 class='alert-heading'>Error</h4>
            <p>".htmlspecialchars($msg)."</p>
            <hr>
            <a href='paso1_datos.php' class='btn btn-secondary'>Volver al formulario</a>
          </div>
        </div></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: paso1_datos.php");
    exit;
}

// CSRF
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    bad("Token inválido. Intenta recargar la página.");
}

// Recibir y sanitizar
$full_name = trim($_POST['full_name'] ?? '');
$birth_date = $_POST['birth_date'] ?? '';
$sex = $_POST['sex'] ?? '';
if (!in_array($sex, ['M','F'])) bad("Sexo inválido. Por favor selecciona Masculino o Femenino.");
$phone = trim($_POST['phone'] ?? '');
$phone_cc = trim($_POST['phone_cc'] ?? '');
$phone_full = trim($_POST['phone_full'] ?? '');
$email = trim($_POST['email'] ?? '');
$is_first = isset($_POST['is_first_time']) ? (int)$_POST['is_first_time'] : 0;
$specialty_id = (int)($_POST['specialty_id'] ?? 0);
$appointment_date = $_POST['date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';

// Si phone_full no viene, construir fallback (si tenemos lada)
if (empty($phone_full) && $phone !== '') {
    $digits = preg_replace('/\D+/', '', $phone);
    $cc = preg_replace('/\D+/', '', $phone_cc);
    if ($cc) $phone_full = $cc . $digits;
    else $phone_full = $digits; // al menos phone local
}

// Validaciones
if (!preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]{7,}$/u', $full_name)) bad("Nombre inválido.");
if (!preg_match('/^\d{10}$/', $phone)) bad("Teléfono inválido.");
$allowed_domains = ["gmail.com","hotmail.com","outlook.com","icloud.com","yahoo.com"];
if ($email !== '') {
    $parts = explode('@', $email);
    if (count($parts) !== 2 || !in_array(strtolower($parts[1]), $allowed_domains)) bad("Email no permitido.");
}
$bd = DateTime::createFromFormat('Y-m-d', $birth_date);
if (!$bd) bad("Fecha de nacimiento inválida.");
$today = new DateTime();
$age = $today->diff($bd)->y;
if ($age < 0 || $age > 95) bad("Edad inválida.");

$ad = DateTime::createFromFormat('Y-m-d', $appointment_date);
if (!$ad) bad("Fecha de cita inválida.");
$today->setTime(0,0,0);
$ad2 = clone $ad; $ad2->setTime(0,0,0);
if ($ad2 < $today) bad("No se permiten fechas pasadas.");
if ((int)$ad->format('N') === 7) bad("No se permiten citas en domingo.");
if (empty($appointment_time)) bad("Debes seleccionar una hora.");

// Verificar especialidad
$stmt = $pdo->prepare("SELECT id,name FROM specialties WHERE id = ?");
$stmt->execute([$specialty_id]);
$spec = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$spec) bad("Especialidad inválida.");

// Normalizar nombre de especialidad y, si es Dermatología, exigir radio is_first_time
$specNameNormalized = strtolower( iconv('UTF-8', 'ASCII//TRANSLIT', $spec['name']) );
if (strpos($specNameNormalized, 'dermatolog') !== false) {
    if (!isset($_POST['is_first_time'])) {
        bad("Para Dermatología debes indicar si es tu primera visita.");
    } else {
        $is_first = (int) $_POST['is_first_time'];
        if (!in_array($is_first, [0,1])) bad("Valor inválido para 'primera visita'.");
    }
}

// Verificar bloqueos
$stmt = $pdo->prepare("SELECT * FROM schedule_exceptions WHERE specialty_id = ? AND exception_date = ? AND type='blocked'");
$stmt->execute([$specialty_id, $appointment_date]);
if ($stmt->fetch()) bad("Ese día está bloqueado.");

// Verificar si slot ya ocupado
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments
    WHERE specialty_id = :spec AND appointment_date = :fecha AND appointment_time = :hora
      AND status IN ('pending','confirmed','attended')");
$stmt->execute(['spec'=>$specialty_id,'fecha'=>$appointment_date,'hora'=>$appointment_time]);
if ($stmt->fetchColumn() > 0) bad("Horario ya reservado.");

// Verificar holds activos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointment_holds
    WHERE specialty_id = :spec AND appointment_date = :fecha AND appointment_time = :hora
      AND expires_at > NOW()");
$stmt->execute(['spec'=>$specialty_id,'fecha'=>$appointment_date,'hora'=>$appointment_time]);
if ($stmt->fetchColumn() > 0) bad("El horario está en proceso de reserva. Intenta otro.");

// Guardar en sesión (incluimos phone_cc y phone_full)
$_SESSION['patient'] = [
    'full_name' => $full_name,
    'birth_date' => $birth_date,
    'sex' => $sex,
    'phone' => $phone,
    'phone_cc' => $phone_cc,
    'phone_full' => $phone_full,
    'email' => $email,
    'is_first_time' => $is_first,
    'specialty_id' => $specialty_id,
    'specialty_name' => $spec['name'],
    'date' => $appointment_date,
    'time' => $appointment_time
];

// Crear hold temporal
$token = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("INSERT INTO appointment_holds
    (temp_token, specialty_id, appointment_date, appointment_time, expires_at, session_info)
    VALUES (:tok,:spec,:fecha,:hora, DATE_ADD(NOW(), INTERVAL 10 MINUTE), :info)");
$stmt->execute([
    'tok' => $token,
    'spec' => $specialty_id,
    'fecha' => $appointment_date,
    'hora' => $appointment_time,
    'info' => $phone
]);
$_SESSION['hold_token'] = $token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmar cita</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow p-4">
      <h3 class="mb-3">Confirmación de cita</h3>
      <p><strong>Paciente:</strong> <?=htmlspecialchars($full_name)?></p>
      <p><strong>Especialidad:</strong> <?=htmlspecialchars($spec['name'])?></p>
      <p><strong>Fecha:</strong> <?=htmlspecialchars($appointment_date)?></p>
      <p><strong>Hora:</strong> <?=htmlspecialchars($appointment_time)?></p>
      <div class="mt-4">
        <form method="POST" action="paso3_pago.php" class="d-inline">
          <button type="submit" class="btn btn-success">Confirmar y continuar a pago</button>
        </form>
        <a href="paso1_datos.php" class="btn btn-secondary">Volver</a>
      </div>
    </div>
  </div>
</body>
</html>

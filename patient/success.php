<?php
session_start();
if (!isset($_SESSION['patient'])) {
    header("Location: paso1_datos.php");
    exit;
}

$patient = $_SESSION['patient'];

// Limpieza: ya no necesitamos mantener la sesión cargada
unset($_SESSION['patient']);
unset($_SESSION['hold_token']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cita confirmada</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow p-4 text-center" style="max-width:700px; margin:auto;">
      <h3 class="mb-3 text-success">✅ ¡Cita confirmada con éxito!</h3>
      <p>Gracias <strong><?=htmlspecialchars($patient['full_name'])?></strong>, hemos recibido tu comprobante de pago.</p>
      <p>Tu cita ha quedado registrada correctamente:</p>

      <ul class="list-group mb-4">
        <li class="list-group-item"><strong>Especialidad:</strong> <?=htmlspecialchars($patient['specialty_name'])?></li>
        <li class="list-group-item"><strong>Fecha:</strong> <?=htmlspecialchars($patient['date'])?></li>
        <li class="list-group-item"><strong>Hora:</strong> <?=htmlspecialchars($patient['time'])?></li>
      </ul>

      <div class="alert alert-info">
        Te contactaremos por WhatsApp o correo para confirmar cualquier detalle adicional.
      </div>

      <a href="paso1_datos.php" class="btn btn-primary">Agendar otra cita</a>
    </div>
  </div>
</body>
</html>

<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['patient']) || !isset($_SESSION['hold_token'])) {
    header("Location: paso1_datos.php");
    exit;
}

$patient = $_SESSION['patient'];

// Leer configuración bancaria desde la BD
$stmt = $pdo->query("SELECT * FROM payment_config ORDER BY id DESC LIMIT 1");
$bank_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bank_info) {
    die("⚠️ Error: No hay configuración bancaria definida en la base de datos.");
}

// Generar referencia dinámica única por sesión
$referencia = $bank_info['reference_prefix'] . '-' . strtoupper(substr(session_id(), -6));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Subir comprobante de pago</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow p-4" style="max-width:700px; margin:auto;">
      <h3 class="mb-3">Pago de tu cita</h3>

      <!-- Resumen de cita -->
      <div class="mb-4">
        <h5>Detalles de la cita</h5>
        <ul class="list-group">
          <li class="list-group-item"><strong>Paciente:</strong> <?=htmlspecialchars($patient['full_name'])?></li>
          <li class="list-group-item"><strong>Especialidad:</strong> <?=htmlspecialchars($patient['specialty_name'])?></li>
          <li class="list-group-item"><strong>Fecha:</strong> <?=htmlspecialchars($patient['date'])?></li>
          <li class="list-group-item"><strong>Hora:</strong> <?=htmlspecialchars($patient['time'])?></li>
        </ul>
      </div>

      <!-- Datos bancarios -->
      <div class="mb-4">
        <h5>Datos bancarios para el pago</h5>
        <div class="alert alert-info">
          <p><strong>Banco:</strong> <?=htmlspecialchars($bank_info['bank_name'])?></p>
          <p><strong>Titular:</strong> <?=htmlspecialchars($bank_info['account_holder'])?></p>
          <p><strong>Cuenta:</strong> <?=htmlspecialchars($bank_info['account_number'])?></p>
          <p><strong>CLABE:</strong> <?=htmlspecialchars($bank_info['clabe'])?></p>
          <p><strong>Referencia:</strong> <?=htmlspecialchars($referencia)?></p>
        </div>
        <p class="text-muted">Por favor realiza el pago y conserva tu comprobante para subirlo a continuación.</p>
      </div>

      <!-- Subida de comprobante -->
      <form action="../api/upload_payment.php" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="comprobante" class="form-label">Subir comprobante de pago</label>
          <input type="file" class="form-control" name="comprobante" id="comprobante"
                 accept=".jpg,.jpeg,.png,.pdf" required>
          <div class="form-text">Formatos permitidos: JPG, PNG o PDF. Máx: 5MB</div>
        </div>

        <button type="submit" class="btn btn-success">Enviar comprobante</button>
        <a href="paso2_confirmacion.php" class="btn btn-secondary">Volver</a>
      </form>
    </div>
  </div>
</body>
</html>

<?php
session_start();
require("../config/db.php");

// Verificamos que venga de un flujo válido
if (!isset($_SESSION['appointment_id']) || !isset($_SESSION['patient'])) {
    die("Sesión inválida. Reinicia el proceso.");
}

$patient = $_SESSION['patient'];
$appointment_id = $_SESSION['appointment_id'];

// Consultamos la cita para sacar fecha, hora, especialidad
$stmt = $pdo->prepare("
    SELECT a.appointment_date, a.appointment_time, s.name AS specialty
    FROM appointments a
    JOIN specialties s ON a.specialty_id = s.id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("No se encontró la cita.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Consentimiento informado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #signature-pad {
      border: 1px solid #ccc;
      display: block;
      margin-bottom: 10px;
      cursor: crosshair;
    }
  </style>
</head>
<body class="container py-4">
  <h2 class="mb-3 text-center">Consentimiento informado</h2>

  <p>
    Yo, <strong><?= htmlspecialchars($patient['full_name']) ?></strong>, 
    con cita en la especialidad de <strong><?= htmlspecialchars($appointment['specialty']) ?></strong> 
    el día <strong><?= htmlspecialchars($appointment['appointment_date']) ?></strong> 
    a las <strong><?= htmlspecialchars($appointment['appointment_time']) ?></strong>,
    acepto voluntariamente el procedimiento médico y firmo este consentimiento.
  </p>

  <p class="text-muted">
    He sido informado sobre los beneficios, riesgos y alternativas del procedimiento, 
    y todas mis dudas fueron respondidas satisfactoriamente.
  </p>

  <!-- Canvas para firma -->
  <div class="mb-3">
    <label class="form-label">Firma del paciente:</label><br>
    <canvas id="signature-pad" width="400" height="150"></canvas>
    <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="clearPad()">Borrar firma</button>
  </div>

  <!-- Formulario oculto para enviar firma -->
  <form id="consent-form" method="post" action="../api/save_consent.php">
    <input type="hidden" name="signature" id="signature">
    <button type="submit" class="btn btn-success">Guardar consentimiento</button>
  </form>

  <script>
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    let drawing = false;

    // Iniciar dibujo
    canvas.addEventListener('mousedown', (e) => {
      drawing = true;
      ctx.beginPath();
      ctx.moveTo(e.offsetX, e.offsetY);
    });

    // Terminar dibujo
    canvas.addEventListener('mouseup', () => { drawing = false; });

    // Dibujar
    canvas.addEventListener('mousemove', (e) => {
      if (!drawing) return;
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.strokeStyle = '#000';
      ctx.lineTo(e.offsetX, e.offsetY);
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(e.offsetX, e.offsetY);
    });

    // Limpiar firma
    function clearPad() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    // Validar firma antes de enviar
    document.getElementById('consent-form').addEventListener('submit', function(e) {
      const blank = document.createElement("canvas");
      blank.width = canvas.width;
      blank.height = canvas.height;

      if (canvas.toDataURL() === blank.toDataURL()) {
        e.preventDefault();
        alert("Por favor firma antes de continuar.");
        return;
      }

      const dataURL = canvas.toDataURL();
      document.getElementById('signature').value = dataURL;
    });
  </script>
</body>
</html>

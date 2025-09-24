<?php
session_start();
include("../config/db.php");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reserva de cita - Paso 1</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="../public/js/form.js" defer></script>
  <script src="../public/js/slots.js" defer></script>
  <style>
    body { background:#f8f9fa; }
    .card { max-width:600px; margin:2rem auto; }
    .slot-btn { margin:4px; }
    .slot-btn.selected { background:#0d6efd; color:white; }
    .slot-btn.blocked { background:#dc3545; color:white; border-color:#dc3545; }
    .slot-btn.held { background:#ffc107; color:#212529; border-color:#ffc107; } /* reservado temporal */
    .slot-btn.booked { background:#6c757d; color:white; border-color:#6c757d; } /* ocupado */
    .date-hint { font-size:0.875rem; color:#495057; margin-top:.25rem; }
    .date-hint .note { color:#0d6efd; cursor:default; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card shadow p-4">
      <h3 class="mb-3">Datos del paciente</h3>

      <form id="patientForm" method="POST" action="paso2_confirmacion.php" novalidate>

        <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
        <!-- hidden que enviaremos con phone_cc+phone -->
        <input type="hidden" name="phone_full" id="phone_full" value="">

        <div class="mb-3">
          <label class="form-label">Nombre completo</label>
          <input id="full_name" type="text" class="form-control" name="full_name" required>
          <div class="invalid-feedback">Ingresa tu nombre (solo letras y espacios, mínimo 3 caracteres).</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha de nacimiento</label>
          <!-- flatpickr visible como DD-MM-AAAA, value enviado será YYYY-MM-DD -->
          <input id="birth_date" name="birth_date" class="form-control datepick" placeholder="DD-MM-AAAA" required>
          <div class="invalid-feedback">Fecha de nacimiento inválida.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Sexo</label>
          <select id="sex" name="sex" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
          </select>
          <div class="invalid-feedback">Selecciona tu sexo.</div>
        </div>

        <!-- Telefono con LADA -->
        <div class="mb-3">
          <label class="form-label">Teléfono</label>
          <div class="d-flex gap-2">
            <select id="phone_cc" name="phone_cc" class="form-select" style="max-width:140px;">
              <!-- Eliminada la opción duplicada +52 -->
              <option value="52" selected>+52 (MX)</option>
              <option value="1">+1 (US/CA)</option>
              <!-- si quieres más, agrégalos -->
            </select>
            <input id="phone" type="text" class="form-control" name="phone" required maxlength="10" inputmode="numeric" pattern="\d*"
                   placeholder="Ej. 7551234567">
          </div>
          <div class="invalid-feedback">Teléfono inválido (solo dígitos, 10 caracteres).</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Email (opcional)</label>
          <input id="email" type="email" class="form-control" name="email">
          <div class="invalid-feedback">Email no permitido o formato incorrecto.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Especialidad</label>
          <select id="specialty" name="specialty_id" class="form-select" required>
            <option value="">-- Selecciona --</option>
            <?php
              $stmt = $pdo->query("SELECT id,name FROM specialties ORDER BY name");
              while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  echo "<option value='".htmlspecialchars($row['id'])."'>".htmlspecialchars($row['name'])."</option>";
              }
            ?>
          </select>
          <div class="invalid-feedback">Selecciona una especialidad.</div>
        </div>

        <!-- Bloque: ¿Es tu primera visita? (Radios Sí / No)
             LO COLOCO DEBAJO DEL SELECT DE ESPECIALIDAD como pediste.
             Se mantiene oculto por defecto y se mostrará cuando la especialidad sea Dermatología. -->
        <div class="mb-3" id="firstVisitContainer" style="display:none;">
          <label class="form-label">¿Es tu primera visita?</label>
          <div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="is_first_time" id="first_yes" value="1">
              <label class="form-check-label" for="first_yes">Sí</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="is_first_time" id="first_no" value="0">
              <label class="form-check-label" for="first_no">No</label>
            </div>
          </div>
          <div class="invalid-feedback d-block" style="display:none;" id="firstVisitError">Por favor indica si es tu primera visita.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha de cita</label>
          <input id="date" name="date" class="form-control datepick" placeholder="DD-MM-AAAA" required>
          <div class="invalid-feedback">Fecha de cita inválida.</div>
          <!-- Mensaje helper para indicar que al hacer click se puede cambiar la fecha -->
          <div id="dateHint" class="date-hint" aria-live="polite" style="display:none;">
            <span class="note">Haz clic en la fecha para cambiarla</span> — si necesitas elegir otro día, presiona nuevamente el campo.
          </div>
        </div>

        <div class="mb-3" id="horarios">
          <p class="text-muted">Selecciona especialidad y fecha para ver horarios disponibles</p>
        </div>

        <input type="hidden" id="appointment_time" name="appointment_time">

        <div class="form-check mb-3">
          <input id="terms" type="checkbox" class="form-check-input" name="terms" required>
          <label class="form-check-label">Acepto términos y condiciones</label>
          <div class="invalid-feedback">Debes aceptar los términos y condiciones.</div>
        </div>

        <button type="submit" class="btn btn-primary">Continuar</button>
      </form>
    </div>
  </div>
</body>
</html>

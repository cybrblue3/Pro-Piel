<?php
// admin/pending_appointments.php
require_once __DIR__ . '/../auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->query("SELECT a.id, a.appointment_date, a.appointment_time, a.specialty_id, p.full_name AS patient_name, p.phone, p.phone_full, a.patient_id
                     FROM appointments a
                     LEFT JOIN patients p ON p.id = a.patient_id
                     WHERE a.status = 'pending'
                     ORDER BY a.appointment_date, a.appointment_time LIMIT 500");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Citas pendientes — Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../partials/nav.php'; ?>

<div class="container py-4">
  <h4>Citas pendientes</h4>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?=htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']);?></div>
  <?php endif; ?>

  <div class="table-responsive">
  <table class="table table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>Fecha</th>
        <th>Hora</th>
        <th>Paciente</th>
        <th>Tel</th>
        <th>Especialidad</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
<?php foreach($rows as $r):
    $specName = '';
    if (!empty($r['specialty_id'])) {
        $s = $pdo->prepare("SELECT name FROM specialties WHERE id = ? LIMIT 1");
        $s->execute([$r['specialty_id']]);
        $specName = $s->fetchColumn() ?: '';
    }
    // comprobante si existe
    $pp = $pdo->prepare("SELECT id FROM payment_proofs WHERE appointment_id = ? ORDER BY id DESC LIMIT 1");
    $pp->execute([$r['id']]);
    $proofRow = $pp->fetch(PDO::FETCH_ASSOC);
    $proof_id = $proofRow['id'] ?? null;

    $patientName = $r['patient_name'] ?? 'Paciente';
    $date = $r['appointment_date'];
    $time = $r['appointment_time'];
    $displayPhone = htmlspecialchars($r['phone'] ?? '');
?>
      <tr>
        <td><?=htmlspecialchars($date)?></td>
        <td><?=htmlspecialchars($time)?></td>
        <td>
          <a href="#" class="text-decoration-none view-details"
             data-appointment-id="<?=intval($r['id'])?>"
             data-patient="<?=htmlspecialchars($patientName, ENT_QUOTES)?>"
             data-phone="<?=htmlspecialchars($r['phone'] ?? '', ENT_QUOTES)?>"
             data-phone-full="<?=htmlspecialchars($r['phone_full'] ?? '', ENT_QUOTES)?>"
             data-specialty="<?=htmlspecialchars($specName, ENT_QUOTES)?>"
             data-date="<?=htmlspecialchars($date, ENT_QUOTES)?>"
             data-time="<?=htmlspecialchars($time, ENT_QUOTES)?>"
             data-proof-id="<?=intval($proof_id)?>"
            ><?= htmlspecialchars($patientName) ?></a>
        </td>
        <td><?= $displayPhone ?></td>
        <td><?= htmlspecialchars($specName) ?></td>
        <td class="text-end">
          <!-- Ver (abre modal) -->
          <button class="btn btn-sm btn-outline-secondary view-details-btn"
                  data-appointment-id="<?=intval($r['id'])?>"
                  data-patient="<?=htmlspecialchars($patientName, ENT_QUOTES)?>"
                  data-phone="<?=htmlspecialchars($r['phone'] ?? '', ENT_QUOTES)?>"
                  data-phone-full="<?=htmlspecialchars($r['phone_full'] ?? '', ENT_QUOTES)?>"
                  data-specialty="<?=htmlspecialchars($specName, ENT_QUOTES)?>"
                  data-date="<?=htmlspecialchars($date, ENT_QUOTES)?>"
                  data-time="<?=htmlspecialchars($time, ENT_QUOTES)?>"
                  data-proof-id="<?=intval($proof_id)?>"
                  >Ver</button>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Modal (NO HAY FORMULARIO envolviendo todo, cada acción tiene su propio form) -->
<div class="modal fade" id="apptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="modalAlert" class="alert d-none" role="alert"></div>

        <dl class="row">
          <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8" id="m_patient">-</dd>
          <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8" id="m_phone">-</dd>
          <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8" id="m_spec">-</dd>
          <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8" id="m_date">-</dd>
          <dt class="col-sm-4">Hora</dt><dd class="col-sm-8" id="m_time">-</dd>
          <dt class="col-sm-4">Comprobante</dt><dd class="col-sm-8" id="m_proof_area"><span class="text-muted">No hay comprobante</span></dd>
        </dl>

        <!-- Cancel block: textarea para motivo -->
        <div id="cancelBlock" class="d-none">
          <hr>
          <h6>Cancelar cita</h6>
          <div class="mb-2">
            <label class="form-label" for="cancel_comment">Motivo (requerido)</label>
            <textarea id="cancel_comment" name="cancel_comment" class="form-control" rows="3" placeholder="Escribe el motivo de la cancelación"></textarea>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <!-- Form específico para confirmar (evita anidar forms) -->
        <form id="confirmForm" method="POST" action="confirm_appointment.php" style="display:inline;" data-skip-ajax="1">>
          <input type="hidden" name="appointment_id" id="confirm_appt_id" value="">
          <button type="submit" class="btn btn-primary">Confirmar</button>
        </form>

        <!-- Botón para abrir el bloque de cancelar -->
        <button type="button" id="showCancelBtn" class="btn btn-danger">Cancelar cita</button>

        <!-- Enviar cancelación -->
        <button type="button" id="submitCancelBtn" class="btn btn-outline-danger d-none">Enviar cancelación</button>

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const apptModalEl = document.getElementById('apptModal');
  const apptModal = new bootstrap.Modal(apptModalEl);
  const confirmApptId = document.getElementById('confirm_appt_id');
  const m_patient = document.getElementById('m_patient');
  const m_phone = document.getElementById('m_phone');
  const m_spec = document.getElementById('m_spec');
  const m_date = document.getElementById('m_date');
  const m_time = document.getElementById('m_time');
  const m_proof_area = document.getElementById('m_proof_area');
  const showCancelBtn = document.getElementById('showCancelBtn');
  const cancelBlock = document.getElementById('cancelBlock');
  const submitCancelBtn = document.getElementById('submitCancelBtn');
  const cancel_comment = document.getElementById('cancel_comment');
  const modalAlert = document.getElementById('modalAlert');

  function openWithData(dataset) {
    const apptId = dataset.appointmentId;
    const patient = dataset.patient || '-';
    const phone = dataset.phone || '';
    const phoneFull = dataset.phoneFull || '';
    const spec = dataset.specialty || '';
    const date = dataset.date || '';
    const time = dataset.time || '';
    const proofId = dataset.proofId || null;

    confirmApptId.value = apptId;
    m_patient.textContent = patient;
    m_phone.textContent = phoneFull ? phoneFull + ' (' + phone + ')' : phone;
    m_spec.textContent = spec;
    m_date.textContent = date;
    m_time.textContent = time;

    if (proofId && parseInt(proofId) > 0) {
      m_proof_area.innerHTML = '<a target="_blank" href="view_proof.php?id=' + encodeURIComponent(proofId) + '" class="btn btn-sm btn-outline-secondary">Ver comprobante</a>';
    } else {
      m_proof_area.innerHTML = '<span class="text-muted">No hay comprobante</span>';
    }

    // Reset cancel UI
    cancelBlock.classList.add('d-none');
    submitCancelBtn.classList.add('d-none');
    showCancelBtn.classList.remove('d-none');
    cancel_comment.value = '';
    modalAlert.classList.add('d-none');

    apptModal.show();
  }

  document.querySelectorAll('.view-details, .view-details-btn').forEach(el => {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      const dataset = {
        appointmentId: this.dataset.appointmentId || this.getAttribute('data-appointment-id'),
        patient: this.dataset.patient || this.getAttribute('data-patient'),
        phone: this.dataset.phone || this.getAttribute('data-phone'),
        phoneFull: this.dataset.phoneFull || this.getAttribute('data-phone-full'),
        specialty: this.dataset.specialty || this.getAttribute('data-specialty'),
        date: this.dataset.date || this.getAttribute('data-date'),
        time: this.dataset.time || this.getAttribute('data-time'),
        proofId: this.dataset.proofId || this.getAttribute('data-proof-id') || null
      };
      openWithData(dataset);
    });
  });

  showCancelBtn.addEventListener('click', function() {
    cancelBlock.classList.remove('d-none');
    submitCancelBtn.classList.remove('d-none');
    showCancelBtn.classList.add('d-none');
  });

  submitCancelBtn.addEventListener('click', function() {
    const apptId = confirmApptId.value;
    const comment = cancel_comment.value.trim();
    if (!comment) {
      modalAlert.className = 'alert alert-danger';
      modalAlert.textContent = 'Escribe el motivo de la cancelación.';
      modalAlert.classList.remove('d-none');
      return;
    }
    // crear y enviar form POST hacia cancel_appointment.php
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = 'cancel_appointment.php';
    f.style.display = 'none';
    const i1 = document.createElement('input'); i1.type='hidden'; i1.name='appointment_id'; i1.value = apptId; f.appendChild(i1);
    const i2 = document.createElement('input'); i2.type='hidden'; i2.name='cancel_comment'; i2.value = comment; f.appendChild(i2);
    document.body.appendChild(f);
    f.submit();
  });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Modal bootstrap
  const apptModalEl = document.getElementById('apptModal');
  const apptModal = new bootstrap.Modal(apptModalEl);
  const confirmForm = document.getElementById('confirmForm');
  const confirmApptId = document.getElementById('confirm_appt_id');
  const m_patient = document.getElementById('m_patient');
  const m_phone = document.getElementById('m_phone');
  const m_spec = document.getElementById('m_spec');
  const m_date = document.getElementById('m_date');
  const m_time = document.getElementById('m_time');
  const m_proof_area = document.getElementById('m_proof_area');
  const showCancelBtn = document.getElementById('showCancelBtn');
  const submitCancelBtn = document.getElementById('submitCancelBtn');
  const cancelBlock = document.getElementById('cancelBlock');
  const cancel_comment = document.getElementById('cancel_comment');
  const modalAlert = document.getElementById('modalAlert');

  // Utility: remove any table rows that reference this appointment id
  function removeRowsByAppointmentId(id) {
    if (!id) return;
    // selector matches the different attribute names used en dashboard/pending pages
    const sel = document.querySelectorAll('[data-id="' + id + '"], [data-appointment-id="' + id + '"], button[data-id="' + id + '"], button[data-appointment-id="' + id + '"]');
    sel.forEach(el => {
      const tr = el.closest('tr');
      if (tr) tr.remove();
    });
    // adicional: si hay anchors con data attributes
    const sel2 = document.querySelectorAll('a[data-id="' + id + '"], a[data-appointment-id="' + id + '"]');
    sel2.forEach(el => {
      const tr = el.closest('tr');
      if (tr) tr.remove();
    });
  }

  // Mostrar alert temporal en top container
  function showTopAlert(message, type='success') {
    const container = document.querySelector('.container') || document.body;
    const div = document.createElement('div');
    div.className = 'alert alert-' + type;
    div.textContent = message;
    container.insertBefore(div, container.firstChild);
    setTimeout(() => {
      div.remove();
    }, 5000);
  }

  // Abrir modal con datos
  function openWithData(dataset) {
    const apptId = dataset.appointmentId;
    const patient = dataset.patient || '-';
    const phone = dataset.phone || '';
    const phoneFull = dataset.phoneFull || '';
    const spec = dataset.specialty || '';
    const date = dataset.date || '';
    const time = dataset.time || '';
    const proofId = dataset.proofId || null;

    confirmApptId.value = apptId;
    m_patient.textContent = patient;
    m_phone.textContent = phoneFull ? phoneFull + ' (' + phone + ')' : phone;
    m_spec.textContent = spec;
    m_date.textContent = date;
    m_time.textContent = time;
    if (proofId && parseInt(proofId) > 0) {
      m_proof_area.innerHTML = '<a target="_blank" href="view_proof.php?id=' + encodeURIComponent(proofId) + '" class="btn btn-sm btn-outline-secondary">Ver comprobante</a>';
    } else {
      m_proof_area.innerHTML = '<span class="text-muted">No hay comprobante</span>';
    }

    // reset cancel UI
    cancelBlock.classList.add('d-none');
    submitCancelBtn.classList.add('d-none');
    showCancelBtn.classList.remove('d-none');
    cancel_comment.value = '';
    modalAlert.classList.add('d-none');

    apptModal.show();
  }

  // Conectar botones / enlaces que abren modal
  document.querySelectorAll('.view-details, .view-details-btn, .view-details-btn, .view-details').forEach(el => {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      const dataset = {
        appointmentId: this.dataset.appointmentId || this.getAttribute('data-appointment-id') || this.getAttribute('data-id'),
        patient: this.dataset.patient || this.getAttribute('data-patient'),
        phone: this.dataset.phone || this.getAttribute('data-phone'),
        phoneFull: this.dataset.phoneFull || this.getAttribute('data-phone-full'),
        specialty: this.dataset.specialty || this.getAttribute('data-specialty') || this.getAttribute('data-spec'),
        date: this.dataset.date || this.getAttribute('data-date'),
        time: this.dataset.time || this.getAttribute('data-time'),
        proofId: this.dataset.proofId || this.getAttribute('data-proof-id') || this.getAttribute('data-proof')
      };
      openWithData(dataset);
    });
  });

  // Mostrar bloque cancel
  if (showCancelBtn) {
    showCancelBtn.addEventListener('click', function() {
      cancelBlock.classList.remove('d-none');
      submitCancelBtn.classList.remove('d-none');
      showCancelBtn.classList.add('d-none');
    });
  }

  // --- Interceptar confirm (form submit)
  if (confirmForm) {
    confirmForm.addEventListener('submit', function(ev) {
      // Si el form tiene data-skip-ajax="1", no interceptamos: permitimos envío normal (misma pestaña).
  if (this.dataset && this.dataset.skipAjax === '1') return;
  ev.preventDefault();
      ev.preventDefault();
      const apptId = confirmApptId.value;
      if (!apptId) return;

      const formData = new FormData();
      formData.append('appointment_id', apptId);

      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      }).then(r => r.json())
        .then(json => {
          if (!json || !json.success) {
            const err = (json && json.error) ? json.error : 'Error al confirmar.';
            modalAlert.className = 'alert alert-danger';
            modalAlert.textContent = err;
            modalAlert.classList.remove('d-none');
            return;
          }
          // éxito: quitar fila(s), cerrar modal, notificar, abrir WA en nueva pestaña si viene wa_url
          removeRowsByAppointmentId(apptId);
          apptModal.hide();
          showTopAlert('Cita confirmada correctamente', 'success');
          if (json.wa_url) {
  // navegar en la misma pestaña para replicar el comportamiento de "Cancelar"
  try { 
    apptModal.hide();
  } catch(e){}
  // limpiar cualquier backdrop residual por si quedó
  document.body.classList.remove('modal-open');
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  // navegar a WhatsApp en la misma pestaña
  window.location.href = json.wa_url;
}

        }).catch(err => {
          console.error(err);
          modalAlert.className = 'alert alert-danger';
          modalAlert.textContent = 'Ocurrió un error al confirmar.';
          modalAlert.classList.remove('d-none');
        });
    });
  }

  // --- Enviar cancelación via AJAX
  if (submitCancelBtn) {
    submitCancelBtn.addEventListener('click', function() {
      const apptId = confirmApptId.value;
      const comment = (cancel_comment && cancel_comment.value) ? cancel_comment.value.trim() : '';
      if (!comment) {
        modalAlert.className = 'alert alert-danger';
        modalAlert.textContent = 'Escribe el motivo de la cancelación.';
        modalAlert.classList.remove('d-none');
        return;
      }
      const formData = new FormData();
      formData.append('appointment_id', apptId);
      formData.append('cancel_comment', comment);

      fetch('cancel_appointment.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      }).then(r => r.json())
        .then(json => {
          if (!json || !json.success) {
            const err = (json && json.error) ? json.error : 'Error al cancelar.';
            modalAlert.className = 'alert alert-danger';
            modalAlert.textContent = err;
            modalAlert.classList.remove('d-none');confirmar<
            return;
          }
          // éxito: quitar fila(s), cerrar modal, notificar, abrir WA en nueva pestaña si viene wa_url
          removeRowsByAppointmentId(apptId);
          apptModal.hide();
          showTopAlert('Cita cancelada correctamente', 'warning');
          if (json.wa_url) {
  // navegar en la misma pestaña para replicar el comportamiento de "Cancelar"
  try { 
    apptModal.hide();
  } catch(e){}
  // limpiar cualquier backdrop residual por si quedó
  document.body.classList.remove('modal-open');
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  // navegar a WhatsApp en la misma pestaña
  window.location.href = json.wa_url;
}

        }).catch(err => {
          console.error(err);
          modalAlert.className = 'alert alert-danger';
          modalAlert.textContent = 'Ocurrió un error al cancelar.';
          modalAlert.classList.remove('d-none');
        });
    });
  }

});
</script>
</body>
</html>

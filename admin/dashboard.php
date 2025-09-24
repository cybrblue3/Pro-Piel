<?php
require_once __DIR__ . '/../auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

// obtener métricas
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_specs = (int)$pdo->query("SELECT COUNT(*) FROM specialties")->fetchColumn();
$total_appointments = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();

// últimos usuarios (para modal)
$last_users_stmt = $pdo->query("SELECT id,name,email,phone,role,created_at FROM users ORDER BY created_at DESC LIMIT 10");
$last_users = $last_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// citas pendientes (lista principal)
$stmt = $pdo->query("
  SELECT a.id, a.appointment_date, a.appointment_time, a.specialty_id, a.patient_id,
         p.full_name AS patient_name, p.phone AS patient_phone, p.phone_full
  FROM appointments a
  LEFT JOIN patients p ON p.id = a.patient_id
  WHERE a.status = 'pending'
  ORDER BY a.appointment_date DESC, a.appointment_time DESC
  LIMIT 500
");
$pending_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper para conseguir nombre de especialidad
function specNameById($pdo, $id){
  if (empty($id)) return '';
  $s = $pdo->prepare("SELECT name FROM specialties WHERE id = ? LIMIT 1");
  $s->execute([$id]);
  return $s->fetchColumn() ?: '';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin — Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/../partials/nav.php'; ?>

<div class="container py-4">
  <h3>Panel administrador</h3>
  <p class="text-muted">Resumen y accesos rápidos.</p>

  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?=htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']);?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']);?></div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#usersModal">
        <div class="card p-3 h-100">
          <div class="h6">Usuarios</div>
          <div class="display-6"><?= $total_users ?></div>
          <div class="text-muted mt-2">Ver últimos usuarios</div>
        </div>
      </a>
    </div>

    <div class="col-md-3">
      <div class="card p-3 h-100">
        <div class="h6">Especialidades</div>
        <div class="display-6"><?= $total_specs ?></div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card p-3 h-100">
        <div class="h6">Citas totales</div>
        <div class="display-6"><?= $total_appointments ?></div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card p-3 h-100">
        <div class="h6">Citas pendientes</div>
        <div class="display-6"><?= $pending_count ?></div>
        <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="#pendingList">Ver pendientes</a></div>
      </div>
    </div>
  </div>

  <!-- PENDIENTES: tabla principal -->
  <div id="pendingList" class="card mb-4">
    <div class="card-body">
      <h5>Últimas citas pendientes</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Fecha</th><th>Hora</th><th>Paciente</th><th>Tel</th><th>Especialidad</th><th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$pending_rows): ?>
              <tr><td colspan="6" class="text-muted">No hay citas pendientes</td></tr>
            <?php else: ?>
              <?php foreach ($pending_rows as $r): 
                $spec = specNameById($pdo, $r['specialty_id']);
                $patient = htmlspecialchars($r['patient_name'] ?? 'N/E');
                $phone = htmlspecialchars($r['patient_phone'] ?? '');
                $phone_full = htmlspecialchars($r['phone_full'] ?? '');
                $proof_id = null;
                $pp = $pdo->prepare("SELECT id FROM payment_proofs WHERE appointment_id = ? ORDER BY id DESC LIMIT 1");
                $pp->execute([$r['id']]);
                $ppr = $pp->fetch(PDO::FETCH_ASSOC); $proof_id = $ppr['id'] ?? null;
              ?>
              <tr>
                <td><?=htmlspecialchars($r['appointment_date'])?></td>
                <td><?=htmlspecialchars($r['appointment_time'])?></td>
                <td>
                  <a href="#" class="text-decoration-none view-details"
                     data-id="<?=intval($r['id'])?>"
                     data-patient="<?=htmlspecialchars($patient, ENT_QUOTES)?>"
                     data-phone="<?=htmlspecialchars($phone, ENT_QUOTES)?>"
                     data-phone-full="<?=htmlspecialchars($phone_full, ENT_QUOTES)?>"
                     data-spec="<?=htmlspecialchars($spec, ENT_QUOTES)?>"
                     data-date="<?=htmlspecialchars($r['appointment_date'], ENT_QUOTES)?>"
                     data-time="<?=htmlspecialchars($r['appointment_time'], ENT_QUOTES)?>"
                     data-proof="<?=intval($proof_id)?>"
                  ><?= $patient ?></a>
                </td>
                <td><?= $phone ?></td>
                <td><?= htmlspecialchars($spec) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-primary view-details" 
                     data-id="<?=intval($r['id'])?>"
                     data-patient="<?=htmlspecialchars($patient, ENT_QUOTES)?>"
                     data-phone="<?=htmlspecialchars($phone, ENT_QUOTES)?>"
                     data-phone-full="<?=htmlspecialchars($phone_full, ENT_QUOTES)?>"
                     data-spec="<?=htmlspecialchars($spec, ENT_QUOTES)?>"
                     data-date="<?=htmlspecialchars($r['appointment_date'], ENT_QUOTES)?>"
                     data-time="<?=htmlspecialchars($r['appointment_time'], ENT_QUOTES)?>"
                     data-proof="<?=intval($proof_id)?>"
                  >Ver</button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Modal detalle cita (confirmar/cancelar) -->
<div class="modal fade" id="apptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalle de cita</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <dl class="row">
            <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8" id="m_patient">-</dd>
            <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8" id="m_phone">-</dd>
            <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8" id="m_spec">-</dd>
            <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8" id="m_date">-</dd>
            <dt class="col-sm-4">Hora</dt><dd class="col-sm-8" id="m_time">-</dd>
            <dt class="col-sm-4">Comprobante</dt><dd class="col-sm-8" id="m_proof_area"><span class="text-muted">No hay comprobante</span></dd>
          </dl>

          <div id="cancelBlock" class="d-none">
            <hr>
            <h6>Cancelar cita</h6>
            <div class="mb-2">
              <label class="form-label">Motivo (requerido)</label>
              <textarea id="cancel_comment" name="cancel_comment" class="form-control" rows="3" placeholder="Escribe el motivo de la cancelación"></textarea>
            </div>
          </div>

          <div id="modalAlert" class="alert d-none" role="alert"></div>
        </div>

        <div class="modal-footer">
          <!-- Confirmar (POST a confirm_appointment.php) -->
          <form method="POST" id="confirmForm" action="confirm_appointment.php" style="display:inline;" data-skip-ajax="1">
            <input type="hidden" name="appointment_id" id="confirm_appt_id" value="">
            <button type="submit" class="btn btn-primary">Confirmar</button>
          </form>

          <button type="button" id="showCancelBtn" class="btn btn-danger">Cancelar cita</button>
          <button type="button" id="submitCancelBtn" class="btn btn-outline-danger d-none">Enviar cancelación</button>

          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
    </div>
  </div>
</div>
<!-- Modal Usuarios -->
<div class="modal fade" id="usersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Últimos usuarios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Tel</th><th>Rol</th><th>Creado</th></tr></thead>
            <tbody>
              <?php if (!$last_users): ?>
                <tr><td colspan="6" class="text-muted">No hay usuarios</td></tr>
              <?php else: foreach($last_users as $u): ?>
                <tr>
                  <td><?=intval($u['id'])?></td>
                  <td><?=htmlspecialchars($u['name'])?></td>
                  <td><?=htmlspecialchars($u['email'])?></td>
                  <td><?=htmlspecialchars($u['phone'])?></td>
                  <td><?=htmlspecialchars($u['role'])?></td>
                  <td><?=htmlspecialchars($u['created_at'])?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const modal = new bootstrap.Modal(document.getElementById('apptModal'));
  const confirm_appt_id = document.getElementById('confirm_appt_id');
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

  function openModalFrom(el){
    const apptId = el.getAttribute('data-id');
    const patient = el.getAttribute('data-patient') || '';
    const phone = el.getAttribute('data-phone') || '';
    const phoneFull = el.getAttribute('data-phone-full') || '';
    const spec = el.getAttribute('data-spec') || '';
    const date = el.getAttribute('data-date') || '';
    const time = el.getAttribute('data-time') || '';
    const proof = el.getAttribute('data-proof') || '';

    confirm_appt_id.value = apptId;
    m_patient.textContent = patient;
    m_phone.textContent = phoneFull ? (phoneFull + ' (' + phone + ')') : phone;
    m_spec.textContent = spec;
    m_date.textContent = date;
    m_time.textContent = time;
    if (proof && parseInt(proof) > 0) {
      m_proof_area.innerHTML = '<a class="btn btn-sm btn-outline-secondary" target="_blank" href="../admin/view_proof.php?id=' + encodeURIComponent(proof) + '">Ver comprobante</a>';
    } else {
      m_proof_area.innerHTML = '<span class="text-muted">No hay comprobante</span>';
    }

    cancelBlock.classList.add('d-none');
    submitCancelBtn.classList.add('d-none');
    showCancelBtn.classList.remove('d-none');
    cancel_comment.value = '';
    modalAlert.classList.add('d-none');

    modal.show();
  }

  document.querySelectorAll('.view-details').forEach(btn=>{
    btn.addEventListener('click', function(e){
      e.preventDefault();
      openModalFrom(this);
    });
  });

  showCancelBtn.addEventListener('click', function(){
    cancelBlock.classList.remove('d-none');
    submitCancelBtn.classList.remove('d-none');
    showCancelBtn.classList.add('d-none');
  });

  submitCancelBtn.addEventListener('click', function(){
    const apptId = confirm_appt_id.value;
    const comment = cancel_comment.value.trim();
    if (!comment) {
      modalAlert.className = 'alert alert-danger';
      modalAlert.textContent = 'Escribe el motivo de la cancelación.';
      modalAlert.classList.remove('d-none');
      return;
    }
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = 'cancel_appointment.php';
    const in1 = document.createElement('input'); in1.type='hidden'; in1.name='appointment_id'; in1.value=apptId; f.appendChild(in1);
    const in2 = document.createElement('input'); in2.type='hidden'; in2.name='cancel_comment'; in2.value=comment; f.appendChild(in2);
    document.body.appendChild(f);
    f.submit();
  });

})();
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
            modalAlert.classList.remove('d-none');
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

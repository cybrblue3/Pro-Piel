<?php
// admin/cancel_appointment.php
session_start();
require_once __DIR__ . '/../auth_check.php';
require_role('admin');
require_once __DIR__ . '/../config/db.php';

function is_ajax_request() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax_request()) {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }
    header('Location: pending_appointments.php');
    exit;
}

$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$comment = trim($_POST['cancel_comment'] ?? '');

if (!$appointment_id || $comment === '') {
    if (is_ajax_request()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID o motivo inválido.']);
        exit;
    }
    $_SESSION['flash_error'] = "ID o motivo inválido.";
    header('Location: pending_appointments.php');
    exit;
}

try {
    // Iniciar transacción para el SELECT ... FOR UPDATE tenga efecto
    $pdo->beginTransaction();

    // Obtener datos y bloquear
    $stmt = $pdo->prepare("SELECT a.id, a.appointment_date, a.appointment_time, a.specialty_id, p.full_name, p.phone, p.phone_full
                           FROM appointments a
                           LEFT JOIN patients p ON p.id = a.patient_id
                           WHERE a.id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$appointment_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        if (is_ajax_request()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Cita no encontrada.']);
            exit;
        }
        $_SESSION['flash_error'] = "Cita no encontrada.";
        header('Location: pending_appointments.php');
        exit;
    }

    // Actualizar estado a cancelled
    $upd = $pdo->prepare("UPDATE appointments
                          SET status = 'cancelled', cancel_reason = :reason, cancel_comment = :comment, cancelled_by = :uid, cancelled_at = NOW()
                          WHERE id = :id");
    $upd->execute([
        'reason' => 'admin_cancel',
        'comment' => $comment,
        'uid' => $_SESSION['user_id'],
        'id' => $appointment_id
    ]);

    $pdo->commit();

    // Preparar mensaje WA bonito
    $patientName = $row['full_name'] ?? 'Paciente';
    $specName = '';
    if (!empty($row['specialty_id'])) {
        $s = $pdo->prepare("SELECT name FROM specialties WHERE id = ? LIMIT 1");
        $s->execute([$row['specialty_id']]);
        $specName = $s->fetchColumn() ?: '';
    }
    $date = $row['appointment_date'];
    $time = $row['appointment_time'];

    $message  = "Hola *{$patientName}* 👋\n\n";
    $message .= "⚠️ Lamentamos informarte que tu cita en *Clínica Propiel* para *{$specName}* ha sido *cancelada*.\n\n";
    $message .= "📅 Fecha: {$date}\n";
    $message .= "⏰ Hora: {$time}\n";
    $message .= "📝 Motivo: {$comment}\n\n";
    $message .= "Por favor contáctanos para reprogramar o resolver cualquier duda.\n\n";
    $message .= "Disculpa las molestias ocasionadas 🙏";

    $msg = rawurlencode($message);

    // Armar número
    $num = '';
    if (!empty($row['phone_full'])) $num = preg_replace('/\D+/', '', $row['phone_full']);
    else if (!empty($row['phone'])) $num = '52' . preg_replace('/\D+/', '', $row['phone']);

    if (is_ajax_request()) {
        $wa = $num ? "https://api.whatsapp.com/send?phone={$num}&text={$msg}" : null;
        echo json_encode(['success' => true, 'wa_url' => $wa, 'appointment_id' => $appointment_id]);
        exit;
    }

    if ($num) {
        $wa = "https://api.whatsapp.com/send?phone={$num}&text={$msg}";
        header("Location: {$wa}");
        exit;
    } else {
        $_SESSION['flash_success'] = "Cita cancelada (sin número de teléfono para WhatsApp).";
        header('Location: pending_appointments.php');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("cancel_appointment error: " . $e->getMessage());
    if (is_ajax_request()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ocurrió un error al cancelar.']);
        exit;
    }
    $_SESSION['flash_error'] = "Ocurrió un error al cancelar.";
    header('Location: pending_appointments.php');
    exit;
}

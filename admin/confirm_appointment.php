<?php
// admin/confirm_appointment.php
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
if (!$appointment_id) {
    if (is_ajax_request()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }
    $_SESSION['flash_error'] = "ID inválido.";
    header('Location: pending_appointments.php');
    exit;
}

try {
    // Transacción + SELECT ... FOR UPDATE
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.specialty_id, a.patient_id,
               p.full_name, p.phone, p.phone_full, a.status
        FROM appointments a
        LEFT JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ? LIMIT 1 FOR UPDATE
    ");
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

    if (($row['status'] ?? '') === 'confirmed') {
        $pdo->commit();
        if (is_ajax_request()) {
            echo json_encode(['success' => true, 'already_confirmed' => true, 'message' => 'La cita ya estaba confirmada.']);
            exit;
        }
        $_SESSION['flash_success'] = "La cita ya estaba confirmada.";
        header('Location: pending_appointments.php');
        exit;
    }

    // Actualizar appointment -> confirmed
    $upd = $pdo->prepare("
        UPDATE appointments
        SET status = 'confirmed', confirmed_by = :uid, confirmed_at = NOW()
        WHERE id = :id
    ");
    $upd->execute(['uid' => $_SESSION['user_id'], 'id' => $appointment_id]);

    // Marcar paciente como confirmado si aplica
    if (!empty($row['patient_id'])) {
        $pup = $pdo->prepare("UPDATE patients SET is_confirmed = 1 WHERE id = ? LIMIT 1");
        $pup->execute([$row['patient_id']]);
    }

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
    $message .= "✅ Tu cita en *Clínica Propiel* para *{$specName}* ha sido *confirmada*.\n\n";
    $message .= "📅 Fecha: {$date}\n";
    $message .= "⏰ Hora: {$time}\n\n";
    $message .= "📍 Llega 10 minutos antes. Si necesitas reprogramar, responde a este mensaje.\n\n";
    $message .= "¡Gracias por confiar en nosotros! 💙";

    $msg = rawurlencode($message);

    // Armar número (preferir phone_full)
    $num = '';
    if (!empty($row['phone_full'])) {
        $num = preg_replace('/\D+/', '', $row['phone_full']);
    } elseif (!empty($row['phone'])) {
        $num = '52' . preg_replace('/\D+/', '', $row['phone']);
    }

    // Si es petición AJAX, devolvemos JSON (tu JS puede abrir WA en nueva pestaña si quieres)
    if (is_ajax_request()) {
        $wa = $num ? "https://api.whatsapp.com/send?phone={$num}&text={$msg}" : null;
        echo json_encode(['success' => true, 'wa_url' => $wa, 'appointment_id' => $appointment_id]);
        exit;
    }

    // COMPORTAMIENTO EXACTO COMO TU cancel_appointment.php:
    // Redirigir en la MISMA pestaña a WhatsApp (si hay número). Al volver (back), el admin verá la pantalla actualizada.
    if ($num) {
        $wa = "https://api.whatsapp.com/send?phone={$num}&text={$msg}";
        header("Location: {$wa}");
        exit;
    } else {
        $_SESSION['flash_success'] = "Cita confirmada correctamente (sin número de teléfono para WhatsApp).";
        header('Location: pending_appointments.php');
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("confirm_appointment error: " . $e->getMessage());
    if (is_ajax_request()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ocurrió un error al confirmar.']);
        exit;
    }
    $_SESSION['flash_error'] = "Ocurrió un error al confirmar.";
    header('Location: pending_appointments.php');
    exit;
}

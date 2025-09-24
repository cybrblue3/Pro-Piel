<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION['patient']) || !isset($_SESSION['hold_token'])) {
    die("Sesión inválida o expirada. Por favor reinicia el proceso.");
}

$patient = $_SESSION['patient'];
$token = $_SESSION['hold_token'];

// Validar que exista el archivo
if (!isset($_FILES['comprobante'])) {
    die("No se recibió archivo.");
}

if ($_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
    die("Error al subir archivo (código: {$_FILES['comprobante']['error']}).");
}

$allowed = ['image/jpeg','image/png','application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['comprobante']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed)) {
    die("Formato de archivo no permitido.");
}
if ($_FILES['comprobante']['size'] > 5*1024*1024) {
    die("Archivo demasiado grande (máx 5MB).");
}

// Re-check del hold y disponibilidad (transacción)
try {
    $pdo->beginTransaction();

    // Obtener hold
    $stmt = $pdo->prepare("SELECT * FROM appointment_holds WHERE temp_token = :tok FOR UPDATE");
    $stmt->execute(['tok' => $token]);
    $hold = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hold) {
        $pdo->rollBack();
        die("Reserva temporal no encontrada o ya expirada.");
    }
    // Verificar expires_at
    if (strtotime($hold['expires_at']) <= time()) {
        $pdo->rollBack();
        die("Reserva temporal expirada. Por favor selecciona el horario de nuevo.");
    }

    // Verificar que no exista una cita ya (por concurrencia)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE specialty_id = :spec AND appointment_date = :fecha AND appointment_time = :hora
          AND status IN ('pending','confirmed','attended')
        FOR UPDATE
    ");
    $stmt->execute(['spec'=>$hold['specialty_id'], 'fecha'=>$hold['appointment_date'], 'hora'=>$hold['appointment_time']]);
    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        die("El horario ya fue ocupado. Intenta otro horario.");
    }

    // Crear/obtener paciente
    $phone_local = $patient['phone'] ?? '';
    $phone_full = $patient['phone_full'] ?? '';
    $phone_cc = $patient['phone_cc'] ?? null;

    $stmt = $pdo->prepare("SELECT id, is_confirmed FROM patients WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone_local]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_id = $row['id'] ?? null;
    if (!$patient_id) {
        // Insertamos paciente COMO PENDIENTE (is_confirmed = 0)
        $stmt = $pdo->prepare("INSERT INTO patients (full_name,birth_date,sex,phone,phone_cc,phone_full,email,is_confirmed) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $patient['full_name'] ?? '',
            $patient['birth_date'] ?? null,
            $patient['sex'] ?? '',
            $phone_local,
            $phone_cc,
            $phone_full,
            $patient['email'] ?? '',
            0
        ]);
        $patient_id = $pdo->lastInsertId();
    } else {
        // Si existe, no tocamos is_confirmed: la confirmación la hará el admin
    }

    // Obtener service_id por la specialty (elige el primero disponible)
    $stmt = $pdo->prepare("SELECT id FROM services WHERE specialty_id = ? LIMIT 1");
    $stmt->execute([$hold['specialty_id']]);
    $service_id = $stmt->fetchColumn();
    if (!$service_id) {
        $pdo->rollBack();
        die("No hay servicio configurado para la especialidad seleccionada.");
    }

    // Insertar appointment (estado 'pending' — admin debe confirmar)
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id,specialty_id,service_id,appointment_date,appointment_time,status,is_first_time)
        VALUES (:pid,:spec,:sid,:fecha,:hora,'pending',:first)
    ");
    $stmt->execute([
        'pid' => $patient_id,
        'spec' => $hold['specialty_id'],
        'sid' => $service_id,
        'fecha' => $hold['appointment_date'],
        'hora' => $hold['appointment_time'],
        'first' => $patient['is_first_time'] ?? 0
    ]);
    $appointment_id = $pdo->lastInsertId();

    // Guardar archivo en uploads (asegúrate de permisos)
    $destDir = __DIR__ . "/../public/uploads/comprobantes/";
    if (!is_dir($destDir)) mkdir($destDir, 0777, true);

    $ext = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
    $filename = "comp_{$appointment_id}_" . time() . "." . $ext;
    $path = $destDir . $filename;
    if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $path)) {
        $pdo->rollBack();
        die("No se pudo guardar el archivo.");
    }

    // Registrar comprobante
    $stmt = $pdo->prepare("INSERT INTO payment_proofs (appointment_id,filename,mime,size_bytes) VALUES (?,?,?,?)");
    $stmt->execute([$appointment_id, $filename, $mime, $_FILES['comprobante']['size']]);

    // Borrar hold
    $stmt = $pdo->prepare("DELETE FROM appointment_holds WHERE id = ?");
    $stmt->execute([$hold['id']]);

    $pdo->commit();

    // Limpiar sesión de hold
    unset($_SESSION['hold_token']);

    // Guardar appointment_id en sesión para el consentimiento
    $_SESSION['appointment_id'] = $appointment_id;

    // Redirigir condicional
    $specName = strtolower($patient['specialty_name'] ?? '');
    if ($specName === 'dermatología' || $specName === 'dermatologia') {
        header("Location: ../patient/paso4_consentimiento.php");
    } else {
        header("Location: ../patient/success.php");
    }
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error upload_payment: " . $e->getMessage());
    die("Ocurrió un error al procesar tu pago. Intenta de nuevo.");
}
// Si todo salió bien -- (no debería llegar aquí por los exit)
header("Location: ../patient/success.php");
exit;

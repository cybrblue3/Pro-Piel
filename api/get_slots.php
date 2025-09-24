<?php
header('Content-Type: application/json; charset=utf-8');

// Ajusta credenciales si difieren en tu entorno
$host = "localhost:3306";
$dbname = "propiel";
$user = "root";
$pass = "";

try {
    // forzar timezone para coherencia con tu entorno (ajusta si tu servidor usa otra TZ)
    date_default_timezone_set('America/Mexico_City');

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión"]);
    exit;
}

$specialty_id = isset($_GET['specialty_id']) ? (int) $_GET['specialty_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : null;

if (!$specialty_id || !$date) {
    http_response_code(400);
    echo json_encode(["error" => "Parámetros inválidos"]);
    exit;
}

$dayOfWeek = (int) date('N', strtotime($date)); // 1-7

// determinar si la fecha solicitada es hoy en servidor
$todayServer = date('Y-m-d');
$is_today = ($date === $todayServer);
$currentTime = date('H:i'); // hora actual del servidor en HH:MM

// 1) comprobar exceptions (día bloqueado)
$stmt = $pdo->prepare("SELECT * FROM schedule_exceptions 
    WHERE specialty_id = :spec AND exception_date = :fecha AND type = 'blocked' LIMIT 1");
$stmt->execute(["spec" => $specialty_id, "fecha" => $date]);
$isBlockedDay = ($stmt->rowCount() > 0);

// 2) obtener plantillas del día
$stmt = $pdo->prepare("SELECT * FROM schedule_templates
    WHERE specialty_id = :spec AND day_of_week = :dow AND active=1
    ORDER BY start_time");
$stmt->execute(["spec" => $specialty_id, "dow" => $dayOfWeek]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$templates) {
    echo json_encode(["times" => []]);
    exit;
}

// 3) generar todos los slots posibles
$allSlots = [];
foreach ($templates as $tpl) {
    // soportar start_time con o sin segundos
    $start = strtotime($date . ' ' . $tpl['start_time']);
    $end = strtotime($date . ' ' . $tpl['end_time']);
    $dur = (int) ($tpl['slot_duration_minutes'] ?? $tpl['slot_minutes'] ?? 30);
    if ($dur <= 0) $dur = 30;
    while ($start + $dur*60 <= $end) {
        $allSlots[] = date('H:i', $start);
        $start += $dur*60;
    }
}

// 4) obtener appointments ocupados
$stmt = $pdo->prepare("SELECT appointment_time FROM appointments
    WHERE specialty_id = :spec AND appointment_date = :fecha
      AND status IN ('pending','confirmed','attended')");
$stmt->execute(["spec" => $specialty_id, "fecha" => $date]);
$takenAppointments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 5) obtener holds activos
$stmt = $pdo->prepare("SELECT appointment_time FROM appointment_holds
    WHERE specialty_id = :spec AND appointment_date = :fecha
      AND expires_at > NOW()");
$stmt->execute(["spec" => $specialty_id, "fecha" => $date]);
$takenHolds = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 6) construir resultado con estados (incluye 'past' si es hoy y ya pasó)
$times = [];
foreach ($allSlots as $t) {
    $status = 'available';
    if ($isBlockedDay) {
        $status = 'blocked';
    } elseif (in_array($t, $takenAppointments)) {
        $status = 'booked';
    } elseif (in_array($t, $takenHolds)) {
        $status = 'held';
    }

    // si es hoy y el slot ya pasó según tiempo del servidor, marcar como 'past'
    if ($is_today && $status === 'available') {
        // comparar HH:MM lexicográficamente funciona para formato HH:MM
        if ($t <= $currentTime) {
            $status = 'past';
        }
    }

    $times[] = ['time' => $t, 'status' => $status];
}

echo json_encode(['times' => $times]);
exit;

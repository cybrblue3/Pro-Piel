<?php
session_start();
require("../config/db.php");
require("../vendor/autoload.php");

use setasign\Fpdi\Fpdi;

if (!isset($_SESSION['appointment_id']) || !isset($_SESSION['patient'])) {
    die("Sesión inválida. Reinicia el proceso.");
}

$appointment_id = $_SESSION['appointment_id'];
$patient = $_SESSION['patient'];

// ==========================
// Calcular edad (si hay birth_date)
// ==========================
$edad = "";
if (!empty($patient['birth_date'])) {
    $birthDate = new DateTime($patient['birth_date']);
    $today = new DateTime("today");
    $edad = $birthDate->diff($today)->y;
}

// ==========================
// Validar firma
// ==========================
if (empty($_POST['signature'])) {
    die("No se recibió la firma.");
}

// Guardar firma como archivo temporal
$data = $_POST['signature'];
$img = str_replace("data:image/png;base64,", "", $data);
$img = str_replace(" ", "+", $img);
$imgData = base64_decode($img);

$upload_dir = __DIR__ . "/../public/uploads/consents/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$signatureFile = $upload_dir . "sign_" . time() . ".png";
file_put_contents($signatureFile, $imgData);

// ==========================
// Traer datos de la cita
// ==========================
$stmt = $pdo->prepare("
    SELECT a.appointment_date, a.appointment_time
    FROM appointments a
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// ==========================
// CREACIÓN DEL PDF CON PLANTILLA
// ==========================
$pdf = new Fpdi();

$templatePath = __DIR__ . "/../public/templates/CONSENTIMIENTO_INFORMADO.pdf";
$pageCount = $pdf->setSourceFile($templatePath);
$templateId = $pdf->importPage(1);
$pdf->AddPage();
$pdf->useTemplate($templateId, 0, 0, 210); // A4

$pdf->SetFont("Arial","",11);
$pdf->SetTextColor(0,0,0);

// ==========================
// DEBUG MODE
// ==========================
$debug = false; // 🔴 Cambia a false cuando termines ajustes

if ($debug) {
    $pdf->SetFont("Arial","B",11);
    $pdf->SetTextColor(200,0,0);

    // Coordenadas Y
    for ($y = 20; $y < 280; $y += 10) {
        $pdf->SetXY(2, $y);
        $pdf->Write(3, "Y".$y);
        $pdf->Line(0, $y, 210, $y);
    }

    // Coordenadas X
    for ($x = 10; $x < 200; $x += 10) {
        $pdf->SetXY($x, 5);
        $pdf->Write(3, "X".$x);
        $pdf->Line($x, 0, $x, 297);
    }
    $pdf->SetTextColor(0,0,0);
}

// ==========================
// CAMPOS DEL CONSENTIMIENTO
// ==========================

// Nombre
$pdf->SetXY(70, 34);
$pdf->Write(5, utf8_decode($patient['full_name']));
if ($debug) $pdf->Rect(70, 34, 60, 6, "D");

// Edad
$pdf->SetXY(163, 46);
$pdf->Write(5, $edad ? $edad." ".utf8_decode("años") : "");
if ($debug) $pdf->Rect(160, 34, 30, 6, "D");

// Sexo
$pdf->SetXY(45, 51);
$pdf->Write(5, utf8_decode($patient['sex']));
if ($debug) $pdf->Rect(45, 51, 30, 6, "D");

// Teléfono
$pdf->SetXY(100, 51);
$pdf->Write(5, isset($patient['phone']) ? $patient['phone'] : "");
if ($debug) $pdf->Rect(40, 60, 40, 6, "D");

// Primera vez / subsecuente → con "X"
if (!empty($patient['is_first_time']) && $patient['is_first_time'] == 1) {
    $pdf->Text(57, 59, "X"); // Primera vez
    if ($debug) $pdf->Rect(57, 59, 5, 5, "D");
} else {
    $pdf->Text(87, 59, "X"); // Subsecuente
    if ($debug) $pdf->Rect(87, 59, 5, 5, "D");
}

// Lugar (ajústalo según tu plantilla)
$pdf->SetXY(46, 205);
$pdf->Write(5, utf8_decode("Clínica ProPiel"));
if ($debug) $pdf->Rect(40, 90, 60, 6, "D");

// Fecha
$pdf->SetXY(94, 205);
$pdf->Write(5, $appointment['appointment_date']);
if ($debug) $pdf->Rect(120, 90, 30, 6, "D");

// Hora
$pdf->SetXY(128, 205);
$pdf->Write(5, $appointment['appointment_time']);
if ($debug) $pdf->Rect(160, 90, 30, 6, "D");

// Firma
$pdf->Image($signatureFile, 110, 230, 80, 0, "PNG");
if ($debug) $pdf->Rect(60, 240, 80, 30, "D");

// ==========================
// GUARDAR PDF FINAL
// ==========================

// Normalizar nombre archivo
$nombreArchivo = "consentimiento_" . preg_replace('/\s+/', '_', strtolower($patient['full_name']));
$nombreArchivo = iconv('UTF-8', 'ASCII//TRANSLIT', $nombreArchivo); // quita acentos
$nombreArchivo = preg_replace('/[^a-z0-9_]/', '', $nombreArchivo); // deja solo letras/números/_

// Agregar id cita
$pdfFile = $upload_dir . $nombreArchivo . "_" . $appointment_id . ".pdf";

$pdf->Output("F", $pdfFile);

// ==========================
// Guardar en BD
// ==========================
$stmt = $pdo->prepare("UPDATE appointments SET consent_pdf = ? WHERE id = ?");
$stmt->execute([basename($pdfFile), $appointment_id]);

// ==========================
// Redirigir
// ==========================
header("Location: ../patient/success.php");
exit;

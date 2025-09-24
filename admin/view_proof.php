<?php
// admin/view_proof.php
session_start();
require_once __DIR__ . '/../auth_check.php';
require_role('admin'); // sólo admin por ahora; si médicos también deben ver, ajustar

require_once __DIR__ . '/../config/db.php';

$proof_id = (int)($_GET['id'] ?? 0);
if (!$proof_id) {
    http_response_code(400);
    echo "ID inválido.";
    exit;
}

// Buscar comprobante
$stmt = $pdo->prepare("SELECT filename, mime, size_bytes, appointment_id FROM payment_proofs WHERE id = ? LIMIT 1");
$stmt->execute([$proof_id]);
$proof = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$proof) {
    http_response_code(404);
    echo "Comprobante no encontrado.";
    exit;
}

// Ruta real donde guardas los archivos (revisar que coincida con upload_payment.php)
$baseDir = realpath(__DIR__ . "/../public/uploads/comprobantes/");
$filepath = $baseDir . DIRECTORY_SEPARATOR . $proof['filename'];

// seguridad: evitar traversal
$real = realpath($filepath);
if (!$real || strpos($real, $baseDir) !== 0 || !is_file($real)) {
    http_response_code(404);
    echo "Archivo no encontrado.";
    exit;
}

// Enviar headers según mime
$mime = $proof['mime'] ?: mime_content_type($real);
$fname = basename($real);

// Para mostrar inline (previsualizar) para pdf/jpg/png, etc.
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Content-Disposition: inline; filename="'. $fname .'"');
// caching (opcional)
header('Cache-Control: private, max-age=86400');

// Entregar archivo
readfile($real);
exit;

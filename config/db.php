<?php
$host = "localhost: 3306";
$dbname = "propiel";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // PHP en hora local de México
    date_default_timezone_set('America/Mexico_City');

    // MySQL con offset UTC-6
    $pdo->exec("SET time_zone = '-06:00'");

} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

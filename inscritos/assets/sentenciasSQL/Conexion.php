<?php
// DATOS DE LA BASE DE DATOS
$dsn = 'mysql:host=localhost;dbname=inscritos;charset=utf8mb4'; // <- CAMBIO AQUÍ
$username = 'root';
$password = '';

try {
    // CREAR LA CONEXIÓN
    $pdo = new PDO($dsn, $username, $password);
    // ESTABLECER MODO DE ERROR
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // FUERZA LA CONEXIÓN A UTF-8MB4 (por si acaso)
    $pdo->exec("SET NAMES utf8mb4");
    
} catch (PDOException $e) {
    die("CONEXIÓN FALLIDA: " . $e->getMessage());
}
?>

<?php
// ============================================================
// API: Historial de Interacciones — via AJAX
// Archivo: assets/api/historial_api.php
// NUEVO: endpoints JSON para historial
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Historial.php';

$model = new Historial();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body        = json_decode(file_get_contents('php://input'), true);
    $idAspirante = (int)($body['id_aspirante'] ?? 0);
    $tipo        = $body['tipo']        ?? 'nota';
    $descripcion = trim($body['descripcion'] ?? '');

    if (!$idAspirante || !$descripcion) {
        echo json_encode(['ok' => false, 'mensaje' => 'Datos incompletos']);
        exit();
    }

    $id = $model->agregar($idAspirante, $tipo, $descripcion);
    echo json_encode($id
        ? ['ok' => true, 'id' => $id]
        : ['ok' => false, 'mensaje' => 'Error al guardar']
    );
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id_aspirante'])) {
    $lista = $model->obtenerPorAspirante((int)$_GET['id_aspirante']);
    echo json_encode(['ok' => true, 'historial' => $lista]);
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado']);

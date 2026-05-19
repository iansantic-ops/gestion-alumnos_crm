<?php
// ============================================================
// API: Agenda y Recordatorios — via AJAX
// Archivo: assets/api/agenda_api.php
// NUEVO: endpoints JSON para agenda
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Agenda.php';

$model = new Agenda();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $accion = $body['accion'] ?? '';

    switch ($accion) {
        case 'crear':
            $id = $model->crear([
                'titulo'       => trim($body['titulo']      ?? ''),
                'tipo'         => $body['tipo']             ?? 'tarea',
                'fecha_hora'   => $body['fecha_hora']       ?? date('Y-m-d H:i:s'),
                'id_aspirante' => $body['id_aspirante']     ?: null,
            ]);
            echo json_encode($id
                ? ['ok' => true, 'id' => $id]
                : ['ok' => false, 'mensaje' => 'Error al crear']
            );
            break;

        case 'completar':
            $id = (int)($body['id'] ?? 0);
            $ok = $model->marcarCompletado($id);
            echo json_encode(['ok' => $ok]);
            break;

        case 'eliminar':
            $id = (int)($body['id'] ?? 0);
            $ok = $model->eliminar($id);
            echo json_encode(['ok' => $ok]);
            break;

        default:
            echo json_encode(['ok' => false, 'mensaje' => 'Acción desconocida']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proximos = $model->obtenerProximos(20);
    echo json_encode(['ok' => true, 'eventos' => $proximos]);
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado']);

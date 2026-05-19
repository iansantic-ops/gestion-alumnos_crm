<?php
// ============================================================
// API: Carreras — CRUD via AJAX
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Carrera.php';
$model = new Carrera();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');
    if ($accion === 'obtener') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']); exit(); }
        $c = $model->obtenerPorId($id);
        echo json_encode($c ? ['ok' => true, 'carrera' => $c] : ['ok' => false, 'mensaje' => 'No encontrada.']);
    } elseif ($accion === 'listar') {
        echo json_encode(['ok' => true, 'carreras' => $model->listarTodas()]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Acción desconocida.']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { echo json_encode(['ok' => false, 'mensaje' => 'Petición inválida.']); exit(); }

    $accion = trim($body['accion'] ?? '');

    switch ($accion) {
        case 'crear':
            echo json_encode($model->crear($body['nombre'] ?? ''));
            break;
        case 'actualizar':
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']); break; }
            echo json_encode($model->actualizar(
                $id,
                $body['nombre'] ?? '',
                isset($body['activa']) ? (int)$body['activa'] : 1
            ));
            break;
        case 'eliminar':
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']); break; }
            echo json_encode($model->eliminar($id));
            break;
        default:
            echo json_encode(['ok' => false, 'mensaje' => 'Acción desconocida.']);
    }
    exit();
}
echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado.']);

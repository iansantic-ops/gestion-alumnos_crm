<?php
// ============================================================
// API: Becas — CRUD via AJAX
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Beca.php';
$model = new Beca();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');
    if ($accion === 'obtener') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']); exit(); }
        $b = $model->obtenerPorId($id);
        echo json_encode($b ? ['ok' => true, 'beca' => $b] : ['ok' => false, 'mensaje' => 'No encontrada.']);
    } elseif ($accion === 'listar') {
        echo json_encode(['ok' => true, 'becas' => $model->listarTodas()]);
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
            $desc = filter_var($body['descuento'] ?? 0, FILTER_VALIDATE_FLOAT);
            if ($desc === false) { echo json_encode(['ok' => false, 'mensaje' => 'Descuento inválido.']); break; }
            echo json_encode($model->crear($body['nombre'] ?? '', $desc));
            break;
        case 'actualizar':
            $id   = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            $desc = filter_var($body['descuento'] ?? 0, FILTER_VALIDATE_FLOAT);
            if (!$id || $desc === false) { echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos.']); break; }
            echo json_encode($model->actualizar(
                $id,
                $body['nombre'] ?? '',
                $desc,
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

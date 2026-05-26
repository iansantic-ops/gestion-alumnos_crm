<?php
// ============================================================
// API: Aspirantes — CRUD via AJAX
// Archivo: assets/api/aspirantes_api.php
// MEJORAS: validaciones con Aspirante::validar() antes de
//          INSERT/UPDATE; respuestas JSON consistentes con
//          campo 'errores' para el frontend
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

// Requiere sesión activa
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Aspirante.php';

$model = new Aspirante();

// ── GET ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');

    if ($accion === 'obtener') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
            exit();
        }
        $aspirante = $model->obtenerPorId($id);
        echo json_encode($aspirante
            ? ['ok' => true, 'aspirante' => $aspirante]
            : ['ok' => false, 'mensaje'   => 'Aspirante no encontrado.']
        );

    } elseif ($accion === 'listar') {
        $filtros = [
            'etapa'      => trim($_GET['etapa']    ?? ''),
            'id_carrera' => trim($_GET['carrera']  ?? ''),
            'busqueda'   => trim($_GET['busqueda'] ?? ''),
        ];
        $lista = $model->listar(array_filter($filtros));
        echo json_encode(['ok' => true, 'aspirantes' => $lista]);

    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida.']);
    }
    exit();
}

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!is_array($body)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Cuerpo de petición inválido.']);
        exit();
    }

    $accion = trim($body['accion'] ?? '');

    switch ($accion) {

        // ── Crear aspirante ───────────────────────────────
        case 'crear':
            $errores = $model->validar($body);
            if (!empty($errores)) {
                echo json_encode([
                    'ok'      => false,
                    'mensaje' => $errores[0],   // primer error para el toast
                    'errores' => $errores,       // lista completa por si el front los necesita
                ]);
                break;
            }
            $id = $model->crear($body);
            echo json_encode($id
                ? ['ok' => true,  'id'      => $id]
                : ['ok' => false, 'mensaje' => 'Error interno al crear el aspirante.']
            );
            break;

        // ── Actualizar aspirante ──────────────────────────
        case 'actualizar':
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID inválido para actualizar.']);
                break;
            }
            $errores = $model->validar($body, $id);   // excluye el propio email del dup-check
            if (!empty($errores)) {
                echo json_encode([
                    'ok'      => false,
                    'mensaje' => $errores[0],
                    'errores' => $errores,
                ]);
                break;
            }
            $ok = $model->actualizar($id, $body);
            echo json_encode($ok
                ? ['ok' => true]
                : ['ok' => false, 'mensaje' => 'Error interno al actualizar.']
            );
            break;

            case 'actualizar_etapa':
    $etapasValidas = ['Contacto', 'Interesado', 'Inscrito', 'No Interesado'];
    $etapa = trim($body['etapa'] ?? '');
    $id    = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
        break;
    }
    if (!in_array($etapa, $etapasValidas)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Etapa inválida.']);
        break;
    }

    $ok = $model->actualizarEtapa($id, $etapa);
    echo json_encode($ok
        ? ['ok' => true]
        : ['ok' => false, 'mensaje' => 'Error al actualizar la etapa.']
    );
    break;
    
        // ── Eliminar aspirante ────────────────────────────
        case 'eliminar':
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID inválido para eliminar.']);
                break;
            }
            $ok = $model->eliminar($id);
            echo json_encode(['ok' => $ok]);
            break;

        default:
            echo json_encode(['ok' => false, 'mensaje' => 'Acción desconocida.']);
    }
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'Método HTTP no soportado.']);

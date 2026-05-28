<?php
// ============================================================
// API: Aspirantes — CRUD + paginación + exportación via AJAX
// v4: listar ahora recibe pagina/pp/filtros desde GET;
//     nueva acción 'exportar' que devuelve TODOS los registros
//     sin LIMIT (respetando filtros) para Excel completo.
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Aspirante.php';

$model = new Aspirante();

// ── GET ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');

    // ── Obtener uno ──────────────────────────────────────────
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

    // ── Listar paginado (filtros server-side) ────────────────
    } elseif ($accion === 'listar') {
        $filtros = [
            'etapa'      => trim($_GET['etapa']    ?? ''),
            'id_carrera' => trim($_GET['carrera']  ?? ''),
            'busqueda'   => trim($_GET['busqueda'] ?? ''),
        ];
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = 50;

        $lista = $model->listar(array_filter($filtros), $pagina, $porPagina);
        $total = $model->totalAspirantes;

        echo json_encode([
            'ok'          => true,
            'aspirantes'  => $lista,
            'total'       => $total,
            'pagina'      => $pagina,
            'porPagina'   => $porPagina,
            'totalPaginas'=> (int)ceil($total / $porPagina),
        ]);

    // ── Exportar TODOS (sin LIMIT, con filtros) ──────────────
    } elseif ($accion === 'exportar') {
        $filtros = [
            'etapa'      => trim($_GET['etapa']    ?? ''),
            'id_carrera' => trim($_GET['carrera']  ?? ''),
            'busqueda'   => trim($_GET['busqueda'] ?? ''),
        ];
        // porPagina muy alto = traer todos sin romper la firma del método
        $lista = $model->listar(array_filter($filtros), 1, 999999);
        echo json_encode(['ok' => true, 'aspirantes' => $lista, 'total' => $model->totalAspirantes]);

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

        // ── Crear ─────────────────────────────────────────────
        case 'crear':
            $errores = $model->validar($body);
            if (!empty($errores)) {
                echo json_encode(['ok' => false, 'mensaje' => $errores[0], 'errores' => $errores]);
                break;
            }
            $id = $model->crear($body);
            echo json_encode($id
                ? ['ok' => true,  'id'      => $id]
                : ['ok' => false, 'mensaje' => 'Error interno al crear el aspirante.']
            );
            break;

        // ── Actualizar ────────────────────────────────────────
        case 'actualizar':
            $id = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || $id <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID inválido para actualizar.']);
                break;
            }
            $errores = $model->validar($body, $id);
            if (!empty($errores)) {
                echo json_encode(['ok' => false, 'mensaje' => $errores[0], 'errores' => $errores]);
                break;
            }
            $ok = $model->actualizar($id, $body);
            echo json_encode($ok
                ? ['ok' => true]
                : ['ok' => false, 'mensaje' => 'Error interno al actualizar.']
            );
            break;

        // ── Actualizar etapa (menú flotante) ──────────────────
        case 'actualizar_etapa':
            $etapasValidas = ['Contacto', 'Interesado', 'Inscrito', 'No Interesado'];
            $etapa = trim($body['etapa'] ?? '');
            $id    = filter_var($body['id'] ?? 0, FILTER_VALIDATE_INT);

            if (!$id || $id <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
                break;
            }
            if (!in_array($etapa, $etapasValidas, true)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Etapa inválida.']);
                break;
            }
            $ok = $model->actualizarEtapa($id, $etapa);
            echo json_encode($ok
                ? ['ok' => true]
                : ['ok' => false, 'mensaje' => 'Error al actualizar la etapa.']
            );
            break;

        // ── Eliminar ──────────────────────────────────────────
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

<?php
// ============================================================
// API Aspirantes v5 — CRUD + paginación + exportar + importar
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok'=>false,'mensaje'=>'No autorizado.']); exit();
}

require_once __DIR__ . '/../sentenciasSQL/Aspirante.php';
$model = new Aspirante();

// ── GET ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');

    if ($accion === 'obtener') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); exit(); }
        $a = $model->obtenerPorId($id);
        echo json_encode($a ? ['ok'=>true,'aspirante'=>$a] : ['ok'=>false,'mensaje'=>'No encontrado.']);

    } elseif ($accion === 'listar') {
        $filtros = [
            'etapa'      => trim($_GET['etapa']    ?? ''),
            'id_carrera' => trim($_GET['carrera']  ?? ''),
            'origen'     => trim($_GET['origen']   ?? ''),
            'busqueda'   => trim($_GET['busqueda'] ?? ''),
        ];
        $pagina    = max(1, (int)($_GET['pagina'] ?? 1));
        $porPagina = min(100, max(5, (int)($_GET['porPagina'] ?? 50)));
        $lista  = $model->listar(array_filter($filtros), $pagina, $porPagina);
        $total  = $model->totalAspirantes;
        echo json_encode([
            'ok'=>true,'aspirantes'=>$lista,'total'=>$total,
            'pagina'=>$pagina,'porPagina'=>$porPagina,
            'totalPaginas'=>(int)ceil($total/$porPagina),
        ]);

    } elseif ($accion === 'exportar') {
        $filtros = [
            'etapa'      => trim($_GET['etapa']    ?? ''),
            'id_carrera' => trim($_GET['carrera']  ?? ''),
            'origen'     => trim($_GET['origen']   ?? ''),
            'busqueda'   => trim($_GET['busqueda'] ?? ''),
        ];
        $lista = $model->listar(array_filter($filtros), 1, 999999);
        echo json_encode(['ok'=>true,'aspirantes'=>$lista,'total'=>$model->totalAspirantes]);

    } elseif ($accion === 'papelera') {
        echo json_encode(['ok'=>true,'eliminados'=>$model->listarEliminados()]);

    } else {
        echo json_encode(['ok'=>false,'mensaje'=>'Acción no reconocida.']);
    }
    exit();
}

// ── POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) { echo json_encode(['ok'=>false,'mensaje'=>'Cuerpo inválido.']); exit(); }

    $accion = trim($body['accion'] ?? '');

    switch ($accion) {
        case 'crear':
            $errores = $model->validar($body);
            if ($errores) { echo json_encode(['ok'=>false,'mensaje'=>$errores[0],'errores'=>$errores]); break; }
            $id = $model->crear($body);
            echo json_encode($id ? ['ok'=>true,'id'=>$id] : ['ok'=>false,'mensaje'=>'Error al crear.']);
            break;

        case 'actualizar':
            $id = filter_var($body['id']??0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); break; }
            $errores = $model->validar($body, $id);
            if ($errores) { echo json_encode(['ok'=>false,'mensaje'=>$errores[0],'errores'=>$errores]); break; }
            echo json_encode(['ok'=>$model->actualizar($id, $body)]);
            break;

        case 'actualizar_etapa':
            $etapas = ['Contacto','Interesado','Inscrito','No Interesado'];
            $etapa  = trim($body['etapa'] ?? '');
            $id     = filter_var($body['id']??0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); break; }
            if (!in_array($etapa, $etapas, true)) { echo json_encode(['ok'=>false,'mensaje'=>'Etapa inválida.']); break; }
            echo json_encode(['ok'=>$model->actualizarEtapa($id, $etapa)]);
            break;

        case 'eliminar':   // soft delete
            $id = filter_var($body['id']??0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); break; }
            echo json_encode(['ok'=>$model->eliminar($id)]);
            break;

        case 'restaurar':
            $id = filter_var($body['id']??0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); break; }
            echo json_encode(['ok'=>$model->restaurar($id)]);
            break;

        case 'eliminar_definitivo':
            $id = filter_var($body['id']??0, FILTER_VALIDATE_INT);
            if (!$id) { echo json_encode(['ok'=>false,'mensaje'=>'ID inválido.']); break; }
            echo json_encode(['ok'=>$model->eliminarDefinitivamente($id)]);
            break;

        case 'masivo':
            $ids   = array_filter(array_map('intval', $body['ids'] ?? []), fn($i) => $i > 0);
            $op    = trim($body['operacion'] ?? '');
            if (empty($ids)) { echo json_encode(['ok'=>false,'mensaje'=>'Sin registros seleccionados.']); break; }
            require_once __DIR__ . '/../sentenciasSQL/Conexion.php';
            $procesados = 0;
            if ($op === 'cambiar_etapa') {
                $etapas = ['Contacto','Interesado','Inscrito','No Interesado'];
                $etapa  = trim($body['etapa'] ?? '');
                if (!in_array($etapa, $etapas, true)) { echo json_encode(['ok'=>false,'mensaje'=>'Etapa inválida.']); break; }
                $in  = implode(',', array_fill(0, count($ids), '?'));
                $st  = $pdo->prepare("UPDATE aspirantes SET etapa=? WHERE id_aspirante IN ($in) AND eliminado_en IS NULL");
                $st->execute(array_merge([$etapa], $ids));
                $procesados = $st->rowCount();
            } elseif ($op === 'agregar_nota') {
                $nota = htmlspecialchars(trim($body['nota'] ?? ''), ENT_QUOTES, 'UTF-8');
                if (!$nota) { echo json_encode(['ok'=>false,'mensaje'=>'La nota no puede estar vacía.']); break; }
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("UPDATE aspirantes SET notas=CONCAT(IFNULL(notas,''),'\n[Nota masiva] ',:nota) WHERE id_aspirante IN ($in) AND eliminado_en IS NULL");
                $params = array_merge([':nota'=>$nota], $ids);
                // Usar bindValue por separado
                $in2 = implode(',', array_fill(0, count($ids), '?'));
                $st2 = $pdo->prepare("UPDATE aspirantes SET notas=CONCAT(IFNULL(notas,''),' | ',:nota) WHERE id_aspirante IN ($in2) AND eliminado_en IS NULL");
                $st2->bindValue(':nota', $nota);
                foreach ($ids as $i => $id) $st2->bindValue($i+1, $id, PDO::PARAM_INT);
                $st2->execute();
                $procesados = $st2->rowCount();
            } elseif ($op === 'carrera_interes') {
                $carrera = htmlspecialchars(trim($body['carrera'] ?? ''), ENT_QUOTES, 'UTF-8');
                if (!$carrera) { echo json_encode(['ok'=>false,'mensaje'=>'Ingresa el nombre de la carrera.']); break; }
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("UPDATE aspirantes SET carreras_interes=CONCAT(IFNULL(NULLIF(carreras_interes,''),''),IF(NULLIF(carreras_interes,'') IS NULL,'',', '),:c) WHERE id_aspirante IN ($in) AND eliminado_en IS NULL");
                $st->bindValue(':c', $carrera);
                foreach ($ids as $i => $id) $st->bindValue($i+1, $id, PDO::PARAM_INT);
                $st->execute();
                $procesados = $st->rowCount();
            } elseif ($op === 'eliminar') {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("UPDATE aspirantes SET eliminado_en=NOW() WHERE id_aspirante IN ($in) AND eliminado_en IS NULL");
                $st->execute($ids);
                $procesados = $st->rowCount();
            }
            echo json_encode(['ok'=>true,'procesados'=>$procesados,'mensaje'=>"$procesados aspirantes actualizados."]);
            break;

        case 'importar':
            // Recibe array 'filas' con aspirantes a crear en lote
            $filas = $body['filas'] ?? [];
            if (!is_array($filas) || empty($filas)) {
                echo json_encode(['ok'=>false,'mensaje'=>'No se enviaron datos.']); break;
            }
            $exitosos = 0; $fallidos = []; $duplicados = 0;
            foreach ($filas as $i => $fila) {
                // Resolver carrera por nombre si se envió como texto
                if (!empty($fila['carrera_nombre']) && empty($fila['id_carrera'])) {
                    $fila['id_carrera'] = $model->buscarCarreraPorNombre($fila['carrera_nombre']);
                }
                $errores = $model->validar($fila);
                if ($errores) {
                    $fallidos[] = ['fila'=>$i+1,'nombre'=>$fila['nombre']??'','error'=>$errores[0]];
                    if (str_contains($errores[0],'correo')) $duplicados++;
                    continue;
                }
                $id = $model->crear($fila);
                if ($id) $exitosos++; else $fallidos[] = ['fila'=>$i+1,'nombre'=>$fila['nombre']??'','error'=>'Error interno.'];
            }
            echo json_encode(['ok'=>true,'exitosos'=>$exitosos,'fallidos'=>$fallidos,'duplicados'=>$duplicados]);
            break;

        default:
            echo json_encode(['ok'=>false,'mensaje'=>'Acción desconocida.']);
    }
    exit();
}
echo json_encode(['ok'=>false,'mensaje'=>'Método no soportado.']);

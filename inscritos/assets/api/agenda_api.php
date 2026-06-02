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
    $accion = trim($_GET['accion'] ?? 'listar');

    if ($accion === 'calendario') {
        // FullCalendar: devuelve todos los eventos como array de objetos FC
        require_once __DIR__ . '/../sentenciasSQL/Conexion.php';
        $stmt = $pdo->query(
            "SELECT ag.id_agenda, ag.tipo, ag.fecha_hora, ag.completado,
                    ag.titulo, a.nombre AS aspirante
             FROM agenda ag
             LEFT JOIN aspirantes a ON ag.id_aspirante = a.id_aspirante
             ORDER BY ag.fecha_hora ASC"
        );
        $eventos = array_map(function($ev) {
            $colores = ['tarea'=>'#0077cc','llamada'=>'#28a745','correo'=>'#e07b00','reunion'=>'#7c3aed'];
            return [
                'id'    => $ev['id_agenda'],
                'title' => ($ev['aspirante'] ? $ev['aspirante'].' — ' : '') . $ev['titulo'],
                'start' => $ev['fecha_hora'],
                'backgroundColor' => $colores[$ev['tipo']] ?? '#888',
                'borderColor'     => $colores[$ev['tipo']] ?? '#888',
                'textColor'       => '#fff',
                'extendedProps'   => ['tipo'=>$ev['tipo'], 'completado'=>$ev['completado']],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        echo json_encode(['ok'=>true,'eventos'=>$eventos]);

    } elseif ($accion === 'pendientes_hoy') {
        require_once __DIR__ . '/../sentenciasSQL/Conexion.php';
        $stmt = $pdo->query(
            "SELECT COUNT(*) AS total FROM agenda
             WHERE completado=0 AND DATE(fecha_hora)=CURDATE()"
        );
        echo json_encode(['ok'=>true,'total'=>(int)$stmt->fetchColumn()]);

    } else {
        $proximos = $model->obtenerProximos(20);
        echo json_encode(['ok' => true, 'eventos' => $proximos]);
    }
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado']);

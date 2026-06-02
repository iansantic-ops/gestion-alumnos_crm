<?php
// ============================================================
// API: Reportes — Estadísticas desde la base de datos
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/Conexion.php';

try {
    // Total aspirantes
    $totalAsp = (int)$pdo->query("SELECT COUNT(*) FROM aspirantes")->fetchColumn();

    // Por etapa
    $stmtEtapa = $pdo->query(
        "SELECT etapa, COUNT(*) as total FROM aspirantes WHERE eliminado_en IS NULL GROUP BY etapa ORDER BY etapa"
    );
    $porEtapa = $stmtEtapa->fetchAll(PDO::FETCH_ASSOC);

    // Por carrera
    $stmtCarrera = $pdo->query(
        "SELECT c.nombre AS carrera, COUNT(a.id_aspirante) AS total
         FROM carreras c
         LEFT JOIN aspirantes a ON a.id_carrera = c.id_carrera
         GROUP BY c.id_carrera, c.nombre
         HAVING total > 0
         ORDER BY total DESC"
    );
    $porCarrera = $stmtCarrera->fetchAll(PDO::FETCH_ASSOC);

    // Becas asignadas
    $stmtBecas = $pdo->query(
        "SELECT b.nombre AS beca, COUNT(a.id_aspirante) AS asignadas, b.descuento
         FROM becas b
         LEFT JOIN aspirantes a ON a.id_beca = b.id_beca
         GROUP BY b.id_beca, b.nombre, b.descuento
         HAVING asignadas > 0
         ORDER BY asignadas DESC"
    );
    $becasAsignadas = $stmtBecas->fetchAll(PDO::FETCH_ASSOC);

    // Total con beca
    $totalConBeca = (int)$pdo->query(
        "SELECT COUNT(*) FROM aspirantes WHERE id_beca IS NOT NULL AND eliminado_en IS NULL AND eliminado_en IS NULL"
    )->fetchColumn();

    // Registros recientes (últimos 10)
    $stmtRec = $pdo->query(
        "SELECT a.nombre, a.email, c.nombre AS carrera, a.etapa, a.creado_en
         FROM aspirantes a
         LEFT JOIN carreras c ON c.id_carrera = a.id_carrera
         ORDER BY a.creado_en DESC
         LIMIT 10"
    );
    $recientes = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

    // Aspirantes por canal de captación
    $stmtOrigen = $pdo->query(
        'SELECT origen, COUNT(*) AS total
         FROM aspirantes WHERE eliminado_en IS NULL
         GROUP BY origen ORDER BY total DESC'
    );
    $porOrigen = $stmtOrigen->fetchAll(PDO::FETCH_ASSOC);

    // Nuevos aspirantes por día (últimos 30 días) para gráfica de línea
    $stmtDiario = $pdo->query(
        "SELECT DATE(creado_en) AS dia, COUNT(*) AS nuevos
          FROM aspirantes
          WHERE eliminado_en IS NULL AND creado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          GROUP BY DATE(creado_en) ORDER BY dia ASC"
    );
    $actividadDiaria = array_map(function($r) {
        return ['dia' => date('d/m', strtotime($r['dia'])), 'nuevos' => (int)$r['nuevos']];
    }, $stmtDiario->fetchAll(PDO::FETCH_ASSOC));

    // Actividad historial (últimos 30 días)
    $stmtHist = $pdo->query(
        "SELECT tipo, COUNT(*) AS total
         FROM historial_interacciones
         WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY tipo ORDER BY total DESC"
    );
    $historial30 = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'             => true,
        'totalAspirantes'=> $totalAsp,
        'totalConBeca'   => $totalConBeca,
        'porEtapa'       => $porEtapa,
        'porCarrera'     => $porCarrera,
        'becasAsignadas' => $becasAsignadas,
        'recientes'      => $recientes,
        'historial30'    => $historial30,
    ]);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error al generar reporte: ' . $e->getMessage()]);
}

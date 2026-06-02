<?php
// ============================================================
// API Configuración — lectura y escritura de configuración
// global del sistema (tabla `configuracion`).
// Los mensajes de WhatsApp se guardan aquí para que sean
// visibles desde cualquier computadora / sesión.
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']); exit();
}

require_once __DIR__ . '/../sentenciasSQL/Conexion.php';

// Claves permitidas (lista blanca para evitar escrituras arbitrarias)
const CLAVES_PERMITIDAS = [
    'wa_msg_contacto',
    'wa_msg_interesado',
    'wa_msg_inscrito',
    'wa_msg_no_interesado',
];

// ── GET: obtener una o todas las claves ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $clave = trim($_GET['clave'] ?? '');

    if ($clave) {
        // Una sola clave
        if (!in_array($clave, CLAVES_PERMITIDAS, true)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Clave no permitida.']); exit();
        }
        $st = $pdo->prepare('SELECT valor FROM configuracion WHERE clave = ?');
        $st->execute([$clave]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'clave' => $clave, 'valor' => $row['valor'] ?? null]);
    } else {
        // Todas las claves de mensajes WA
        $in   = implode(',', array_fill(0, count(CLAVES_PERMITIDAS), '?'));
        $st   = $pdo->prepare("SELECT clave, valor FROM configuracion WHERE clave IN ($in)");
        $st->execute(CLAVES_PERMITIDAS);
        $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);   // ['clave' => 'valor', ...]
        echo json_encode(['ok' => true, 'config' => $rows]);
    }
    exit();
}

// ── POST: guardar una o varias claves ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Cuerpo inválido.']); exit();
    }

    $datos = $body['config'] ?? [];
    if (!is_array($datos) || empty($datos)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Sin datos para guardar.']); exit();
    }

    $guardados = 0;
    $st = $pdo->prepare(
        'INSERT INTO configuracion (clave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );

    foreach ($datos as $clave => $valor) {
        if (!in_array($clave, CLAVES_PERMITIDAS, true)) continue;
        $valor = trim((string) $valor);
        if ($valor === '') continue;
        $st->execute([$clave, $valor]);
        $guardados++;
    }

    echo json_encode([
        'ok'        => $guardados > 0,
        'guardados' => $guardados,
        'mensaje'   => $guardados > 0 ? "Configuración guardada ($guardados claves)." : 'No se guardó nada.',
    ]);
    exit();
}

echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado.']);

<?php
// ============================================================
// API: Admin — Gestión de credenciales del administrador
// Archivo: assets/api/admin_api.php
// NUEVO: permite cambiar usuario/contraseña desde el modal
//        de configuración en el dashboard de forma segura
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo admins autenticados
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no soportado.']);
    exit();
}

require_once __DIR__ . '/../sentenciasSQL/admin.php';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Cuerpo de petición inválido.']);
    exit();
}

$accion = trim($body['accion'] ?? '');

if ($accion === 'cambiar_credenciales') {

    // ── Recoger y validar campos ──────────────────────────────
    $contrasenaActual  = $body['contrasena_actual']  ?? '';
    $nuevoUsuario      = trim($body['nuevo_usuario'] ?? '');
    $nuevaContrasena   = $body['nueva_contrasena']   ?? '';
    $confirmarContrasena = $body['confirmar_contrasena'] ?? '';

    if (empty($contrasenaActual)) {
        echo json_encode(['ok' => false, 'mensaje' => 'La contraseña actual es obligatoria.']);
        exit();
    }
    if (empty($nuevoUsuario)) {
        echo json_encode(['ok' => false, 'mensaje' => 'El nombre de usuario no puede estar vacío.']);
        exit();
    }
    if (strlen($nuevoUsuario) < 3 || strlen($nuevoUsuario) > 100) {
        echo json_encode(['ok' => false, 'mensaje' => 'El usuario debe tener entre 3 y 100 caracteres.']);
        exit();
    }
    // Solo letras, números, guión y punto permitidos en el usuario
    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $nuevoUsuario)) {
        echo json_encode(['ok' => false, 'mensaje' => 'El usuario solo puede contener letras, números, puntos y guiones.']);
        exit();
    }
    if (empty($nuevaContrasena)) {
        echo json_encode(['ok' => false, 'mensaje' => 'La nueva contraseña no puede estar vacía.']);
        exit();
    }
    if (strlen($nuevaContrasena) < 6) {
        echo json_encode(['ok' => false, 'mensaje' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
        exit();
    }
    if ($nuevaContrasena !== $confirmarContrasena) {
        echo json_encode(['ok' => false, 'mensaje' => 'Las contraseñas no coinciden.']);
        exit();
    }

    // ── Delegar la lógica al modelo ───────────────────────────
    $adminModel = new Admin();
    $resultado  = $adminModel->cambiarCredenciales(
        (int)$_SESSION['id_usuario'],
        $contrasenaActual,
        $nuevoUsuario,
        $nuevaContrasena
    );

    // Si el cambio fue exitoso, actualizar el usuario en sesión
    if ($resultado['ok']) {
        $_SESSION['usuario'] = $nuevoUsuario;
    }

    echo json_encode($resultado);

} else {
    echo json_encode(['ok' => false, 'mensaje' => 'Acción no reconocida.']);
}

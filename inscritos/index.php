<?php
// ============================================================
// CRM Universitario — Login
// SEGURIDAD: la verificación de contraseña se delega a
//   Admin::leerAdmin() que usa password_verify() internamente.
//   Nunca se compara la contraseña en texto plano contra la BD.
// ============================================================
session_start();

// Si ya hay sesión activa redirigir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: pagina_principal.php");
    exit();
}

require_once __DIR__ . "/assets/sentenciasSQL/admin.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar'])) {

    // Sanitizar entradas: trim; no se hace htmlspecialchars aquí porque
    // las contraseñas pueden contener caracteres especiales válidos
    $usuario    = trim($_POST['usuario']    ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (empty($usuario) || empty($contrasena)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        $admin     = new Admin();
        // leerAdmin() busca por usuario y valida el hash con password_verify()
        $adminData = $admin->leerAdmin($usuario, $contrasena);

        if ($adminData) {
            // Guardar datos en sesión (sin el hash de contraseña)
            $_SESSION['id_usuario'] = $adminData['id_usuario'];
            $_SESSION['usuario']    = $adminData['usuario'];
            $_SESSION['nombre']     = $adminData['nombre'] ?? 'Admin';
            header("Location: pagina_principal.php");
            exit();
        } else {
            // Mensaje genérico para no revelar si el usuario existe
            $error = "Usuario o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Universitario — Iniciar Sesión</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-logo">
            <div class="icon">🎓</div>
            <h1>CRM Universitario</h1>
            <p>Gestión de Aspirantes</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form action="" method="post">
            <div class="input-group">
                <label for="usuario">Usuario</label>
                <input type="text" name="usuario" id="usuario"
                       placeholder="Ingresa tu usuario"
                       autocomplete="username" required>
            </div>
            <div class="input-group">
                <label for="contrasena">Contraseña</label>
                <input type="password" name="contrasena" id="contrasena"
                       placeholder="Ingresa tu contraseña"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" name="iniciar" class="btn-login">Iniciar sesión</button>
        </form>

        <p class="login-footer">CRM Universitario &copy; <?= date('Y') ?></p>
    </div>
</body>
</html>

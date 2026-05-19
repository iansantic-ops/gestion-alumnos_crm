<?php
// ============================================================
// Clase Admin — Gestión de usuarios administradores
// Archivo: assets/sentenciasSQL/admin.php
// MEJORAS DE SEGURIDAD:
//   - leerAdmin()           usa password_verify() en lugar de
//                           comparar la contraseña en SQL plain-text
//   - actualizarAdmin()     aplica password_hash() antes de guardar
//   - cambiarCredenciales() método nuevo: valida contraseña actual,
//                           verifica confirmación y guarda con hash
// ============================================================
class Admin {

    /**
     * Valida credenciales de login.
     * Busca solo por usuario y verifica el hash con password_verify().
     *
     * @param string $usuario
     * @param string $contrasena  Contraseña en texto plano enviada por el form
     * @return array|false  Datos del admin o false si no coinciden
     */
    public function leerAdmin(string $usuario, string $contrasena): array|false {
        include __DIR__ . "/Conexion.php";

        // Solo filtramos por usuario; el hash se verifica en PHP
        $stmt = $pdo->prepare(
            "SELECT id_usuario, usuario, nombre, contrasena
             FROM usuarios
             WHERE usuario = :usuario
             LIMIT 1"
        );
        $stmt->execute([':usuario' => $usuario]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return false;
        }

        // password_verify() compara texto plano contra hash bcrypt ($2y$ o $2b$)
        if (!password_verify($contrasena, $admin['contrasena'])) {
            return false;
        }

        // No devolver el hash al resto de la aplicación
        unset($admin['contrasena']);
        return $admin;
    }

    /**
     * Actualiza usuario y contraseña de un admin.
     * La contraseña se hashea automáticamente antes de guardar.
     *
     * @param int    $id
     * @param string $usuario
     * @param string $contrasena  Texto plano; se hashea dentro del método
     * @return bool
     */
    public function actualizarAdmin(int $id, string $usuario, string $contrasena): bool {
        include __DIR__ . "/Conexion.php";

        $hash = password_hash($contrasena, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "UPDATE usuarios
             SET usuario    = :usuario,
                 contrasena = :contrasena
             WHERE id_usuario = :id"
        );
        return $stmt->execute([
            ':usuario'    => $usuario,
            ':contrasena' => $hash,
            ':id'         => $id,
        ]);
    }

    /**
     * Cambia las credenciales de forma segura desde el modal del dashboard.
     * Valida contraseña actual antes de aplicar el cambio.
     *
     * @param int    $idUsuario        ID del admin en sesión
     * @param string $contrasenaActual Texto plano para verificar identidad
     * @param string $nuevoUsuario     Nuevo nombre de usuario (puede ser igual al actual)
     * @param string $nuevaContrasena  Nueva contraseña en texto plano
     * @return array  ['ok' => bool, 'mensaje' => string]
     */
    public function cambiarCredenciales(
        int    $idUsuario,
        string $contrasenaActual,
        string $nuevoUsuario,
        string $nuevaContrasena
    ): array {
        include __DIR__ . "/Conexion.php";

        // 1. Obtener datos actuales del admin
        $stmt = $pdo->prepare(
            "SELECT usuario, contrasena FROM usuarios WHERE id_usuario = :id LIMIT 1"
        );
        $stmt->execute([':id' => $idUsuario]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return ['ok' => false, 'mensaje' => 'Administrador no encontrado.'];
        }

        // 2. Verificar contraseña actual
        if (!password_verify($contrasenaActual, $admin['contrasena'])) {
            return ['ok' => false, 'mensaje' => 'La contraseña actual es incorrecta.'];
        }

        // 3. Validar nuevo usuario (solo si cambió)
        $nuevoUsuario = trim($nuevoUsuario);
        if (empty($nuevoUsuario) || strlen($nuevoUsuario) < 3 || strlen($nuevoUsuario) > 100) {
            return ['ok' => false, 'mensaje' => 'El usuario debe tener entre 3 y 100 caracteres.'];
        }

        // Verificar que el nuevo usuario no esté tomado por otro admin
        if ($nuevoUsuario !== $admin['usuario']) {
            $stmtCheck = $pdo->prepare(
                "SELECT id_usuario FROM usuarios WHERE usuario = :u AND id_usuario != :id LIMIT 1"
            );
            $stmtCheck->execute([':u' => $nuevoUsuario, ':id' => $idUsuario]);
            if ($stmtCheck->fetch()) {
                return ['ok' => false, 'mensaje' => 'Ese nombre de usuario ya está en uso.'];
            }
        }

        // 4. Hashear nueva contraseña
        $hash = password_hash($nuevaContrasena, PASSWORD_BCRYPT);

        // 5. Guardar cambios
        $stmtUpd = $pdo->prepare(
            "UPDATE usuarios
             SET usuario    = :usuario,
                 contrasena = :contrasena
             WHERE id_usuario = :id"
        );
        $ok = $stmtUpd->execute([
            ':usuario'    => $nuevoUsuario,
            ':contrasena' => $hash,
            ':id'         => $idUsuario,
        ]);

        return $ok
            ? ['ok' => true,  'mensaje' => 'Credenciales actualizadas correctamente.']
            : ['ok' => false, 'mensaje' => 'Error al guardar los cambios.'];
    }

    /**
     * Obtiene los datos de un admin por ID (sin devolver el hash).
     *
     * @param int $id
     * @return array|false
     */
    public function obtenerAdminPorId(int $id): array|false {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "SELECT id_usuario, usuario, nombre FROM usuarios WHERE id_usuario = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

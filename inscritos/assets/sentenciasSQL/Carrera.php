<?php
// ============================================================
// Clase Carrera — Catálogo de carreras universitarias
// CRUD completo con PDO preparado y validaciones
// ============================================================
class Carrera {

    private function conexion(): PDO {
        include __DIR__ . "/Conexion.php";
        return $pdo;
    }

    public function listarActivas(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            "SELECT id_carrera, nombre FROM carreras WHERE activa = 1 ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTodas(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            "SELECT id_carrera, nombre, activa FROM carreras ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare(
            "SELECT id_carrera, nombre, activa FROM carreras WHERE id_carrera = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre): array {
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        if (empty($nombre) || strlen($nombre) < 3) {
            return ['ok' => false, 'mensaje' => 'El nombre debe tener al menos 3 caracteres.'];
        }
        if (strlen($nombre) > 150) {
            return ['ok' => false, 'mensaje' => 'El nombre no puede superar 150 caracteres.'];
        }
        $pdo = $this->conexion();
        $dup = $pdo->prepare("SELECT id_carrera FROM carreras WHERE nombre = :n LIMIT 1");
        $dup->execute([':n' => $nombre]);
        if ($dup->fetch()) {
            return ['ok' => false, 'mensaje' => 'Ya existe una carrera con ese nombre.'];
        }
        $stmt = $pdo->prepare("INSERT INTO carreras (nombre, activa) VALUES (:n, 1)");
        $ok   = $stmt->execute([':n' => $nombre]);
        return $ok
            ? ['ok' => true,  'id'      => (int)$pdo->lastInsertId()]
            : ['ok' => false, 'mensaje' => 'Error interno al crear la carrera.'];
    }

    public function actualizar(int $id, string $nombre, int $activa): array {
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        if (empty($nombre) || strlen($nombre) < 3) {
            return ['ok' => false, 'mensaje' => 'El nombre debe tener al menos 3 caracteres.'];
        }
        $pdo = $this->conexion();
        $dup = $pdo->prepare(
            "SELECT id_carrera FROM carreras WHERE nombre = :n AND id_carrera != :id LIMIT 1"
        );
        $dup->execute([':n' => $nombre, ':id' => $id]);
        if ($dup->fetch()) {
            return ['ok' => false, 'mensaje' => 'Ya existe una carrera con ese nombre.'];
        }
        $stmt = $pdo->prepare(
            "UPDATE carreras SET nombre = :n, activa = :a WHERE id_carrera = :id"
        );
        $ok = $stmt->execute([':n' => $nombre, ':a' => $activa, ':id' => $id]);
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'mensaje' => 'Error interno al actualizar.'];
    }

    public function eliminar(int $id): array {
        $pdo = $this->conexion();
        $check = $pdo->prepare("SELECT COUNT(*) FROM aspirantes WHERE id_carrera = :id");
        $check->execute([':id' => $id]);
        if ((int)$check->fetchColumn() > 0) {
            return ['ok' => false, 'mensaje' => 'No se puede eliminar: hay aspirantes en esta carrera. Desactívela en su lugar.'];
        }
        $stmt = $pdo->prepare("DELETE FROM carreras WHERE id_carrera = :id");
        $ok   = $stmt->execute([':id' => $id]);
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'mensaje' => 'Error interno al eliminar.'];
    }
}

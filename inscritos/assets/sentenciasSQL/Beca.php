<?php
// ============================================================
// Clase Beca — Catálogo de becas disponibles
// CRUD completo con PDO preparado y validaciones
// ============================================================
class Beca {

    private function conexion(): PDO {
        include __DIR__ . "/Conexion.php";
        return $pdo;
    }

    public function listarActivas(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            "SELECT id_beca, nombre, descuento FROM becas WHERE activa = 1 ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTodas(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            "SELECT id_beca, nombre, descuento, activa FROM becas ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): array|false {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare(
            "SELECT id_beca, nombre, descuento, activa FROM becas WHERE id_beca = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, float $descuento): array {
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        if (empty($nombre) || strlen($nombre) < 3) {
            return ['ok' => false, 'mensaje' => 'El nombre debe tener al menos 3 caracteres.'];
        }
        if ($descuento < 0 || $descuento > 100) {
            return ['ok' => false, 'mensaje' => 'El porcentaje de descuento debe estar entre 0 y 100.'];
        }
        $pdo = $this->conexion();
        $dup = $pdo->prepare("SELECT id_beca FROM becas WHERE nombre = :n LIMIT 1");
        $dup->execute([':n' => $nombre]);
        if ($dup->fetch()) {
            return ['ok' => false, 'mensaje' => 'Ya existe una beca con ese nombre.'];
        }
        $stmt = $pdo->prepare(
            "INSERT INTO becas (nombre, descuento, activa) VALUES (:n, :d, 1)"
        );
        $ok = $stmt->execute([':n' => $nombre, ':d' => round($descuento, 2)]);
        return $ok
            ? ['ok' => true,  'id'      => (int)$pdo->lastInsertId()]
            : ['ok' => false, 'mensaje' => 'Error interno al crear la beca.'];
    }

    public function actualizar(int $id, string $nombre, float $descuento, int $activa): array {
        $nombre = trim(htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'));
        if (empty($nombre) || strlen($nombre) < 3) {
            return ['ok' => false, 'mensaje' => 'El nombre debe tener al menos 3 caracteres.'];
        }
        if ($descuento < 0 || $descuento > 100) {
            return ['ok' => false, 'mensaje' => 'El porcentaje debe estar entre 0 y 100.'];
        }
        $pdo = $this->conexion();
        $dup = $pdo->prepare(
            "SELECT id_beca FROM becas WHERE nombre = :n AND id_beca != :id LIMIT 1"
        );
        $dup->execute([':n' => $nombre, ':id' => $id]);
        if ($dup->fetch()) {
            return ['ok' => false, 'mensaje' => 'Ya existe una beca con ese nombre.'];
        }
        $stmt = $pdo->prepare(
            "UPDATE becas SET nombre = :n, descuento = :d, activa = :a WHERE id_beca = :id"
        );
        $ok = $stmt->execute([':n' => $nombre, ':d' => round($descuento, 2), ':a' => $activa, ':id' => $id]);
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'mensaje' => 'Error interno al actualizar.'];
    }

    public function eliminar(int $id): array {
        $pdo  = $this->conexion();
        $check = $pdo->prepare("SELECT COUNT(*) FROM aspirantes WHERE id_beca = :id");
        $check->execute([':id' => $id]);
        if ((int)$check->fetchColumn() > 0) {
            return ['ok' => false, 'mensaje' => 'No se puede eliminar: hay aspirantes con esta beca asignada. Desactívela en su lugar.'];
        }
        $stmt = $pdo->prepare("DELETE FROM becas WHERE id_beca = :id");
        $ok   = $stmt->execute([':id' => $id]);
        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'mensaje' => 'Error interno al eliminar.'];
    }
}

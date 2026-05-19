<?php
// ============================================================
// Clase Historial — Registro de interacciones con aspirantes
// Archivo: assets/sentenciasSQL/Historial.php
// NUEVO: clase completa, no existía en el proyecto original
// ============================================================
class Historial {

    /** Agrega una interacción al historial. Retorna el ID o false. */
    public function agregar(int $idAspirante, string $tipo, string $descripcion): int|false {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "INSERT INTO historial_interacciones (id_aspirante, tipo, descripcion)
             VALUES (:id_aspirante, :tipo, :descripcion)"
        );
        $ok = $stmt->execute([
            ':id_aspirante' => $idAspirante,
            ':tipo'         => $tipo,
            ':descripcion'  => $descripcion,
        ]);
        return $ok ? (int)$pdo->lastInsertId() : false;
    }

    /** Obtiene el historial de un aspirante específico. */
    public function obtenerPorAspirante(int $idAspirante): array {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "SELECT h.*, a.nombre AS aspirante_nombre
             FROM historial_interacciones h
             JOIN aspirantes a ON h.id_aspirante = a.id_aspirante
             WHERE h.id_aspirante = :id
             ORDER BY h.fecha DESC"
        );
        $stmt->execute([':id' => $idAspirante]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Obtiene las interacciones más recientes (todas). */
    public function obtenerRecientes(int $limite = 10): array {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "SELECT h.*, a.nombre AS aspirante_nombre
             FROM historial_interacciones h
             JOIN aspirantes a ON h.id_aspirante = a.id_aspirante
             ORDER BY h.fecha DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Elimina una entrada del historial. Retorna bool. */
    public function eliminar(int $idHistorial): bool {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare("DELETE FROM historial_interacciones WHERE id_historial = :id");
        return $stmt->execute([':id' => $idHistorial]);
    }
}

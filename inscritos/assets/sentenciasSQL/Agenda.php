<?php
// ============================================================
// Clase Agenda — Recordatorios y tareas del CRM
// Archivo: assets/sentenciasSQL/Agenda.php
// NUEVO: clase completa, no existía en el proyecto original
// ============================================================
class Agenda {

    /** Crea un nuevo recordatorio/tarea. Retorna el ID o false. */
    public function crear(array $datos): int|false {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "INSERT INTO agenda (id_aspirante, tipo, titulo, fecha_hora)
             VALUES (:id_aspirante, :tipo, :titulo, :fecha_hora)"
        );
        $ok = $stmt->execute([
            ':id_aspirante' => $datos['id_aspirante'] ?? null,
            ':tipo'         => $datos['tipo']         ?? 'tarea',
            ':titulo'       => $datos['titulo'],
            ':fecha_hora'   => $datos['fecha_hora'],
        ]);
        return $ok ? (int)$pdo->lastInsertId() : false;
    }

    /** Obtiene los próximos eventos sin completar. */
    public function obtenerProximos(int $limite = 10): array {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "SELECT ag.*, a.nombre AS aspirante_nombre
             FROM agenda ag
             LEFT JOIN aspirantes a ON ag.id_aspirante = a.id_aspirante
             WHERE ag.completado = 0
             ORDER BY ag.fecha_hora ASC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Marca un evento como completado. Retorna bool. */
    public function marcarCompletado(int $idAgenda): bool {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "UPDATE agenda SET completado = 1 WHERE id_agenda = :id"
        );
        return $stmt->execute([':id' => $idAgenda]);
    }

    /** Elimina un evento de agenda. Retorna bool. */
    public function eliminar(int $idAgenda): bool {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare("DELETE FROM agenda WHERE id_agenda = :id");
        return $stmt->execute([':id' => $idAgenda]);
    }
}

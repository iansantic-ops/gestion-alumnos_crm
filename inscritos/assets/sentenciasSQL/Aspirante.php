<?php
// ============================================================
// Clase Aspirante — CRM Universitario v5
// Cambios v5: campo origen, soft delete (eliminado_en),
//             restaurar(), eliminarDefinitivamente(),
//             listarEliminados()
// ============================================================
class Aspirante {

    public int $totalAspirantes = 0;

    private const ETAPAS_VALIDAS  = ['Contacto', 'Interesado', 'Inscrito', 'No Interesado'];
    private const ORIGENES_VALIDOS = ['Web', 'Redes sociales', 'Feria', 'Referido', 'Llamada', 'Otro'];

    private function conexion(): PDO {
        include __DIR__ . '/Conexion.php';
        return $pdo;
    }

    // ── Validación ──────────────────────────────────────────
    public function validar(array $datos, int $excluirId = 0): array {
        $errores = [];
        $nombre = trim(strip_tags($datos['nombre'] ?? ''));
        if (empty($nombre))                                              $errores[] = 'El nombre es obligatorio.';
        elseif (strlen($nombre) < 2)                                     $errores[] = 'El nombre debe tener al menos 2 caracteres.';
        elseif (strlen($nombre) > 150)                                   $errores[] = 'El nombre no puede superar 150 caracteres.';
        elseif (!preg_match('/^[\p{L}\s\.\-\']+$/u', $nombre))          $errores[] = 'El nombre contiene caracteres no permitidos.';

        $email = strtolower(trim($datos['email'] ?? ''));
        if (empty($email))                                               $errores[] = 'El email es obligatorio.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))              $errores[] = 'El formato del email no es válido.';
        elseif (strlen($email) > 200)                                    $errores[] = 'El email no puede superar 200 caracteres.';
        elseif ($this->emailExiste($email, $excluirId))                  $errores[] = 'Ya existe un aspirante con ese correo.';

        $telefono = trim($datos['telefono'] ?? '');
        if (!empty($telefono) && !preg_match('/^[\d\s\-\(\)\+]{7,20}$/', $telefono))
            $errores[] = 'El teléfono debe tener entre 7 y 20 dígitos.';

        $etapa = $datos['etapa'] ?? 'Contacto';
        if (!in_array($etapa, self::ETAPAS_VALIDAS, true))
            $errores[] = 'La etapa seleccionada no es válida.';

        return $errores;
    }

    private function emailExiste(string $email, int $excluirId = 0): bool {
        $pdo    = $this->conexion();
        $sql    = 'SELECT id_aspirante FROM aspirantes WHERE email = :email AND eliminado_en IS NULL';
        $params = [':email' => $email];
        if ($excluirId > 0) { $sql .= ' AND id_aspirante != :ex'; $params[':ex'] = $excluirId; }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    private function sanitizar(array $datos): array {
        $origen = in_array($datos['origen'] ?? '', self::ORIGENES_VALIDOS, true)
                  ? $datos['origen'] : 'Otro';
        return [
            'nombre'     => htmlspecialchars(trim(strip_tags($datos['nombre'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'email'      => strtolower(filter_var(trim($datos['email'] ?? ''), FILTER_SANITIZE_EMAIL)),
            'telefono'   => preg_replace('/[^\d\s\-\(\)\+]/', '', trim($datos['telefono'] ?? '')),
            'id_carrera' => !empty($datos['id_carrera']) ? (int)$datos['id_carrera'] : null,
            'etapa'      => in_array($datos['etapa'] ?? '', self::ETAPAS_VALIDAS, true) ? $datos['etapa'] : 'Contacto',
            'origen'     => $origen,
            'id_beca'    => !empty($datos['id_beca']) ? (int)$datos['id_beca'] : null,
            'descuento'  => max(0, min(100, (float)($datos['descuento'] ?? 0))),
            'notas'           => htmlspecialchars(trim(strip_tags($datos['notas']           ?? '')), ENT_QUOTES, 'UTF-8'),
            'carreras_interes' => htmlspecialchars(trim(strip_tags($datos['carreras_interes'] ?? '')), ENT_QUOTES, 'UTF-8'),
        ];
    }

    // ── CRUD ─────────────────────────────────────────────────
    public function crear(array $datos): int|false {
        $pdo = $this->conexion();
        $d   = $this->sanitizar($datos);
        $stmt = $pdo->prepare(
            'INSERT INTO aspirantes (nombre,email,telefono,id_carrera,etapa,origen,id_beca,descuento_aplicado,notas)
             VALUES (:nombre,:email,:telefono,:id_carrera,:etapa,:origen,:id_beca,:descuento,:notas)'
        );
        $ok = $stmt->execute([
            ':nombre'=>$d['nombre'], ':email'=>$d['email'], ':telefono'=>$d['telefono']?:null,
            ':id_carrera'=>$d['id_carrera'], ':etapa'=>$d['etapa'], ':origen'=>$d['origen'],
            ':id_beca'=>$d['id_beca'], ':descuento'=>$d['descuento'], ':notas'=>$d['notas']?:null,
        ]);
        return $ok ? (int)$pdo->lastInsertId() : false;
    }

    public function obtenerPorId(int $id): array|false {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare(
            'SELECT a.*, c.nombre AS carrera, b.nombre AS beca
             FROM aspirantes a
             LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
             LEFT JOIN becas    b ON a.id_beca    = b.id_beca
             WHERE a.id_aspirante = :id AND a.eliminado_en IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
        $pdo = $this->conexion();
        $where  = 'WHERE a.eliminado_en IS NULL';
        $params = [];

        if (!empty($filtros['etapa']) && in_array($filtros['etapa'], self::ETAPAS_VALIDAS, true)) {
            $where .= ' AND a.etapa = :etapa'; $params[':etapa'] = $filtros['etapa'];
        }
        if (!empty($filtros['id_carrera']) && is_numeric($filtros['id_carrera'])) {
            $where .= ' AND a.id_carrera = :id_carrera'; $params[':id_carrera'] = (int)$filtros['id_carrera'];
        }
        if (!empty($filtros['origen']) && in_array($filtros['origen'], self::ORIGENES_VALIDOS, true)) {
            $where .= ' AND a.origen = :origen'; $params[':origen'] = $filtros['origen'];
        }
        if (!empty($filtros['busqueda'])) {
            $like = '%'.str_replace(['%','_'],['\%','\_'],trim($filtros['busqueda'])).'%';
            $where .= ' AND (a.nombre LIKE :b OR a.email LIKE :b2)';
            $params[':b'] = $like; $params[':b2'] = $like;
        }

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM aspirantes a $where");
        $stmtCount->execute($params);
        $this->totalAspirantes = (int)$stmtCount->fetchColumn();

        $order  = "ORDER BY FIELD(a.etapa,'Contacto','Interesado','Inscrito','No Interesado') ASC, a.creado_en DESC";
        $offset = ($pagina - 1) * $porPagina;
        $sql    = "SELECT a.*, c.nombre AS carrera, b.nombre AS beca
                   FROM aspirantes a
                   LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
                   LEFT JOIN becas    b ON a.id_beca    = b.id_beca
                   $where $order LIMIT :limite OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function actualizar(int $id, array $datos): bool {
        $pdo = $this->conexion();
        $d   = $this->sanitizar($datos);
        $stmt = $pdo->prepare(
            'UPDATE aspirantes SET nombre=:nombre,email=:email,telefono=:telefono,
             id_carrera=:id_carrera,origen=:origen,id_beca=:id_beca,
             descuento_aplicado=:descuento,notas=:notas,carreras_interes=:ci
             WHERE id_aspirante=:id AND eliminado_en IS NULL'
        );
        return $stmt->execute([
            ':nombre'=>$d['nombre'], ':email'=>$d['email'], ':telefono'=>$d['telefono']?:null,
            ':id_carrera'=>$d['id_carrera'], ':origen'=>$d['origen'], ':id_beca'=>$d['id_beca'],
            ':descuento'=>$d['descuento'], ':notas'=>$d['notas']?:null, ':ci'=>$d['carreras_interes']?:null, ':id'=>$id,
        ]);
    }

    public function actualizarEtapa(int $id, string $etapa): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('UPDATE aspirantes SET etapa=:etapa WHERE id_aspirante=:id AND eliminado_en IS NULL');
        return $stmt->execute([':etapa'=>$etapa, ':id'=>$id]);
    }

    /** Soft delete — mueve a papelera */
    public function eliminar(int $id): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('UPDATE aspirantes SET eliminado_en=NOW() WHERE id_aspirante=:id AND eliminado_en IS NULL');
        return $stmt->execute([':id' => $id]);
    }

    /** Restaura un aspirante de la papelera */
    public function restaurar(int $id): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('UPDATE aspirantes SET eliminado_en=NULL WHERE id_aspirante=:id AND eliminado_en IS NOT NULL');
        return $stmt->execute([':id' => $id]);
    }

    /** Borrado físico permanente (solo desde papelera) */
    public function eliminarDefinitivamente(int $id): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('DELETE FROM aspirantes WHERE id_aspirante=:id AND eliminado_en IS NOT NULL');
        return $stmt->execute([':id' => $id]);
    }

    /** Lista aspirantes en papelera */
    public function listarEliminados(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            'SELECT a.*, c.nombre AS carrera FROM aspirantes a
             LEFT JOIN carreras c ON a.id_carrera=c.id_carrera
             WHERE a.eliminado_en IS NOT NULL
             ORDER BY a.eliminado_en DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Estadísticas ─────────────────────────────────────────
    public function contarPorEtapa(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query('SELECT etapa, COUNT(*) AS total FROM aspirantes WHERE eliminado_en IS NULL GROUP BY etapa');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $r    = ['Contacto'=>0,'Interesado'=>0,'Inscrito'=>0,'No Interesado'=>0,'total'=>0];
        foreach ($rows as $row) { $r[$row['etapa']] = (int)$row['total']; $r['total'] += (int)$row['total']; }
        return $r;
    }

    public function contarNuevosEstaSemana(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query('SELECT etapa, COUNT(*) AS total FROM aspirantes WHERE eliminado_en IS NULL AND creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY etapa');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $r    = ['Contacto'=>0,'Interesado'=>0,'Inscrito'=>0,'No Interesado'=>0];
        foreach ($rows as $row) $r[$row['etapa']] = (int)$row['total'];
        return $r;
    }

    /** Busca id_carrera por nombre exacto (para importación CSV) */
    public function buscarCarreraPorNombre(string $nombre): ?int {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('SELECT id_carrera FROM carreras WHERE nombre = :n AND activa = 1 LIMIT 1');
        $stmt->execute([':n' => trim($nombre)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id_carrera'] : null;
    }
}

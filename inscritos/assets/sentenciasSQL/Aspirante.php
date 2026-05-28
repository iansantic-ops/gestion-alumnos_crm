<?php
// ============================================================
// Clase Aspirante — CRUD completo para la tabla aspirantes
// v4: clase reorganizada, propiedad al inicio, actualizarEtapa
//     en lugar correcto, contarFiltrados eliminado (dead code),
//     typo 'No interesado' corregido en ORDER BY FIELD()
// ============================================================
class Aspirante {

    // --------------------------------------------------------
    // Propiedades públicas
    // --------------------------------------------------------
    public int $totalAspirantes = 0;

    // --------------------------------------------------------
    // Etapas válidas
    // --------------------------------------------------------
    private const ETAPAS_VALIDAS = ['Contacto', 'Interesado', 'Inscrito', 'No Interesado'];

    // --------------------------------------------------------
    // CONEXIÓN INTERNA
    // --------------------------------------------------------
    private function conexion(): PDO {
        include __DIR__ . '/Conexion.php';
        return $pdo;
    }

    // --------------------------------------------------------
    // VALIDACIÓN
    // --------------------------------------------------------

    /**
     * Sanitiza y valida los datos de un aspirante.
     * Retorna un array de errores; si está vacío, los datos son válidos.
     */
    public function validar(array $datos, int $excluirId = 0): array {
        $errores = [];

        $nombre = trim(strip_tags($datos['nombre'] ?? ''));
        if (empty($nombre))                                               $errores[] = 'El nombre es obligatorio.';
        elseif (strlen($nombre) < 2)                                      $errores[] = 'El nombre debe tener al menos 2 caracteres.';
        elseif (strlen($nombre) > 150)                                    $errores[] = 'El nombre no puede superar 150 caracteres.';
        elseif (!preg_match('/^[\p{L}\s\.\-\']+$/u', $nombre))           $errores[] = 'El nombre contiene caracteres no permitidos.';

        $email = strtolower(trim($datos['email'] ?? ''));
        if (empty($email))                                                $errores[] = 'El email es obligatorio.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))               $errores[] = 'El formato del email no es válido.';
        elseif (strlen($email) > 200)                                     $errores[] = 'El email no puede superar 200 caracteres.';
        elseif ($this->emailExiste($email, $excluirId))                   $errores[] = 'Ya existe un aspirante registrado con ese correo electrónico.';

        $telefono = trim($datos['telefono'] ?? '');
        if (!empty($telefono) && !preg_match('/^[\d\s\-\(\)\+]{7,20}$/', $telefono))
            $errores[] = 'El teléfono debe tener entre 7 y 20 dígitos (se permiten espacios, guiones y paréntesis).';

        $etapa = $datos['etapa'] ?? 'Contacto';
        if (!in_array($etapa, self::ETAPAS_VALIDAS, true))
            $errores[] = 'La etapa seleccionada no es válida.';

        $descuento = (float)($datos['descuento'] ?? 0);
        if ($descuento < 0 || $descuento > 100)
            $errores[] = 'El descuento debe estar entre 0 y 100.';

        return $errores;
    }

    private function emailExiste(string $email, int $excluirId = 0): bool {
        $pdo  = $this->conexion();
        $sql  = 'SELECT id_aspirante FROM aspirantes WHERE email = :email';
        $params = [':email' => $email];
        if ($excluirId > 0) {
            $sql .= ' AND id_aspirante != :excluir';
            $params[':excluir'] = $excluirId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    // --------------------------------------------------------
    // SANITIZACIÓN INTERNA
    // --------------------------------------------------------
    private function sanitizar(array $datos): array {
        return [
            'nombre'     => htmlspecialchars(trim(strip_tags($datos['nombre'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'email'      => strtolower(filter_var(trim($datos['email'] ?? ''), FILTER_SANITIZE_EMAIL)),
            'telefono'   => preg_replace('/[^\d\s\-\(\)\+]/', '', trim($datos['telefono'] ?? '')),
            'id_carrera' => !empty($datos['id_carrera']) ? (int)$datos['id_carrera'] : null,
            'etapa'      => in_array($datos['etapa'] ?? '', self::ETAPAS_VALIDAS, true)
                                ? $datos['etapa'] : 'Contacto',
            'id_beca'    => !empty($datos['id_beca']) ? (int)$datos['id_beca'] : null,
            'descuento'  => max(0, min(100, (float)($datos['descuento'] ?? 0))),
            'notas'      => htmlspecialchars(trim(strip_tags($datos['notas'] ?? '')), ENT_QUOTES, 'UTF-8'),
        ];
    }

    // --------------------------------------------------------
    // CRUD
    // --------------------------------------------------------

    /** Crea un nuevo aspirante. Retorna el ID insertado o false. */
    public function crear(array $datos): int|false {
        $pdo = $this->conexion();
        $d   = $this->sanitizar($datos);

        $stmt = $pdo->prepare(
            'INSERT INTO aspirantes
                (nombre, email, telefono, id_carrera, etapa, id_beca, descuento_aplicado, notas)
             VALUES
                (:nombre, :email, :telefono, :id_carrera, :etapa, :id_beca, :descuento, :notas)'
        );
        $ok = $stmt->execute([
            ':nombre'     => $d['nombre'],
            ':email'      => $d['email'],
            ':telefono'   => $d['telefono']   ?: null,
            ':id_carrera' => $d['id_carrera'],
            ':etapa'      => $d['etapa'],
            ':id_beca'    => $d['id_beca'],
            ':descuento'  => $d['descuento'],
            ':notas'      => $d['notas']      ?: null,
        ]);
        return $ok ? (int)$pdo->lastInsertId() : false;
    }

    /** Obtiene un aspirante por ID. Retorna array o false. */
    public function obtenerPorId(int $id): array|false {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare(
            'SELECT a.*, c.nombre AS carrera, b.nombre AS beca
             FROM aspirantes a
             LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
             LEFT JOIN becas    b ON a.id_beca    = b.id_beca
             WHERE a.id_aspirante = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lista aspirantes con filtros opcionales y paginación.
     * Tras la llamada, $this->totalAspirantes contiene el total real (sin paginación).
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
        $pdo = $this->conexion();

        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($filtros['etapa']) && in_array($filtros['etapa'], self::ETAPAS_VALIDAS, true)) {
            $where .= ' AND a.etapa = :etapa';
            $params[':etapa'] = $filtros['etapa'];
        }
        if (!empty($filtros['id_carrera']) && is_numeric($filtros['id_carrera'])) {
            $where .= ' AND a.id_carrera = :id_carrera';
            $params[':id_carrera'] = (int)$filtros['id_carrera'];
        }
        if (!empty($filtros['busqueda'])) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($filtros['busqueda'])) . '%';
            $where .= ' AND (a.nombre LIKE :b OR a.email LIKE :b2)';
            $params[':b']  = $like;
            $params[':b2'] = $like;
        }

        // Total para paginación (consulta COUNT independiente — más segura que str_replace)
        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*) FROM aspirantes a $where"
        );
        $stmtCount->execute($params);
        $this->totalAspirantes = (int)$stmtCount->fetchColumn();

        // Orden y paginación
        $order  = "ORDER BY FIELD(a.etapa,'Contacto','Interesado','Inscrito','No Interesado') ASC, a.creado_en DESC";
        $offset = ($pagina - 1) * $porPagina;

        $sql  = "SELECT a.*, c.nombre AS carrera, b.nombre AS beca
                 FROM aspirantes a
                 LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
                 LEFT JOIN becas    b ON a.id_beca    = b.id_beca
                 $where $order
                 LIMIT :limite OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza datos de un aspirante (NO cambia la etapa — usar actualizarEtapa).
     * Retorna bool.
     */
    public function actualizar(int $id, array $datos): bool {
        $pdo = $this->conexion();
        $d   = $this->sanitizar($datos);

        $stmt = $pdo->prepare(
            'UPDATE aspirantes SET
                nombre             = :nombre,
                email              = :email,
                telefono           = :telefono,
                id_carrera         = :id_carrera,
                id_beca            = :id_beca,
                descuento_aplicado = :descuento,
                notas              = :notas
             WHERE id_aspirante = :id'
        );
        return $stmt->execute([
            ':nombre'     => $d['nombre'],
            ':email'      => $d['email'],
            ':telefono'   => $d['telefono']   ?: null,
            ':id_carrera' => $d['id_carrera'],
            ':id_beca'    => $d['id_beca'],
            ':descuento'  => $d['descuento'],
            ':notas'      => $d['notas']      ?: null,
            ':id'         => $id,
        ]);
    }

    /** Actualiza únicamente la etapa de un aspirante. Retorna bool. */
    public function actualizarEtapa(int $id, string $etapa): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('UPDATE aspirantes SET etapa = :etapa WHERE id_aspirante = :id');
        return $stmt->execute([':etapa' => $etapa, ':id' => $id]);
    }

    /** Elimina un aspirante por ID. Retorna bool. */
    public function eliminar(int $id): bool {
        $pdo  = $this->conexion();
        $stmt = $pdo->prepare('DELETE FROM aspirantes WHERE id_aspirante = :id');
        return $stmt->execute([':id' => $id]);
    }

    // --------------------------------------------------------
    // ESTADÍSTICAS
    // --------------------------------------------------------

    /** Cuenta aspirantes por etapa para los medidores del pipeline. */
    public function contarPorEtapa(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query('SELECT etapa, COUNT(*) AS total FROM aspirantes GROUP BY etapa');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = ['Contacto' => 0, 'Interesado' => 0, 'Inscrito' => 0, 'No Interesado' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $resultado[$row['etapa']] = (int)$row['total'];
            $resultado['total']      += (int)$row['total'];
        }
        return $resultado;
    }

    /** Cuenta aspirantes nuevos en la última semana por etapa. */
    public function contarNuevosEstaSemana(): array {
        $pdo  = $this->conexion();
        $stmt = $pdo->query(
            'SELECT etapa, COUNT(*) AS total FROM aspirantes
             WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY etapa'
        );
        $rows     = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = ['Contacto' => 0, 'Interesado' => 0, 'Inscrito' => 0, 'No Interesado' => 0];
        foreach ($rows as $row) {
            $resultado[$row['etapa']] = (int)$row['total'];
        }
        return $resultado;
    }
}

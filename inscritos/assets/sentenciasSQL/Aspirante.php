<?php
// ============================================================
// Clase Aspirante — CRUD completo para la tabla aspirantes
// Archivo: assets/sentenciasSQL/Aspirante.php
// MEJORAS: método validar() con sanitización y control de
//          duplicados; sanitización en crear() y actualizar()
// ============================================================
class Aspirante {

    // --------------------------------------------------------
    // Etapas válidas
    // --------------------------------------------------------
    private const ETAPAS_VALIDAS = ['Contacto', 'Interesado', 'Inscrito', 'No Interesado'];

    // --------------------------------------------------------
    // VALIDACIÓN Y SANITIZACIÓN
    // --------------------------------------------------------

    /**
     * Sanitiza y valida los datos de un aspirante.
     * Retorna un array de errores; si está vacío, los datos son válidos.
     *
     * @param array $datos       Datos crudos del formulario/API
     * @param int   $excluirId   ID a excluir en la revisión de duplicados (para UPDATE)
     * @return array             Lista de mensajes de error (vacío = sin errores)
     */
    /** Actualiza únicamente la etapa de un aspirante. Retorna bool. */
public function actualizarEtapa(int $id, string $etapa): bool {
    include __DIR__ . "/Conexion.php";
    $stmt = $pdo->prepare(
        "UPDATE aspirantes SET etapa = :etapa WHERE id_aspirante = :id"
    );
    return $stmt->execute([':etapa' => $etapa, ':id' => $id]);
}
    public function validar(array $datos, int $excluirId = 0): array {
        $errores = [];

        // ── Nombre ─────────────────────────────────────────
        $nombre = trim(strip_tags($datos['nombre'] ?? ''));
        if (empty($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($nombre) < 2) {
            $errores[] = 'El nombre debe tener al menos 2 caracteres.';
        } elseif (strlen($nombre) > 150) {
            $errores[] = 'El nombre no puede superar 150 caracteres.';
        } elseif (!preg_match('/^[\p{L}\s\.\-\']+$/u', $nombre)) {
            // Solo letras (incluye UTF-8 acentuadas), espacios, punto, guion, apóstrofe
            $errores[] = 'El nombre contiene caracteres no permitidos.';
        }

        // ── Email ───────────────────────────────────────────
        $email = strtolower(trim($datos['email'] ?? ''));
        if (empty($email)) {
            $errores[] = 'El email es obligatorio.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El formato del email no es válido.';
        } elseif (strlen($email) > 200) {
            $errores[] = 'El email no puede superar 200 caracteres.';
        } else {
            // Control de duplicados
            $duplicado = $this->emailExiste($email, $excluirId);
            if ($duplicado) {
                $errores[] = 'Ya existe un aspirante registrado con ese correo electrónico.';
            }
        }

        // ── Teléfono (opcional) ─────────────────────────────
        $telefono = trim($datos['telefono'] ?? '');
        if (!empty($telefono)) {
            // Permite dígitos, espacios, guiones, paréntesis y símbolo +
            if (!preg_match('/^[\d\s\-\(\)\+]{7,20}$/', $telefono)) {
                $errores[] = 'El teléfono debe tener entre 7 y 20 dígitos (se permiten espacios, guiones y paréntesis).';
            }
        }

        // ── Etapa ───────────────────────────────────────────
        $etapa = $datos['etapa'] ?? 'Contacto';
        if (!in_array($etapa, self::ETAPAS_VALIDAS, true)) {
            $errores[] = 'La etapa seleccionada no es válida.';
        }

        // ── Descuento (0-100) ───────────────────────────────
        $descuento = (float)($datos['descuento'] ?? 0);
        if ($descuento < 0 || $descuento > 100) {
            $errores[] = 'El descuento debe estar entre 0 y 100.';
        }

        return $errores;
    }

    /**
     * Comprueba si ya existe un aspirante con el email dado.
     *
     * @param string $email
     * @param int    $excluirId  Excluir este ID (para UPDATE no marcar al mismo como duplicado)
     * @return bool
     */
    private function emailExiste(string $email, int $excluirId = 0): bool {
        include __DIR__ . "/Conexion.php";
        $sql = "SELECT id_aspirante FROM aspirantes WHERE email = :email";
        $params = [':email' => $email];
        if ($excluirId > 0) {
            $sql .= " AND id_aspirante != :excluir";
            $params[':excluir'] = $excluirId;
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    // --------------------------------------------------------
    // SANITIZACIÓN INTERNA
    // --------------------------------------------------------

    /**
     * Sanitiza un array de datos de aspirante antes de INSERT/UPDATE.
     * Aplica trim, strip_tags y normalización de tipos.
     */
    private function sanitizar(array $datos): array {
        return [
            'nombre'     => htmlspecialchars(trim(strip_tags($datos['nombre'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'email'      => strtolower(filter_var(trim($datos['email'] ?? ''), FILTER_SANITIZE_EMAIL)),
            'telefono'   => preg_replace('/[^\d\s\-\(\)\+]/', '', trim($datos['telefono'] ?? '')),
            'id_carrera' => !empty($datos['id_carrera']) ? (int)$datos['id_carrera'] : null,
            'etapa'      => in_array($datos['etapa'] ?? '', self::ETAPAS_VALIDAS, true)
                                ? $datos['etapa']
                                : 'Contacto',
            'id_beca'    => !empty($datos['id_beca']) ? (int)$datos['id_beca'] : null,
            'descuento'  => max(0, min(100, (float)($datos['descuento'] ?? 0))),
            'notas'      => htmlspecialchars(trim(strip_tags($datos['notas'] ?? '')), ENT_QUOTES, 'UTF-8'),
        ];
    }

    // --------------------------------------------------------
    // CRUD
    // --------------------------------------------------------

    /**
     * Crea un nuevo aspirante. Retorna el ID insertado o false.
     * NOTA: llamar a validar() antes de crear().
     */
    public function crear(array $datos): int|false {
        include __DIR__ . "/Conexion.php";
        $d = $this->sanitizar($datos);

        $stmt = $pdo->prepare(
            "INSERT INTO aspirantes
                (nombre, email, telefono, id_carrera, etapa, id_beca, descuento_aplicado, notas)
             VALUES
                (:nombre, :email, :telefono, :id_carrera, :etapa, :id_beca, :descuento, :notas)"
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
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare(
            "SELECT a.*, c.nombre AS carrera, b.nombre AS beca
             FROM aspirantes a
             LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
             LEFT JOIN becas    b ON a.id_beca    = b.id_beca
             WHERE a.id_aspirante = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todos los aspirantes con filtros opcionales.
     * Los parámetros de búsqueda son sanitizados antes de la consulta.
     */
   public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array {
    include __DIR__ . "/Conexion.php";

    $sql    = "SELECT a.*, c.nombre AS carrera, b.nombre AS beca
               FROM aspirantes a
               LEFT JOIN carreras c ON a.id_carrera = c.id_carrera
               LEFT JOIN becas    b ON a.id_beca    = b.id_beca
               WHERE 1=1";
    $params = [];

    if (!empty($filtros['etapa']) && in_array($filtros['etapa'], self::ETAPAS_VALIDAS, true)) {
        $sql .= " AND a.etapa = :etapa";
        $params[':etapa'] = $filtros['etapa'];
    }
    if (!empty($filtros['id_carrera']) && is_numeric($filtros['id_carrera'])) {
        $sql .= " AND a.id_carrera = :id_carrera";
        $params[':id_carrera'] = (int)$filtros['id_carrera'];
    }
    if (!empty($filtros['busqueda'])) {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($filtros['busqueda'])) . '%';
        $sql .= " AND (a.nombre LIKE :b OR a.email LIKE :b2)";
        $params[':b']  = $like;
        $params[':b2'] = $like;
    }

    $sql .= " ORDER BY FIELD(a.etapa,'Contacto','Interesado','Inscrito','No Interesado','No interesado') ASC, a.creado_en DESC";

    // Total para paginación
    $stmtCount = $pdo->prepare(str_replace("SELECT a.*, c.nombre AS carrera, b.nombre AS beca", "SELECT COUNT(*) AS total", $sql));
    $stmtCount->execute($params);
    $this->totalAspirantes = (int)$stmtCount->fetchColumn();

    $offset = ($pagina - 1) * $porPagina;
    $sql   .= " LIMIT :limite OFFSET :offset";
    $stmt   = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public int $totalAspirantes = 0;

public function contarFiltrados(array $filtros = []): int {
    include __DIR__ . "/Conexion.php";
    $sql    = "SELECT COUNT(*) FROM aspirantes a WHERE 1=1";
    $params = [];
    if (!empty($filtros['etapa']) && in_array($filtros['etapa'], self::ETAPAS_VALIDAS, true)) {
        $sql .= " AND a.etapa = :etapa";
        $params[':etapa'] = $filtros['etapa'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
    /**
     * Actualiza un aspirante. Retorna bool.
     * NOTA: llamar a validar($datos, $id) antes de actualizar().
     */
   public function actualizar(int $id, array $datos): bool {
    include __DIR__ . "/Conexion.php";
    $d = $this->sanitizar($datos);

    // Conserva la etapa actual de la BD — la etapa solo se cambia
    // desde el menú flotante (actualizarEtapa), nunca desde el formulario de edición
    $stmt = $pdo->prepare(
        "UPDATE aspirantes SET
            nombre             = :nombre,
            email              = :email,
            telefono           = :telefono,
            id_carrera         = :id_carrera,
            id_beca            = :id_beca,
            descuento_aplicado = :descuento,
            notas              = :notas
         WHERE id_aspirante = :id"
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

    /** Elimina un aspirante por ID. Retorna bool. */
    public function eliminar(int $id): bool {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->prepare("DELETE FROM aspirantes WHERE id_aspirante = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Cuenta aspirantes por etapa para los medidores del pipeline. */
    public function contarPorEtapa(): array {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->query("SELECT etapa, COUNT(*) AS total FROM aspirantes GROUP BY etapa");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = ['Contacto' => 0, 'Interesado' => 0, 'Inscrito' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $resultado[$row['etapa']] = (int)$row['total'];
            $resultado['total']      += (int)$row['total'];
        }
        return $resultado;
    }

    /** Cuenta aspirantes nuevos en la última semana por etapa. */
    public function contarNuevosEstaSemana(): array {
        include __DIR__ . "/Conexion.php";
        $stmt = $pdo->query(
            "SELECT etapa, COUNT(*) AS total FROM aspirantes
             WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY etapa"
        );
        $rows     = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $resultado = ['Contacto' => 0, 'Interesado' => 0, 'Inscrito' => 0];
        foreach ($rows as $row) {
            $resultado[$row['etapa']] = (int)$row['total'];
        }
        return $resultado;
    }
}

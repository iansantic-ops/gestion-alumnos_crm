<?php
// ============================================================
// CRM Universitario — Página Principal con navegación funcional
// ============================================================
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . "/assets/sentenciasSQL/Aspirante.php";
require_once __DIR__ . "/assets/sentenciasSQL/Historial.php";
require_once __DIR__ . "/assets/sentenciasSQL/Agenda.php";
require_once __DIR__ . "/assets/sentenciasSQL/Carrera.php";
require_once __DIR__ . "/assets/sentenciasSQL/Beca.php";
require_once __DIR__ . "/assets/sentenciasSQL/admin.php";

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$aspiranteModel = new Aspirante();
$historialModel = new Historial();
$agendaModel    = new Agenda();
$carreraModel   = new Carrera();
$becaModel      = new Beca();

$pipeline    = $aspiranteModel->contarPorEtapa();
$semana      = $aspiranteModel->contarNuevosEstaSemana();
$aspirantes  = $aspiranteModel->listar();
$historial   = $historialModel->obtenerRecientes(8);
$proximosTmp = $agendaModel->obtenerProximos(6);
$carreras    = $carreraModel->listarActivas();
$todasCarreras = $carreraModel->listarTodas();
$becas       = $becaModel->listarActivas();
$todasBecas  = $becaModel->listarTodas();

$usuario_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'];

function iconoTipo(string $tipo): string {
    return match($tipo) {
        'llamada' => '📞','correo' => '✉️','visita' => '🏢',default => '📝',
    };
}
function iconoAgenda(string $tipo): string {
    return match($tipo) {
        'llamada' => '📞','correo' => '✉️','reunion' => '🤝',default => '✅',
    };
}
function colorEtapa(string $etapa): string {
    return match($etapa) {
        'Inscrito' => '#28a745','Interesado' => '#e07000',default => '#0077cc',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Universitario — Panel</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap');
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 220px;
            --blue-dark: #0f2d5e;
            --blue:      #1a4a8a;
            --blue-light:#eef3fb;
            --orange:    #f08c00;
            --green:     #28a745;
            --gray-bg:   #f3f5fb;
            --white:     #ffffff;
            --text:      #2d3748;
            --text-light:#718096;
            --border:    #e2e8f0;
            --radius:    12px;
            --shadow:    0 2px 12px rgba(0,0,0,0.08);
        }

        body { font-family: 'Nunito', Arial, sans-serif; background: var(--gray-bg); color: var(--text); display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: var(--sidebar-w); background: linear-gradient(180deg, var(--blue-dark) 0%, var(--blue) 100%); position: fixed; top:0; left:0; bottom:0; display:flex; flex-direction:column; z-index:100; box-shadow:4px 0 16px rgba(0,0,0,0.18); }
        .sidebar-brand { padding:22px 20px 18px; border-bottom:1px solid rgba(255,255,255,0.12); display:flex; align-items:center; gap:10px; }
        .sidebar-brand .brand-icon { width:38px; height:38px; background:rgba(255,255,255,0.18); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .sidebar-brand .brand-text h2 { font-size:14px; font-weight:800; color:white; line-height:1.2; }
        .sidebar-brand .brand-text span { font-size:11px; color:rgba(255,255,255,0.55); font-weight:600; }
        .sidebar-user { padding:14px 20px; display:flex; align-items:center; gap:10px; border-bottom:1px solid rgba(255,255,255,0.10); cursor:pointer; transition:background 0.2s; }
        .sidebar-user:hover { background:rgba(255,255,255,0.08); }
        .sidebar-user .avatar { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .sidebar-user .uname { font-size:13px; font-weight:700; color:white; }
        .sidebar-user .urole { font-size:11px; color:rgba(255,255,255,0.5); }
        .sidebar-menu { padding:14px 12px; flex:1; overflow-y:auto; }
        .sidebar-menu .menu-label { font-size:10px; font-weight:800; color:rgba(255,255,255,0.35); text-transform:uppercase; letter-spacing:1px; padding:0 8px; margin:12px 0 6px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:rgba(255,255,255,0.72); font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; margin-bottom:2px; text-decoration:none; user-select:none; }
        .nav-item:hover { background:rgba(255,255,255,0.12); color:white; }
        .nav-item.active { background:rgba(255,255,255,0.18); color:white; font-weight:700; }
        .nav-item .ni-icon { font-size:17px; width:22px; text-align:center; }
        .sidebar-bottom { padding:14px 12px; border-top:1px solid rgba(255,255,255,0.10); }
        .btn-logout { width:100%; padding:10px; background:rgba(255,80,80,0.2); color:#ff9898; border:1px solid rgba(255,80,80,0.3); border-radius:10px; font-size:13px; font-weight:700; font-family:inherit; cursor:pointer; transition:all 0.2s; }
        .btn-logout:hover { background:rgba(255,80,80,0.35); color:white; }

        /* MAIN */
        .main-content { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .topbar { background:white; padding:14px 28px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
        .topbar h1 { font-size:18px; font-weight:800; color:var(--blue-dark); }
        .topbar .topbar-sub { font-size:12px; color:var(--text-light); margin-top:2px; }
        .topbar-actions { display:flex; align-items:center; gap:10px; }
        .topbar-badge { background:var(--gray-bg); padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700; color:var(--text-light); }

        /* SECTIONS */
        .section { display:none; padding:24px 28px; flex:1; }
        .section.active { display:block; }

        /* CARDS */
        .card { background:white; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .card-header { padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--border); }
        .card-header h3 { font-size:15px; font-weight:800; color:var(--blue-dark); }
        .card-body { padding:16px 20px; }

        /* BUTTONS */
        .btn-sm { padding:7px 14px; border-radius:8px; font-size:13px; font-weight:700; font-family:inherit; cursor:pointer; border:none; transition:all 0.2s; }
        .btn-primary { background:var(--blue); color:white; }
        .btn-primary:hover { background:var(--blue-dark); }
        .btn-danger { background:#fff0f0; color:#c0392b; border:1px solid #ffcccc; }
        .btn-danger:hover { background:#c0392b; color:white; }
        .btn-success { background:#e8f5e9; color:#1e7e3a; border:1px solid #c3e6cb; }
        .btn-success:hover { background:#1e7e3a; color:white; }
        .btn-warning { background:#fff8e1; color:#e07b00; border:1px solid #ffe082; }
        .btn-warning:hover { background:#e07b00; color:white; }
        .btn-guardar { width:100%; padding:12px; background:linear-gradient(135deg, var(--blue), var(--blue-dark)); color:white; border:none; border-radius:10px; font-size:14px; font-weight:700; font-family:inherit; cursor:pointer; transition:all 0.2s; box-shadow:0 3px 10px rgba(15,45,94,0.25); }
        .btn-guardar:hover { transform:translateY(-1px); box-shadow:0 5px 15px rgba(15,45,94,0.35); }

        /* TABLES */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { padding:10px 14px; text-align:left; background:var(--gray-bg); color:var(--text-light); font-weight:700; font-size:12px; border-bottom:1px solid var(--border); white-space:nowrap; }
        td { padding:11px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafbff; }
        .empty-table { text-align:center; padding:30px; color:var(--text-light); }

        /* BADGES */
        .etapa-badge { display:inline-block; padding:4px 11px; border-radius:20px; font-size:12px; font-weight:700; color:white; }
        .badge-activa { background:#e8f5e9; color:#1e7e3a; border:1px solid #c3e6cb; font-size:12px; padding:3px 10px; border-radius:20px; font-weight:700; }
        .badge-inactiva { background:#f5f5f5; color:#9e9e9e; border:1px solid #e0e0e0; font-size:12px; padding:3px 10px; border-radius:20px; font-weight:700; }

        /* ACTION BUTTONS */
        .action-btns { display:flex; gap:6px; }
        .action-btns button { border:none; border-radius:6px; padding:5px 10px; font-size:12px; cursor:pointer; font-family:inherit; font-weight:700; }
        .btn-edit { background:#e8f0fe; color:#1a4a8a; }
        .btn-edit:hover { background:#1a4a8a; color:white; }
        .btn-del  { background:#fff0f0; color:#c0392b; }
        .btn-del:hover  { background:#c0392b; color:white; }

        /* FORM ELEMENTS */
        .form-group { margin-bottom:13px; }
        .form-group label { display:block; font-size:12px; font-weight:700; color:var(--text-light); margin-bottom:5px; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; color:var(--text); outline:none; transition:border-color 0.2s; background:#fafbff; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--blue); background:white; }
        .form-group textarea { resize:vertical; min-height:60px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }

        /* FILTERS */
        .filters-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; padding:12px 16px; background:var(--gray-bg); border-bottom:1px solid var(--border); }
        .filters-row select, .filters-row input { padding:7px 12px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; background:white; color:var(--text); outline:none; }
        .filters-row input { flex:1; min-width:160px; }

        /* DASHBOARD GRID */
        .pipeline-row { display:grid; grid-template-columns:repeat(3, 1fr) auto; gap:14px; margin-bottom:22px; align-items:center; }
        .metric-card { border-radius:var(--radius); padding:16px 20px; color:white; display:flex; flex-direction:column; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s; box-shadow:var(--shadow); }
        .metric-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,0.15); }
        .metric-card.contacto  { background:linear-gradient(135deg, #f5a623, #e07b00); }
        .metric-card.interesado{ background:linear-gradient(135deg, #e07000, #b85a00); }
        .metric-card.inscrito  { background:linear-gradient(135deg, #2ca853, #1e7e3a); }
        .metric-num { font-size:36px; font-weight:800; line-height:1; display:inline-block; margin-right:8px; }
        .metric-label { font-size:14px; font-weight:700; opacity:0.95; }
        .metric-delta { font-size:12px; opacity:0.80; margin-top:4px; }
        .total-box { background:white; border-radius:var(--radius); padding:16px 22px; text-align:right; box-shadow:var(--shadow); }
        .total-box .total-label { font-size:13px; font-weight:700; color:var(--text-light); }
        .total-box .total-num   { font-size:40px; font-weight:800; color:var(--blue-dark); line-height:1.1; }
        .dashboard-grid { display:grid; grid-template-columns:1fr 310px; gap:18px; }
        .bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:18px; }
        .scroll-area { max-height:260px; overflow-y:auto; }

        /* HISTORIAL ITEMS */
        .hist-item { display:flex; gap:10px; align-items:flex-start; padding:10px 0; border-bottom:1px solid var(--border); }
        .hist-item:last-child { border-bottom:none; }
        .hist-icon { width:34px; height:34px; border-radius:50%; background:var(--gray-bg); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
        .hist-body { flex:1; min-width:0; }
        .hist-name { font-size:13px; font-weight:700; color:var(--text); }
        .hist-desc { font-size:12px; color:var(--text-light); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .hist-date { font-size:11px; color:var(--text-light); white-space:nowrap; }

        /* AGENDA ITEMS */
        .agenda-item { display:flex; gap:10px; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); }
        .agenda-item:last-child { border-bottom:none; }
        .agenda-dot { width:10px; height:10px; border-radius:50%; background:var(--orange); flex-shrink:0; }
        .agenda-dot.green { background:var(--green); }
        .agenda-dot.blue  { background:var(--blue); }
        .agenda-body { flex:1; min-width:0; }
        .agenda-title { font-size:13px; font-weight:700; }
        .agenda-sub   { font-size:12px; color:var(--text-light); }

        /* REPORTES */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:14px; margin-bottom:22px; }
        .stat-box { background:white; border-radius:var(--radius); padding:18px 20px; box-shadow:var(--shadow); text-align:center; }
        .stat-box .stat-num { font-size:38px; font-weight:800; color:var(--blue-dark); line-height:1; }
        .stat-box .stat-label { font-size:13px; color:var(--text-light); margin-top:6px; font-weight:600; }
        .reportes-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .bar-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--border); }
        .bar-item:last-child { border-bottom:none; }
        .bar-label { font-size:13px; font-weight:600; min-width:130px; }
        .bar-track { flex:1; background:#f0f0f0; border-radius:6px; height:10px; overflow:hidden; }
        .bar-fill { height:100%; border-radius:6px; background:var(--blue); transition:width 0.6s; }
        .bar-count { font-size:13px; font-weight:700; min-width:28px; text-align:right; }

        /* CONFIGURACION */
        .config-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }

        /* TOAST */
        #toast { position:fixed; bottom:24px; right:24px; background:#1e7e3a; color:white; padding:12px 22px; border-radius:10px; font-size:14px; font-weight:700; box-shadow:0 4px 20px rgba(0,0,0,0.2); opacity:0; pointer-events:none; transition:opacity 0.3s; z-index:999; }
        #toast.show { opacity:1; }
        #toast.error { background:#c0392b; }

        /* MODALS */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:white; border-radius:16px; padding:28px; width:440px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,0.25); animation:popIn 0.25s ease; }
        @keyframes popIn { from{opacity:0;transform:scale(0.9)} to{opacity:1;transform:scale(1)} }
        .modal h3 { font-size:17px; font-weight:800; margin-bottom:18px; color:var(--blue-dark); }

        /* LOADING */
        .loading-spinner { text-align:center; padding:40px; color:var(--text-light); font-size:14px; }

        .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
        .section-header h2 { font-size:20px; font-weight:800; color:var(--blue-dark); }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-text">
            <h2>CRM Universitario</h2>
            <span>Panel Administrativo</span>
        </div>
    </div>

    <div class="sidebar-user" onclick="mostrarSeccion('configuracion')" title="Configurar cuenta">
        <div class="avatar">👤</div>
        <div>
            <div class="uname"><?= htmlspecialchars($usuario_nombre) ?></div>
            <div class="urole" style="display:flex;align-items:center;gap:4px;">
                Administrador <span style="font-size:9px;opacity:0.55;">⚙️</span>
            </div>
        </div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Principal</div>
        <a class="nav-item active" onclick="mostrarSeccion('dashboard')" data-seccion="dashboard">
            <span class="ni-icon">🏠</span> Panel Principal
        </a>
        <a class="nav-item" onclick="mostrarSeccion('aspirantes')" data-seccion="aspirantes">
            <span class="ni-icon">👥</span> Aspirantes
        </a>
        <a class="nav-item" onclick="mostrarSeccion('historial')" data-seccion="historial">
            <span class="ni-icon">📋</span> Historial
        </a>
        <a class="nav-item" onclick="mostrarSeccion('agenda')" data-seccion="agenda">
            <span class="ni-icon">📅</span> Agenda
        </a>

        <div class="menu-label">Catálogos</div>
        <a class="nav-item" onclick="mostrarSeccion('carreras')" data-seccion="carreras">
            <span class="ni-icon">🎓</span> Carreras
        </a>
        <a class="nav-item" onclick="mostrarSeccion('becas')" data-seccion="becas">
            <span class="ni-icon">💰</span> Becas
        </a>

        <div class="menu-label">Sistema</div>
        <a class="nav-item" onclick="mostrarSeccion('reportes')" data-seccion="reportes">
            <span class="ni-icon">📊</span> Reportes
        </a>
        <a class="nav-item" onclick="mostrarSeccion('configuracion')" data-seccion="configuracion">
            <span class="ni-icon">⚙️</span> Configuración
        </a>
    </nav>

    <div class="sidebar-bottom">
        <form method="post">
            <button type="submit" name="logout" class="btn-logout">🚪 Cerrar sesión</button>
        </form>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">

    <div class="topbar">
        <div>
            <h1 id="topbar-title">Panel Principal</h1>
            <div class="topbar-sub" id="topbar-sub">Bienvenido, <?= htmlspecialchars($usuario_nombre) ?></div>
        </div>
        <div class="topbar-actions">
            <span class="topbar-badge">📅 <?= date('d M Y') ?></span>
        </div>
    </div>

    <!-- ============ SECCIÓN: DASHBOARD ============ -->
    <div id="sec-dashboard" class="section active">

        <div class="pipeline-row">
            <div class="metric-card contacto" onclick="irAspirantes('Contacto')">
                <div><span class="metric-num"><?= $pipeline['Contacto'] ?></span><span class="metric-label">En Contacto</span></div>
                <div class="metric-delta">+<?= $semana['Contacto'] ?> esta semana</div>
            </div>
            <div class="metric-card interesado" onclick="irAspirantes('Interesado')">
                <div><span class="metric-num"><?= $pipeline['Interesado'] ?></span><span class="metric-label">Interesados</span></div>
                <div class="metric-delta">+<?= $semana['Interesado'] ?> esta semana</div>
            </div>
            <div class="metric-card inscrito" onclick="irAspirantes('Inscrito')">
                <div><span class="metric-num"><?= $pipeline['Inscrito'] ?></span><span class="metric-label">Inscritos</span></div>
                <div class="metric-delta">+<?= $semana['Inscrito'] ?> esta semana</div>
            </div>
            <div class="total-box">
                <div class="total-label">Aspirantes Totales</div>
                <div class="total-num"><?= $pipeline['total'] ?></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Tabla aspirantes -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Lista de Aspirantes</h3>
                    <button class="btn-sm btn-primary" onclick="mostrarSeccion('aspirantes'); abrirModalAspirante()">+ Agregar</button>
                </div>
                <div class="filters-row">
                    <select id="dash-filtro-etapa" onchange="filtrarTablaD()">
                        <option value="">Todas las etapas</option>
                        <option value="Contacto">Contacto</option>
                        <option value="Interesado">Interesado</option>
                        <option value="Inscrito">Inscrito</option>
                    </select>
                    <select id="dash-filtro-carrera" onchange="filtrarTablaD()">
                        <option value="">Todas las carreras</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="dash-busqueda" placeholder="🔍 Buscar..." oninput="filtrarTablaD()">
                </div>
                <div class="table-wrap">
                    <table id="dash-tabla-asp">
                        <thead><tr><th>Nombre</th><th>Email</th><th>Carrera</th><th>Etapa</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php if (empty($aspirantes)): ?>
                            <tr><td colspan="5" class="empty-table">No hay aspirantes registrados.</td></tr>
                        <?php else: foreach ($aspirantes as $a): ?>
                            <tr data-etapa="<?= $a['etapa'] ?>" data-carrera="<?= $a['id_carrera'] ?>" data-nombre="<?= strtolower($a['nombre']) ?>" data-email="<?= strtolower($a['email']) ?>">
                                <td><strong><?= htmlspecialchars($a['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($a['email']) ?></td>
                                <td><?= htmlspecialchars($a['carrera'] ?? '—') ?></td>
                                <td><span class="etapa-badge" style="background:<?= colorEtapa($a['etapa']) ?>"><?= $a['etapa'] ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-edit" onclick="editarAspirante(<?= $a['id_aspirante'] ?>)">✏️</button>
                                        <button class="btn-del" onclick="confirmarEliminar(<?= $a['id_aspirante'] ?>, '<?= addslashes($a['nombre']) ?>', 'aspirante')">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Formulario rápido -->
            <div class="card">
                <div class="card-header"><h3>📝 Registro Rápido</h3></div>
                <div class="card-body">
                    <div class="form-group"><label>Nombre completo</label><input type="text" id="dash-nombre" placeholder="Nombre del aspirante"></div>
                    <div class="form-group"><label>Email</label><input type="email" id="dash-email" placeholder="correo@ejemplo.com"></div>
                    <div class="form-group"><label>Teléfono</label><input type="text" id="dash-telefono" placeholder="555 000 0000"></div>
                    <div class="form-group"><label>Carrera</label>
                        <select id="dash-carrera">
                            <option value="">— Seleccione —</option>
                            <?php foreach ($carreras as $c): ?>
                                <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Etapa</label>
                        <select id="dash-etapa"><option value="Contacto">Contacto</option><option value="Interesado">Interesado</option><option value="Inscrito">Inscrito</option></select>
                    </div>
                    <button type="button" onclick="guardarAspRapido()" class="btn-guardar">💾 Guardar Aspirante</button>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="card">
                <div class="card-header"><h3>🗂️ Historial Reciente</h3><button class="btn-sm btn-primary" onclick="abrirModal('modal-historial')">+ Agregar</button></div>
                <div class="card-body scroll-area">
                    <?php if (empty($historial)): ?>
                        <p style="color:var(--text-light);text-align:center;padding:20px;">Sin interacciones recientes.</p>
                    <?php else: foreach ($historial as $h): ?>
                        <div class="hist-item">
                            <div class="hist-icon"><?= iconoTipo($h['tipo']) ?></div>
                            <div class="hist-body">
                                <div class="hist-name"><?= htmlspecialchars($h['aspirante_nombre']) ?></div>
                                <div class="hist-desc"><?= htmlspecialchars($h['descripcion']) ?></div>
                            </div>
                            <div class="hist-date"><?= date('d M', strtotime($h['fecha'])) ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>📅 Agenda Próxima</h3><button class="btn-sm btn-primary" onclick="abrirModalAgenda()">+ Nuevo</button></div>
                <div class="card-body scroll-area">
                    <?php if (empty($proximosTmp)): ?>
                        <p style="color:var(--text-light);text-align:center;padding:20px;">Sin eventos próximos.</p>
                    <?php else: foreach ($proximosTmp as $ev): ?>
                        <div class="agenda-item">
                            <div class="agenda-dot <?= $ev['tipo']==='correo'?'blue':($ev['tipo']==='tarea'?'green':'') ?>"></div>
                            <div class="agenda-body">
                                <div class="agenda-title"><?= htmlspecialchars($ev['titulo']) ?></div>
                                <div class="agenda-sub"><?= $ev['aspirante_nombre'] ? htmlspecialchars($ev['aspirante_nombre']).' · ':'' ?><?= date('d M H:i', strtotime($ev['fecha_hora'])) ?></div>
                            </div>
                            <button class="btn-sm btn-success" onclick="completarAgenda(<?= $ev['id_agenda'] ?>, this)">✓</button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: ASPIRANTES ============ -->
    <div id="sec-aspirantes" class="section">
        <div class="section-header">
            <h2>👥 Gestión de Aspirantes</h2>
            <button class="btn-sm btn-primary" onclick="abrirModalAspirante()">+ Nuevo Aspirante</button>
        </div>
        <div class="card">
            <div class="filters-row">
                <select id="asp-filtro-etapa" onchange="filtrarTablaA()">
                    <option value="">Todas las etapas</option>
                    <option value="Contacto">Contacto</option>
                    <option value="Interesado">Interesado</option>
                    <option value="Inscrito">Inscrito</option>
                </select>
                <select id="asp-filtro-carrera" onchange="filtrarTablaA()">
                    <option value="">Todas las carreras</option>
                    <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="asp-busqueda" placeholder="🔍 Buscar nombre o email..." oninput="filtrarTablaA()">
            </div>
            <div class="table-wrap">
                <table id="asp-tabla">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Carrera</th><th>Beca</th><th>Etapa</th><th>Registrado</th><th>Acciones</th></tr></thead>
                    <tbody id="asp-tbody">
                    <?php if (empty($aspirantes)): ?>
                        <tr><td colspan="8" class="empty-table">No hay aspirantes registrados aún.</td></tr>
                    <?php else: foreach ($aspirantes as $a): ?>
                        <tr data-etapa="<?= $a['etapa'] ?>" data-carrera="<?= $a['id_carrera'] ?>" data-nombre="<?= strtolower($a['nombre']) ?>" data-email="<?= strtolower($a['email']) ?>">
                            <td><strong><?= htmlspecialchars($a['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td><?= htmlspecialchars($a['telefono'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['carrera'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['beca'] ?? '—') ?></td>
                            <td><span class="etapa-badge" style="background:<?= colorEtapa($a['etapa']) ?>"><?= $a['etapa'] ?></span></td>
                            <td style="font-size:12px;color:var(--text-light)"><?= date('d M Y', strtotime($a['creado_en'])) ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="editarAspirante(<?= $a['id_aspirante'] ?>)">✏️ Editar</button>
                                    <button class="btn-del" onclick="confirmarEliminar(<?= $a['id_aspirante'] ?>, '<?= addslashes($a['nombre']) ?>', 'aspirante')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: HISTORIAL ============ -->
    <div id="sec-historial" class="section">
        <div class="section-header">
            <h2>📋 Historial de Interacciones</h2>
            <button class="btn-sm btn-primary" onclick="abrirModal('modal-historial')">+ Nueva Interacción</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Aspirante</th><th>Tipo</th><th>Descripción</th><th>Fecha</th></tr></thead>
                    <tbody>
                    <?php
                    $todoHistorial = $historialModel->obtenerRecientes(100);
                    if (empty($todoHistorial)): ?>
                        <tr><td colspan="4" class="empty-table">Sin interacciones registradas.</td></tr>
                    <?php else: foreach ($todoHistorial as $h): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($h['aspirante_nombre']) ?></strong></td>
                            <td><?= iconoTipo($h['tipo']) ?> <?= ucfirst($h['tipo']) ?></td>
                            <td><?= htmlspecialchars($h['descripcion']) ?></td>
                            <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($h['fecha'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: AGENDA ============ -->
    <div id="sec-agenda" class="section">
        <div class="section-header">
            <h2>📅 Agenda y Recordatorios</h2>
            <button class="btn-sm btn-primary" onclick="abrirModalAgenda()">+ Nuevo Evento</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Título</th><th>Tipo</th><th>Aspirante</th><th>Fecha y Hora</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                    <?php
                    $todaAgenda = $agendaModel->obtenerProximos(50);
                    if (empty($todaAgenda)): ?>
                        <tr><td colspan="6" class="empty-table">Sin eventos registrados.</td></tr>
                    <?php else: foreach ($todaAgenda as $ev): ?>
                        <tr id="agenda-row-<?= $ev['id_agenda'] ?>">
                            <td><strong><?= htmlspecialchars($ev['titulo']) ?></strong></td>
                            <td><?= iconoAgenda($ev['tipo']) ?> <?= ucfirst($ev['tipo']) ?></td>
                            <td><?= htmlspecialchars($ev['aspirante_nombre'] ?? '—') ?></td>
                            <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($ev['fecha_hora'])) ?></td>
                            <td><span class="badge-activa">Pendiente</span></td>
                            <td><button class="btn-sm btn-success" onclick="completarAgenda(<?= $ev['id_agenda'] ?>, this)">✓ Completar</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: CARRERAS ============ -->
    <div id="sec-carreras" class="section">
        <div class="section-header">
            <h2>🎓 Catálogo de Carreras</h2>
            <button class="btn-sm btn-primary" onclick="abrirModalCarrera()">+ Nueva Carrera</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="carreras-tbody">
                    <?php if (empty($todasCarreras)): ?>
                        <tr><td colspan="4" class="empty-table">No hay carreras registradas.</td></tr>
                    <?php else: foreach ($todasCarreras as $c): ?>
                        <tr id="carrera-row-<?= $c['id_carrera'] ?>">
                            <td><?= $c['id_carrera'] ?></td>
                            <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                            <td><span class="<?= $c['activa'] ? 'badge-activa' : 'badge-inactiva' ?>"><?= $c['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="editarCarrera(<?= $c['id_carrera'] ?>, '<?= addslashes($c['nombre']) ?>', <?= $c['activa'] ?>)">✏️ Editar</button>
                                    <button class="btn-del" onclick="confirmarEliminar(<?= $c['id_carrera'] ?>, '<?= addslashes($c['nombre']) ?>', 'carrera')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: BECAS ============ -->
    <div id="sec-becas" class="section">
        <div class="section-header">
            <h2>💰 Catálogo de Becas</h2>
            <button class="btn-sm btn-primary" onclick="abrirModalBeca()">+ Nueva Beca</button>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>Nombre</th><th>Descuento %</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="becas-tbody">
                    <?php if (empty($todasBecas)): ?>
                        <tr><td colspan="5" class="empty-table">No hay becas registradas.</td></tr>
                    <?php else: foreach ($todasBecas as $b): ?>
                        <tr id="beca-row-<?= $b['id_beca'] ?>">
                            <td><?= $b['id_beca'] ?></td>
                            <td><strong><?= htmlspecialchars($b['nombre']) ?></strong></td>
                            <td><strong style="color:var(--blue-dark)"><?= number_format($b['descuento'], 0) ?>%</strong></td>
                            <td><span class="<?= $b['activa'] ? 'badge-activa' : 'badge-inactiva' ?>"><?= $b['activa'] ? 'Activa' : 'Inactiva' ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="editarBeca(<?= $b['id_beca'] ?>, '<?= addslashes($b['nombre']) ?>', <?= $b['descuento'] ?>, <?= $b['activa'] ?>)">✏️ Editar</button>
                                    <button class="btn-del" onclick="confirmarEliminar(<?= $b['id_beca'] ?>, '<?= addslashes($b['nombre']) ?>', 'beca')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ SECCIÓN: REPORTES ============ -->
    <div id="sec-reportes" class="section">
        <div class="section-header">
            <h2>📊 Reportes del Sistema</h2>
            <button class="btn-sm btn-primary" onclick="cargarReportes()">🔄 Actualizar</button>
        </div>
        <div id="reportes-contenido"><div class="loading-spinner">⏳ Cargando datos...</div></div>
    </div>

    <!-- ============ SECCIÓN: CONFIGURACIÓN ============ -->
    <div id="sec-configuracion" class="section">
        <div class="section-header">
            <h2>⚙️ Configuración del Sistema</h2>
        </div>
        <div class="config-grid">

            <!-- Cambio de credenciales -->
            <div class="card">
                <div class="card-header"><h3>🔐 Credenciales de Acceso</h3></div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:16px;">Modifica tu usuario y contraseña. Se requiere la contraseña actual para autorizar el cambio.</p>
                    <div id="cfg-alert" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:14px;"></div>
                    <div class="form-group"><label>Contraseña actual <span style="color:#c0392b">*</span></label><input type="password" id="cfg-pass-actual" placeholder="Tu contraseña actual"></div>
                    <div class="form-group"><label>Nuevo usuario</label><input type="text" id="cfg-usuario" value="<?= htmlspecialchars($_SESSION['usuario'] ?? '') ?>"></div>
                    <div class="form-group"><label>Nueva contraseña</label><input type="password" id="cfg-pass-nueva" placeholder="Mínimo 6 caracteres"></div>
                    <div class="form-group"><label>Confirmar nueva contraseña</label><input type="password" id="cfg-pass-confirmar" placeholder="Repite la nueva contraseña"></div>
                    <button class="btn-guardar" onclick="guardarCredenciales()">💾 Guardar Cambios</button>
                </div>
            </div>

            <!-- Info del sistema -->
            <div class="card">
                <div class="card-header"><h3>ℹ️ Información del Sistema</h3></div>
                <div class="card-body">
                    <table style="width:100%;font-size:13px;">
                        <tr><td style="padding:10px 0;border-bottom:1px solid var(--border);color:var(--text-light);font-weight:700;">Sistema</td><td style="padding:10px 0;border-bottom:1px solid var(--border);">CRM Universitario</td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid var(--border);color:var(--text-light);font-weight:700;">Usuario activo</td><td style="padding:10px 0;border-bottom:1px solid var(--border);"><?= htmlspecialchars($usuario_nombre) ?></td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid var(--border);color:var(--text-light);font-weight:700;">Fecha</td><td style="padding:10px 0;border-bottom:1px solid var(--border);"><?= date('d M Y H:i') ?></td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid var(--border);color:var(--text-light);font-weight:700;">Total aspirantes</td><td style="padding:10px 0;border-bottom:1px solid var(--border);"><?= $pipeline['total'] ?></td></tr>
                        <tr><td style="padding:10px 0;border-bottom:1px solid var(--border);color:var(--text-light);font-weight:700;">Carreras activas</td><td style="padding:10px 0;border-bottom:1px solid var(--border);"><?= count($carreras) ?></td></tr>
                        <tr><td style="padding:10px 0;color:var(--text-light);font-weight:700;">Becas activas</td><td style="padding:10px 0;"><?= count($becas) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

</main>

<!-- ============ MODALES ============ -->

<!-- Modal: Aspirante (crear/editar) -->
<div class="modal-overlay" id="modal-aspirante">
    <div class="modal" style="width:500px;">
        <h3 id="modal-asp-title">📝 Nuevo Aspirante</h3>
        <input type="hidden" id="asp-id">
        <div class="form-row">
            <div class="form-group"><label>Nombre completo *</label><input type="text" id="asp-nombre" placeholder="Nombre completo"></div>
            <div class="form-group"><label>Email *</label><input type="email" id="asp-email" placeholder="correo@ejemplo.com"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Teléfono</label><input type="text" id="asp-telefono" placeholder="555 000 0000"></div>
            <div class="form-group"><label>Etapa</label>
                <select id="asp-etapa"><option value="Contacto">Contacto</option><option value="Interesado">Interesado</option><option value="Inscrito">Inscrito</option></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Carrera</label>
                <select id="asp-carrera">
                    <option value="">— Sin carrera —</option>
                    <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Beca</label>
                <select id="asp-beca">
                    <option value="">No asignada</option>
                    <?php foreach ($becas as $b): ?>
                        <option value="<?= $b['id_beca'] ?>"><?= htmlspecialchars($b['nombre']) ?> (<?= $b['descuento'] ?>%)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label>Notas</label><textarea id="asp-notas" placeholder="Observaciones..."></textarea></div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px;" onclick="guardarAspirante()">💾 Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-aspirante')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Historial -->
<div class="modal-overlay" id="modal-historial">
    <div class="modal">
        <h3>📝 Agregar Interacción</h3>
        <div class="form-group"><label>Aspirante</label>
            <select id="hist-aspirante">
                <?php foreach ($aspirantes as $a): ?>
                    <option value="<?= $a['id_aspirante'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Tipo</label>
            <select id="hist-tipo">
                <option value="nota">📝 Nota</option>
                <option value="llamada">📞 Llamada</option>
                <option value="correo">✉️ Correo</option>
                <option value="visita">🏢 Visita</option>
            </select>
        </div>
        <div class="form-group"><label>Descripción *</label><textarea id="hist-desc" placeholder="Describe la interacción..." style="height:80px"></textarea></div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="guardarHistorial()">Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-historial')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Agenda -->
<div class="modal-overlay" id="modal-agenda">
    <div class="modal">
        <h3>📅 Nuevo Recordatorio</h3>
        <div class="form-group"><label>Título *</label><input type="text" id="ag-titulo" placeholder="Descripción del evento"></div>
        <div class="form-row">
            <div class="form-group"><label>Tipo</label>
                <select id="ag-tipo"><option value="tarea">✅ Tarea</option><option value="llamada">📞 Llamada</option><option value="correo">✉️ Correo</option><option value="reunion">🤝 Reunión</option></select>
            </div>
            <div class="form-group"><label>Fecha y hora *</label><input type="datetime-local" id="ag-fecha"></div>
        </div>
        <div class="form-group"><label>Aspirante (opcional)</label>
            <select id="ag-aspirante">
                <option value="">— Sin aspirante —</option>
                <?php foreach ($aspirantes as $a): ?>
                    <option value="<?= $a['id_aspirante'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="guardarAgenda()">Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-agenda')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Carrera -->
<div class="modal-overlay" id="modal-carrera">
    <div class="modal" style="width:400px;">
        <h3 id="modal-carrera-title">🎓 Nueva Carrera</h3>
        <input type="hidden" id="carrera-id">
        <div class="form-group"><label>Nombre de la carrera *</label><input type="text" id="carrera-nombre" placeholder="Ej: Ingeniería en Sistemas"></div>
        <div class="form-group" id="carrera-activa-group" style="display:none;">
            <label>Estado</label>
            <select id="carrera-activa"><option value="1">Activa</option><option value="0">Inactiva</option></select>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="guardarCarrera()">💾 Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-carrera')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Beca -->
<div class="modal-overlay" id="modal-beca">
    <div class="modal" style="width:400px;">
        <h3 id="modal-beca-title">💰 Nueva Beca</h3>
        <input type="hidden" id="beca-id">
        <div class="form-group"><label>Nombre de la beca *</label><input type="text" id="beca-nombre" placeholder="Ej: Beca Excelencia"></div>
        <div class="form-group"><label>Porcentaje de descuento * (0 - 100)</label><input type="number" id="beca-descuento" min="0" max="100" step="0.01" placeholder="Ej: 50"></div>
        <div class="form-group" id="beca-activa-group" style="display:none;">
            <label>Estado</label>
            <select id="beca-activa"><option value="1">Activa</option><option value="0">Inactiva</option></select>
        </div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="guardarBeca()">💾 Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-beca')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal: Confirmar eliminación -->
<div class="modal-overlay" id="modal-confirm">
    <div class="modal" style="max-width:360px;text-align:center;">
        <div style="font-size:48px;margin-bottom:10px;">⚠️</div>
        <h3 id="confirm-msg" style="font-size:15px;margin-bottom:18px;font-weight:700;"></h3>
        <div style="display:flex;gap:10px;">
            <button class="btn-sm btn-danger" style="flex:1;padding:11px" id="confirm-yes">Eliminar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-confirm')">Cancelar</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
// ============================================================
// Navegación de secciones
// ============================================================
const titulos = {
    dashboard:     ['Panel Principal',              'Resumen general del sistema'],
    aspirantes:    ['Aspirantes',                   'Gestión de aspirantes'],
    historial:     ['Historial de Interacciones',   'Registro de comunicaciones'],
    agenda:        ['Agenda y Recordatorios',        'Eventos programados'],
    carreras:      ['Catálogo de Carreras',          'Administración de carreras'],
    becas:         ['Catálogo de Becas',             'Administración de becas'],
    reportes:      ['Reportes',                      'Estadísticas del sistema'],
    configuracion: ['Configuración',                 'Ajustes del sistema'],
};

function mostrarSeccion(sec) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

    const el = document.getElementById('sec-' + sec);
    if (el) el.classList.add('active');

    const nav = document.querySelector(`.nav-item[data-seccion="${sec}"]`);
    if (nav) nav.classList.add('active');

    if (titulos[sec]) {
        document.getElementById('topbar-title').textContent = titulos[sec][0];
        document.getElementById('topbar-sub').textContent   = titulos[sec][1];
    }

    if (sec === 'reportes') cargarReportes();
}

function irAspirantes(etapa) {
    mostrarSeccion('aspirantes');
    document.getElementById('asp-filtro-etapa').value = etapa;
    filtrarTablaA();
}

// ============================================================
// Utilidades
// ============================================================
function toast(msg, tipo = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = tipo === 'error' ? 'error show' : 'show';
    setTimeout(() => el.className = '', 3500);
}
function abrirModal(id) { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// ============================================================
// Filtros de tablas
// ============================================================
function filtrarTablaD() {
    const etapa   = document.getElementById('dash-filtro-etapa').value.toLowerCase();
    const carrera = document.getElementById('dash-filtro-carrera').value;
    const busq    = document.getElementById('dash-busqueda').value.toLowerCase();
    document.querySelectorAll('#dash-tabla-asp tbody tr[data-etapa]').forEach(row => {
        const ok = (!etapa   || row.dataset.etapa.toLowerCase() === etapa)
                && (!carrera || row.dataset.carrera === carrera)
                && (!busq    || row.dataset.nombre.includes(busq) || row.dataset.email.includes(busq));
        row.style.display = ok ? '' : 'none';
    });
}
function filtrarTablaA() {
    const etapa   = document.getElementById('asp-filtro-etapa').value.toLowerCase();
    const carrera = document.getElementById('asp-filtro-carrera').value;
    const busq    = document.getElementById('asp-busqueda').value.toLowerCase();
    document.querySelectorAll('#asp-tbody tr[data-etapa]').forEach(row => {
        const ok = (!etapa   || row.dataset.etapa.toLowerCase() === etapa)
                && (!carrera || row.dataset.carrera === carrera)
                && (!busq    || row.dataset.nombre.includes(busq) || row.dataset.email.includes(busq));
        row.style.display = ok ? '' : 'none';
    });
}

// ============================================================
// CRUD Aspirantes
// ============================================================
function abrirModalAspirante() {
    document.getElementById('asp-id').value      = '';
    document.getElementById('asp-nombre').value  = '';
    document.getElementById('asp-email').value   = '';
    document.getElementById('asp-telefono').value = '';
    document.getElementById('asp-carrera').value  = '';
    document.getElementById('asp-etapa').value    = 'Contacto';
    document.getElementById('asp-beca').value     = '';
    document.getElementById('asp-notas').value    = '';
    document.getElementById('modal-asp-title').textContent = '📝 Nuevo Aspirante';
    abrirModal('modal-aspirante');
}

async function editarAspirante(id) {
    const resp = await fetch(`assets/api/aspirantes_api.php?accion=obtener&id=${id}`);
    const data = await resp.json();
    if (!data.ok) { toast('Error al cargar datos', 'error'); return; }
    const a = data.aspirante;
    document.getElementById('asp-id').value       = a.id_aspirante;
    document.getElementById('asp-nombre').value   = a.nombre;
    document.getElementById('asp-email').value    = a.email;
    document.getElementById('asp-telefono').value = a.telefono || '';
    document.getElementById('asp-carrera').value  = a.id_carrera || '';
    document.getElementById('asp-etapa').value    = a.etapa;
    document.getElementById('asp-beca').value     = a.id_beca || '';
    document.getElementById('asp-notas').value    = a.notas || '';
    document.getElementById('modal-asp-title').textContent = '✏️ Editar Aspirante';
    abrirModal('modal-aspirante');
}

async function guardarAspirante() {
    const id     = document.getElementById('asp-id').value;
    const nombre = document.getElementById('asp-nombre').value.trim();
    const email  = document.getElementById('asp-email').value.trim();
    if (!nombre || !email) { toast('Nombre y email son obligatorios', 'error'); return; }

    const datos = {
        accion:     id ? 'actualizar' : 'crear',
        id, nombre, email,
        telefono:   document.getElementById('asp-telefono').value,
        id_carrera: document.getElementById('asp-carrera').value,
        etapa:      document.getElementById('asp-etapa').value,
        id_beca:    document.getElementById('asp-beca').value,
        notas:      document.getElementById('asp-notas').value,
    };

    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    cerrarModal('modal-aspirante');
    if (data.ok) {
        toast(id ? '✅ Aspirante actualizado' : '✅ Aspirante registrado');
        setTimeout(() => location.reload(), 1200);
    } else {
        toast(data.mensaje || 'Error al guardar', 'error');
    }
}

async function guardarAspRapido() {
    const nombre = document.getElementById('dash-nombre').value.trim();
    const email  = document.getElementById('dash-email').value.trim();
    if (!nombre || !email) { toast('Nombre y email son obligatorios', 'error'); return; }
    const datos = {
        accion: 'crear', nombre, email,
        telefono:   document.getElementById('dash-telefono').value,
        id_carrera: document.getElementById('dash-carrera').value,
        etapa:      document.getElementById('dash-etapa').value,
    };
    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    if (data.ok) { toast('✅ Aspirante registrado'); setTimeout(() => location.reload(), 1200); }
    else { toast(data.mensaje || 'Error al guardar', 'error'); }
}

// ============================================================
// CRUD Carreras
// ============================================================
function abrirModalCarrera() {
    document.getElementById('carrera-id').value     = '';
    document.getElementById('carrera-nombre').value = '';
    document.getElementById('carrera-activa').value = '1';
    document.getElementById('carrera-activa-group').style.display = 'none';
    document.getElementById('modal-carrera-title').textContent = '🎓 Nueva Carrera';
    abrirModal('modal-carrera');
}

function editarCarrera(id, nombre, activa) {
    document.getElementById('carrera-id').value     = id;
    document.getElementById('carrera-nombre').value = nombre;
    document.getElementById('carrera-activa').value = activa;
    document.getElementById('carrera-activa-group').style.display = 'block';
    document.getElementById('modal-carrera-title').textContent = '✏️ Editar Carrera';
    abrirModal('modal-carrera');
}

async function guardarCarrera() {
    const id     = document.getElementById('carrera-id').value;
    const nombre = document.getElementById('carrera-nombre').value.trim();
    if (!nombre) { toast('El nombre es obligatorio', 'error'); return; }

    const datos = {
        accion:  id ? 'actualizar' : 'crear',
        id, nombre,
        activa: parseInt(document.getElementById('carrera-activa').value),
    };
    const resp = await fetch('assets/api/carreras_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    cerrarModal('modal-carrera');
    if (data.ok) { toast(id ? '✅ Carrera actualizada' : '✅ Carrera creada'); setTimeout(() => location.reload(), 1200); }
    else { toast(data.mensaje || 'Error', 'error'); }
}

// ============================================================
// CRUD Becas
// ============================================================
function abrirModalBeca() {
    document.getElementById('beca-id').value        = '';
    document.getElementById('beca-nombre').value    = '';
    document.getElementById('beca-descuento').value = '';
    document.getElementById('beca-activa').value    = '1';
    document.getElementById('beca-activa-group').style.display = 'none';
    document.getElementById('modal-beca-title').textContent = '💰 Nueva Beca';
    abrirModal('modal-beca');
}

function editarBeca(id, nombre, descuento, activa) {
    document.getElementById('beca-id').value        = id;
    document.getElementById('beca-nombre').value    = nombre;
    document.getElementById('beca-descuento').value = descuento;
    document.getElementById('beca-activa').value    = activa;
    document.getElementById('beca-activa-group').style.display = 'block';
    document.getElementById('modal-beca-title').textContent = '✏️ Editar Beca';
    abrirModal('modal-beca');
}

async function guardarBeca() {
    const id        = document.getElementById('beca-id').value;
    const nombre    = document.getElementById('beca-nombre').value.trim();
    const descuento = parseFloat(document.getElementById('beca-descuento').value);
    if (!nombre) { toast('El nombre es obligatorio', 'error'); return; }
    if (isNaN(descuento) || descuento < 0 || descuento > 100) { toast('El descuento debe estar entre 0 y 100', 'error'); return; }

    const datos = {
        accion: id ? 'actualizar' : 'crear',
        id, nombre, descuento,
        activa: parseInt(document.getElementById('beca-activa').value),
    };
    const resp = await fetch('assets/api/becas_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    cerrarModal('modal-beca');
    if (data.ok) { toast(id ? '✅ Beca actualizada' : '✅ Beca creada'); setTimeout(() => location.reload(), 1200); }
    else { toast(data.mensaje || 'Error', 'error'); }
}

// ============================================================
// Eliminar genérico
// ============================================================
let _delId = null, _delTipo = null;

function confirmarEliminar(id, nombre, tipo) {
    _delId = id; _delTipo = tipo;
    document.getElementById('confirm-msg').textContent = `¿Eliminar "${nombre}"? Esta acción no se puede deshacer.`;
    abrirModal('modal-confirm');
    document.getElementById('confirm-yes').onclick = ejecutarEliminar;
}

async function ejecutarEliminar() {
    const apis = { aspirante:'aspirantes_api.php', carrera:'carreras_api.php', beca:'becas_api.php' };
    const api  = apis[_delTipo];
    if (!api) return;
    const resp = await fetch(`assets/api/${api}`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ accion:'eliminar', id: _delId })
    });
    const data = await resp.json();
    cerrarModal('modal-confirm');
    if (data.ok) { toast('🗑️ Eliminado correctamente'); setTimeout(() => location.reload(), 1000); }
    else { toast(data.mensaje || 'Error al eliminar', 'error'); }
}

// ============================================================
// Historial
// ============================================================
async function guardarHistorial() {
    const datos = {
        id_aspirante: document.getElementById('hist-aspirante').value,
        tipo:         document.getElementById('hist-tipo').value,
        descripcion:  document.getElementById('hist-desc').value.trim(),
    };
    if (!datos.descripcion) { toast('Escribe una descripción', 'error'); return; }
    const resp = await fetch('assets/api/historial_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    cerrarModal('modal-historial');
    if (data.ok) { toast('✅ Interacción registrada'); setTimeout(() => location.reload(), 1000); }
    else { toast('Error al guardar', 'error'); }
}

// ============================================================
// Agenda
// ============================================================
function abrirModalAgenda() {
    const tm = new Date(); tm.setDate(tm.getDate()+1); tm.setHours(9,0,0,0);
    document.getElementById('ag-fecha').value = tm.toISOString().slice(0,16);
    document.getElementById('ag-titulo').value = '';
    abrirModal('modal-agenda');
}
async function guardarAgenda() {
    const datos = {
        titulo:       document.getElementById('ag-titulo').value.trim(),
        tipo:         document.getElementById('ag-tipo').value,
        fecha_hora:   document.getElementById('ag-fecha').value,
        id_aspirante: document.getElementById('ag-aspirante').value,
    };
    if (!datos.titulo || !datos.fecha_hora) { toast('Completa los campos requeridos', 'error'); return; }
    const resp = await fetch('assets/api/agenda_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({accion:'crear', ...datos})
    });
    const data = await resp.json();
    cerrarModal('modal-agenda');
    if (data.ok) { toast('📅 Evento agregado'); setTimeout(() => location.reload(), 1000); }
    else { toast('Error al guardar', 'error'); }
}
async function completarAgenda(id, btn) {
    const resp = await fetch('assets/api/agenda_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({accion:'completar', id})
    });
    const data = await resp.json();
    if (data.ok) {
        btn.closest('.agenda-item, tr').style.opacity = '0.4';
        btn.disabled = true;
        toast('✅ Marcado como completado');
    }
}

// ============================================================
// Reportes
// ============================================================
async function cargarReportes() {
    const cont = document.getElementById('reportes-contenido');
    cont.innerHTML = '<div class="loading-spinner">⏳ Generando reporte...</div>';
    try {
        const resp = await fetch('assets/api/reportes_api.php');
        const d    = await resp.json();
        if (!d.ok) { cont.innerHTML = `<div class="loading-spinner">❌ Error: ${d.mensaje}</div>`; return; }

        const maxCarrera = Math.max(...d.porCarrera.map(r => r.total), 1);
        const maxEtapa   = Math.max(...d.porEtapa.map(r => r.total), 1);

        const colores = { Contacto:'#f5a623', Interesado:'#e07000', Inscrito:'#28a745' };
        const iconosH = { llamada:'📞', correo:'✉️', visita:'🏢', nota:'📝' };

        cont.innerHTML = `
        <div class="stats-grid">
            <div class="stat-box"><div class="stat-num">${d.totalAspirantes}</div><div class="stat-label">Total Aspirantes</div></div>
            ${d.porEtapa.map(e => `<div class="stat-box"><div class="stat-num" style="color:${colores[e.etapa]||'var(--blue-dark)'}">${e.total}</div><div class="stat-label">${e.etapa}</div></div>`).join('')}
            <div class="stat-box"><div class="stat-num" style="color:var(--green)">${d.totalConBeca}</div><div class="stat-label">Con Beca Asignada</div></div>
        </div>

        <div class="reportes-grid">
            <div class="card">
                <div class="card-header"><h3>🎓 Aspirantes por Carrera</h3></div>
                <div class="card-body">
                    ${d.porCarrera.length === 0 ? '<p style="color:var(--text-light);text-align:center">Sin datos</p>' :
                      d.porCarrera.map(r => `
                        <div class="bar-item">
                            <div class="bar-label">${r.carrera}</div>
                            <div class="bar-track"><div class="bar-fill" style="width:${(r.total/maxCarrera*100).toFixed(1)}%"></div></div>
                            <div class="bar-count">${r.total}</div>
                        </div>`).join('')}
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>📊 Pipeline de Etapas</h3></div>
                <div class="card-body">
                    ${d.porEtapa.map(r => `
                        <div class="bar-item">
                            <div class="bar-label">${r.etapa}</div>
                            <div class="bar-track"><div class="bar-fill" style="width:${(r.total/maxEtapa*100).toFixed(1)}%;background:${colores[r.etapa]||'var(--blue)'}"></div></div>
                            <div class="bar-count">${r.total}</div>
                        </div>`).join('')}
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>💰 Becas Asignadas</h3></div>
                <div class="card-body">
                    ${d.becasAsignadas.length === 0 ? '<p style="color:var(--text-light);text-align:center">Sin becas asignadas</p>' :
                    `<table style="width:100%;font-size:13px;border-collapse:collapse">
                        <thead><tr><th style="padding:8px 0;text-align:left;color:var(--text-light)">Beca</th><th style="padding:8px 0;text-align:center;color:var(--text-light)">Asignadas</th><th style="padding:8px 0;text-align:right;color:var(--text-light)">Descuento</th></tr></thead>
                        <tbody>${d.becasAsignadas.map(b => `<tr><td style="padding:8px 0;border-bottom:1px solid var(--border)">${b.beca}</td><td style="text-align:center;border-bottom:1px solid var(--border)">${b.asignadas}</td><td style="text-align:right;border-bottom:1px solid var(--border);font-weight:700">${b.descuento}%</td></tr>`).join('')}</tbody>
                    </table>`}
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>📞 Actividad (últimos 30 días)</h3></div>
                <div class="card-body">
                    ${d.historial30.length === 0 ? '<p style="color:var(--text-light);text-align:center">Sin actividad registrada</p>' :
                    d.historial30.map(h => `
                        <div class="bar-item">
                            <div class="bar-label">${iconosH[h.tipo]||'📝'} ${h.tipo}</div>
                            <div class="bar-count" style="font-size:15px;font-weight:800;color:var(--blue-dark)">${h.total}</div>
                        </div>`).join('')}
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:18px;">
            <div class="card-header"><h3>🕐 Registros Recientes</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nombre</th><th>Email</th><th>Carrera</th><th>Etapa</th><th>Registrado</th></tr></thead>
                    <tbody>
                    ${d.recientes.length === 0 ? '<tr><td colspan="5" class="empty-table">Sin registros</td></tr>' :
                      d.recientes.map(r => `
                        <tr>
                            <td><strong>${r.nombre}</strong></td>
                            <td>${r.email}</td>
                            <td>${r.carrera || '—'}</td>
                            <td><span class="etapa-badge" style="background:${colores[r.etapa]||'#aaa'}">${r.etapa}</span></td>
                            <td style="font-size:12px;white-space:nowrap">${new Date(r.creado_en).toLocaleDateString('es-MX', {day:'2-digit',month:'short',year:'numeric'})}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
        </div>`;
    } catch(e) {
        cont.innerHTML = `<div class="loading-spinner">❌ Error de conexión: ${e.message}</div>`;
    }
}

// ============================================================
// Configuración: Credenciales
// ============================================================
async function guardarCredenciales() {
    const passActual    = document.getElementById('cfg-pass-actual').value;
    const nuevoUsuario  = document.getElementById('cfg-usuario').value.trim();
    const nuevaPass     = document.getElementById('cfg-pass-nueva').value;
    const confirmarPass = document.getElementById('cfg-pass-confirmar').value;
    const alertEl       = document.getElementById('cfg-alert');

    const showAlert = (msg, tipo = 'error') => {
        alertEl.textContent = msg;
        alertEl.style.display = 'block';
        alertEl.style.background = tipo === 'ok' ? '#e8f5e9' : '#fff0f0';
        alertEl.style.color      = tipo === 'ok' ? '#1e7e3a' : '#c0392b';
        alertEl.style.border     = `1px solid ${tipo === 'ok' ? '#c3e6cb' : '#ffcccc'}`;
    };

    if (!passActual)                    return showAlert('La contraseña actual es obligatoria.');
    if (!nuevoUsuario)                  return showAlert('El nombre de usuario no puede estar vacío.');
    if (nuevoUsuario.length < 3)        return showAlert('El usuario debe tener al menos 3 caracteres.');
    if (!nuevaPass)                     return showAlert('La nueva contraseña no puede estar vacía.');
    if (nuevaPass.length < 6)           return showAlert('La nueva contraseña debe tener al menos 6 caracteres.');
    if (nuevaPass !== confirmarPass)    return showAlert('Las contraseñas no coinciden.');

    try {
        const resp = await fetch('assets/api/admin_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                accion: 'cambiar_credenciales',
                contrasena_actual:    passActual,
                nuevo_usuario:        nuevoUsuario,
                nueva_contrasena:     nuevaPass,
                confirmar_contrasena: confirmarPass,
            })
        });
        const data = await resp.json();
        if (data.ok) {
            showAlert('✅ ' + data.mensaje, 'ok');
            document.getElementById('cfg-pass-actual').value    = '';
            document.getElementById('cfg-pass-nueva').value     = '';
            document.getElementById('cfg-pass-confirmar').value = '';
            toast('✅ Credenciales actualizadas');
        } else {
            showAlert('⚠️ ' + data.mensaje);
        }
    } catch(e) {
        showAlert('Error de red al procesar la solicitud.');
    }
}
</script>
</body>
</html>

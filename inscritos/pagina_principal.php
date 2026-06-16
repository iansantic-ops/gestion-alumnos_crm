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
// Dashboard mini-tabla (5 registros, paginación propia)
$paginaDash   = max(1, (int)($_GET['paginad'] ?? 1));
$porPaginaDash = 5;
$aspirantesDash = $aspiranteModel->listar([], $paginaDash, $porPaginaDash);
$totalAsp         = $aspiranteModel->totalAspirantes;
$totalPaginasDash = max(1, (int)ceil($totalAsp / $porPaginaDash));
// Sección Aspirantes: la tabla se carga vía AJAX (filtros + paginación server-side)
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
        'Inscrito'       => '#28a745',
        'Interesado'     => '#e07b00',
        'No Interesado'  => '#c0392b',
        default          => '#0077cc', // Contacto
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
            --blue-dark: #2085a7;
            --blue:      #1a4a8a;
            --blue-light:#eef3fb;
            --orange:    #f08c00;
            --amazul:     #d0ca23;
            --green:     #28a745;
            --red:     #c0392b;
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


/* Menú flotante de etapas */
.stage-menu {
    position:fixed; background:white; border-radius:10px;
    box-shadow:0 6px 24px rgba(0,0,0,0.18); border:1px solid var(--border);
    z-index:500; overflow:hidden; min-width:150px;
}
.stage-menu-item {
    display:flex; align-items:center; gap:10px; padding:10px 16px;
    font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s;
    font-family:'Nunito',sans-serif;
}
.stage-menu-item:hover { background:var(--gray-bg); }
.stage-dot { width:11px; height:11px; border-radius:50%; flex-shrink:0; }


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

        .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .section-header h2 { font-size:20px; font-weight:800; color:var(--blue-dark); }

        /* ===== AGENDA VIEW TOGGLE (nuevos botones lista/calendario) ===== */
        .agenda-view-toggle {
            display:flex; gap:6px; padding:10px 16px;
            border-bottom:1px solid var(--border); background:var(--gray-bg);
        }
        .view-btn {
            padding:6px 14px; border-radius:8px; font-size:13px; font-weight:700;
            font-family:inherit; cursor:pointer; border:1.5px solid var(--border);
            background:white; color:var(--text-light); transition:all 0.2s;
        }
        .view-btn:hover { background:var(--blue-light); color:var(--blue); border-color:var(--blue); }
        .view-btn.active { background:var(--blue); color:white; border-color:var(--blue); }

        /* ===== BULK ACTION BAR (barra de selección masiva) ===== */
        .bulk-bar {
            display:none; align-items:center; gap:8px; flex-wrap:wrap;
            padding:10px 16px; background:linear-gradient(135deg,var(--blue),var(--blue-dark));
            border-top:1px solid rgba(255,255,255,0.15);
        }
        .bulk-bar.visible { display:flex; }
        .bulk-bar #bulk-count {
            font-size:13px; font-weight:700; color:white; margin-right:4px;
        }
        .bulk-btn {
            padding:6px 13px; border-radius:8px; font-size:12px; font-weight:700;
            font-family:inherit; cursor:pointer; border:none; color:white;
            transition:opacity 0.2s, transform 0.1s;
        }
        .bulk-btn:hover { opacity:0.85; transform:translateY(-1px); }

        /* ===== CONTACT BAR (botones de acción en tabla aspirantes) ===== */
        .contact-bar { display:flex; gap:4px; align-items:center; flex-wrap:wrap; }
        .btn-wa   { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#e8f5e9; color:#1e7e3a; font-family:inherit; font-weight:700; }
        .btn-wa:hover   { background:#1e7e3a; color:white; }
        .btn-call { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#e3f2fd; color:#1a4a8a; font-family:inherit; font-weight:700; }
        .btn-call:hover { background:#1a4a8a; color:white; }
        .btn-mail-sm { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#fff8e1; color:#e07b00; font-family:inherit; font-weight:700; }
        .btn-mail-sm:hover { background:#e07b00; color:white; }
        .btn-sched { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#f3e8ff; color:#7c3aed; font-family:inherit; font-weight:700; }
        .btn-sched:hover { background:#7c3aed; color:white; }
        .btn-edit-sm { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#e8f0fe; color:#1a4a8a; font-family:inherit; font-weight:700; }
        .btn-edit-sm:hover { background:#1a4a8a; color:white; }
        .btn-del-sm  { padding:5px 9px; border-radius:6px; font-size:13px; cursor:pointer; border:none; background:#fff0f0; color:#c0392b; font-family:inherit; font-weight:700; }
        .btn-del-sm:hover  { background:#c0392b; color:white; }

        /* ===== HAMBURGER / MENU TOGGLE (responsive sidebar) ===== */
        .menu-toggle {
            display:none; background:none; border:none; cursor:pointer;
            padding:6px; border-radius:8px; transition:background 0.2s; color:var(--blue-dark);
        }
        .menu-toggle:hover { background:var(--blue-light); }
        .menu-toggle span { display:block; width:22px; height:2px; background:currentColor; margin:5px 0; border-radius:2px; transition:all 0.3s; }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:99; }

        /* ===== FULLCALENDAR container ===== */
        #fc-container { padding:16px 20px; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) {
            .dashboard-grid { grid-template-columns:1fr; }
            .bottom-grid    { grid-template-columns:1fr; }
            .reportes-grid  { grid-template-columns:1fr; }
            .config-grid    { grid-template-columns:1fr; }
            .pipeline-row   { grid-template-columns:1fr 1fr; grid-template-rows:auto auto; }
            .total-box      { grid-column: span 2; text-align:center; }
        }

        @media (max-width: 768px) {
            :root { --sidebar-w: 0px; }

            .menu-toggle { display:block; }
            .sidebar-overlay.open { display:block; }

            .sidebar {
                width:240px; transform:translateX(-100%);
                transition:transform 0.3s ease; z-index:200;
            }
            .sidebar.open { transform:translateX(0); }

            .main-content { margin-left:0; }

            .topbar { padding:12px 16px; gap:10px; }
            .topbar h1 { font-size:16px; }

            .section { padding:16px; }

            .pipeline-row { grid-template-columns:1fr 1fr; gap:10px; }
            .total-box { grid-column:span 2; }
            .metric-num { font-size:28px; }

            .dashboard-grid { grid-template-columns:1fr; }
            .bottom-grid    { grid-template-columns:1fr; }
            .reportes-grid  { grid-template-columns:1fr; }
            .config-grid    { grid-template-columns:1fr; }

            .filters-row { flex-direction:column; align-items:stretch; }
            .filters-row select, .filters-row input { width:100%; min-width:unset; }

            .section-header { flex-direction:column; align-items:flex-start; }
            .section-header > div { flex-wrap:wrap; gap:6px; }

            .form-row { grid-template-columns:1fr; }

            table { font-size:12px; }
            th, td { padding:8px 10px; }

            .modal { width:95vw !important; padding:20px; }

            .bulk-bar { flex-wrap:wrap; }
            .bulk-btn { font-size:11px; padding:5px 10px; }

            .topbar-badge { display:none; }

            /* Tablas responsivas mejoradas */
            .table-wrap {
                -webkit-overflow-scrolling: touch;
                overflow-x: auto;
                border-radius: 0 0 var(--radius) var(--radius);
            }
            /* Columnas menos críticas se ocultan en móvil */
            .td-email { display: none; }
            th:nth-child(2) { display: none; }
            /* Cards de pipeline más compactos */
            .metric-card { padding: 12px 14px !important; }
            .metric-num { font-size: 26px !important; }
            /* Badges de etapa más pequeños */
            .etapa-badge { font-size: 11px; padding: 3px 8px; }
            /* Botones de acción en columna */
            .action-btns { flex-direction: column; gap: 3px; }
            .action-btns button { font-size: 11px; padding: 4px 8px; }
            /* Paginación compacta */
            #dash-paginacion { flex-direction: column; align-items: center; gap: 6px; }
        }

        @media (max-width: 480px) {
            .pipeline-row { grid-template-columns:1fr; }
            .total-box { grid-column:unset; }
            .contact-bar { flex-wrap:wrap; }
            .stats-grid { grid-template-columns:1fr 1fr; }
            .topbar h1 { font-size:14px; }
        }

        /* ===== TOPBAR ICON BUTTONS (bell, dark-toggle, search) ===== */
        .bell-btn, .dark-toggle {
            position:relative; background:var(--gray-bg); border:1.5px solid var(--border);
            border-radius:10px; padding:7px 12px; font-size:13px; cursor:pointer;
            font-family:inherit; font-weight:700; color:var(--text-light);
            transition:all 0.2s; line-height:1; white-space:nowrap;
        }
        .bell-btn:hover, .dark-toggle:hover {
            background:var(--blue-light); border-color:var(--blue); color:var(--blue);
        }
        .bell-badge {
            position:absolute; top:-5px; right:-5px; background:#e24b4a; color:white;
            font-size:10px; font-weight:800; border-radius:10px; padding:0 4px;
            min-width:16px; height:16px; line-height:16px; text-align:center;
            border:2px solid white;
        }

        /* ===== GLOBAL SEARCH OVERLAY ===== */
        #global-search-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(15,30,60,0.55); z-index:900;
            align-items:flex-start; justify-content:center;
            padding-top:80px;
            backdrop-filter:blur(3px);
        }
        #global-search-overlay.open { display:flex; }
        #global-search-box {
            width:560px; max-width:94vw;
            background:white; border-radius:16px;
            box-shadow:0 24px 60px rgba(0,0,0,0.28);
            overflow:hidden; animation:popIn 0.2s ease;
        }
        #global-search-input {
            width:100%; padding:16px 20px;
            border:none; border-bottom:1.5px solid var(--border);
            font-size:15px; font-family:inherit; color:var(--text);
            outline:none; background:white;
        }
        #global-search-results { max-height:360px; overflow-y:auto; padding:8px 0; }
        .gs-hint {
            text-align:center; padding:14px 20px;
            font-size:13px; color:var(--text-light); font-weight:600;
        }
        .gs-hint kbd {
            background:var(--gray-bg); border:1px solid var(--border);
            border-radius:5px; padding:2px 6px; font-size:11px;
            font-family:monospace; color:var(--text);
        }
        .gs-item {
            display:flex; align-items:center; gap:12px;
            padding:10px 20px; cursor:pointer; transition:background 0.15s;
        }
        .gs-item:hover { background:var(--blue-light); }
        .gs-item-badge {
            display:inline-block; padding:3px 10px; border-radius:20px;
            font-size:11px; font-weight:700; color:white; flex-shrink:0;
        }
        .gs-item-info { flex:1; min-width:0; }
        .gs-item-name { font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gs-item-sub  { font-size:12px; color:var(--text-light); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* ===== QUICK VIEW DRAWER ===== */
        #quick-view {
            position:fixed; top:0; right:-340px; width:320px; height:100vh;
            background:white; box-shadow:-4px 0 30px rgba(0,0,0,0.15);
            z-index:300; display:flex; flex-direction:column;
            transition:right 0.3s cubic-bezier(.4,0,.2,1);
            overflow-y:auto;
        }
        #quick-view.open { right:0; }

        .qv-header {
            display:flex; align-items:center; justify-content:space-between;
            padding:18px 20px 14px; border-bottom:1px solid rgba(255,255,255,0.15);
            background:linear-gradient(135deg, var(--blue-dark), var(--blue));
            position:sticky; top:0; z-index:2; flex-shrink:0;
        }
        #qv-nombre { font-size:15px; font-weight:800; color:white; }
        #qv-email  { font-size:12px; color:rgba(255,255,255,0.7); margin-top:2px; }

        .qv-close {
            background:rgba(255,255,255,0.18); border:none; border-radius:8px;
            width:30px; height:30px; display:flex; align-items:center; justify-content:center;
            font-size:14px; cursor:pointer; color:white; font-weight:700;
            transition:background 0.2s; flex-shrink:0; margin-left:8px;
        }
        .qv-close:hover { background:rgba(255,255,255,0.35); }

        .qv-section {
            padding:14px 20px; border-bottom:1px solid var(--border); flex-shrink:0;
        }
        .qv-section h4 {
            font-size:11px; font-weight:800; color:var(--text-light);
            text-transform:uppercase; letter-spacing:.7px; margin-bottom:10px;
        }
        .qv-row {
            display:flex; align-items:flex-start; gap:8px;
            padding:5px 0; border-bottom:1px solid var(--border); font-size:13px;
        }
        .qv-row:last-child { border-bottom:none; }
        .qv-label {
            font-weight:700; color:var(--text-light); min-width:80px; flex-shrink:0;
            font-size:12px; padding-top:1px;
        }
        .qv-val { color:var(--text); flex:1; word-break:break-word; font-size:13px; }

        /* Dark mode */
        /* ===== MODO OSCURO — se activa con html[data-theme="dark"] ===== */
        [data-theme="dark"] body {
            --gray-bg:#1a1f2e; --white:#242938; --text:#e2e8f0;
            --text-light:#94a3b8; --border:#2d3748;
            background:var(--gray-bg);
            color:var(--text);
        }
        [data-theme="dark"] .topbar,
        [data-theme="dark"] .card,
        [data-theme="dark"] #quick-view,
        [data-theme="dark"] #global-search-box { background:#242938; color:var(--text); }
        [data-theme="dark"] .topbar { border-bottom-color:var(--border); }
        [data-theme="dark"] .sidebar { background:linear-gradient(180deg,#1a2744 0%,#0f1b35 100%); }
        [data-theme="dark"] th { background:#1a1f2e; color:var(--text-light); border-bottom-color:var(--border); }
        [data-theme="dark"] td { color:var(--text); border-bottom-color:var(--border); }
        [data-theme="dark"] tr:hover td { background:#2a3040; }
        [data-theme="dark"] .filters-row { background:#1a1f2e; border-color:var(--border); }
        [data-theme="dark"] .filters-row select,
        [data-theme="dark"] .filters-row input { background:#242938; color:var(--text); border-color:var(--border); }
        [data-theme="dark"] .form-group input,
        [data-theme="dark"] .form-group select,
        [data-theme="dark"] .form-group textarea { background:#1a1f2e; border-color:var(--border); color:var(--text); }
        [data-theme="dark"] .bell-btn,
        [data-theme="dark"] .dark-toggle { background:#2d3748; border-color:#3d4758; color:#94a3b8; }
        [data-theme="dark"] .bell-btn:hover,
        [data-theme="dark"] .dark-toggle:hover { background:#3d4758; color:white; }
        [data-theme="dark"] #global-search-input { background:#242938; color:var(--text); border-bottom-color:var(--border); }
        [data-theme="dark"] .gs-item:hover { background:#2d3748; }
        [data-theme="dark"] .qv-section { border-bottom-color:var(--border); }
        [data-theme="dark"] .qv-row { border-bottom-color:var(--border); }
        [data-theme="dark"] .total-box,
        [data-theme="dark"] .stat-box { background:#242938; }
        [data-theme="dark"] .dark-toggle { color:#f6c90e; }
        [data-theme="dark"] .modal { background:#242938; }
        [data-theme="dark"] .modal h3 { color:#93c5fd; }
        [data-theme="dark"] .section-header h2 { color:#93c5fd; }
        [data-theme="dark"] .card-header { border-bottom-color:var(--border); }
        [data-theme="dark"] .card-header h3 { color:#93c5fd; }
        [data-theme="dark"] .stage-menu { background:#242938; border-color:var(--border); }
        [data-theme="dark"] .stage-menu-item:hover { background:#1a1f2e; }
        [data-theme="dark"] .nav-item { color:rgba(255,255,255,0.72); }
        [data-theme="dark"] .modal-overlay { background:rgba(0,0,0,0.7); }
        [data-theme="dark"] .topbar h1 { color:#93c5fd; }
        [data-theme="dark"] .badge-activa { background:#1a3a2a; color:#6ee7b7; border-color:#2d5a3d; }
        [data-theme="dark"] .badge-inactiva { background:#1a1f2e; color:#64748b; border-color:#2d3748; }
        [data-theme="dark"] .btn-edit { background:#1e3a5f; color:#93c5fd; }
        [data-theme="dark"] .btn-del { background:#3b1a1a; color:#fca5a5; }
        [data-theme="dark"] .btn-primary { background:#1a4a8a; }
        [data-theme="dark"] .btn-sm { background:#2d3748; color:var(--text); border-color:var(--border); }
        [data-theme="dark"] input[type="text"],
        [data-theme="dark"] input[type="email"],
        [data-theme="dark"] input[type="tel"],
        [data-theme="dark"] input[type="datetime-local"],
        [data-theme="dark"] select,
        [data-theme="dark"] textarea { background:#1a1f2e; color:var(--text); border-color:var(--border); }
        /* Transición suave al cambiar tema */
        body, .card, .topbar, .sidebar, th, td, .filters-row, .form-group input,
        .form-group select, .form-group textarea, .modal, .stage-menu {
            transition: background-color 0.25s ease, color 0.15s ease, border-color 0.2s ease;
        }

        /* ===== MEJORAS GENERALES DE TABLAS ===== */
        .table-wrap { border-radius: 0 0 var(--radius) var(--radius); }
        table { border-collapse: separate; border-spacing: 0; }
        tbody tr { transition: background 0.12s ease; }
        tbody tr:hover td { background: #f0f4ff; }
        [data-theme="dark"] tbody tr:hover td { background: #2a3040; }

        /* Skeleton loader para tablas */
        @keyframes shimmer {
            0%   { background-position: -700px 0; }
            100% { background-position: 700px 0; }
        }
        .skeleton-row td {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 700px 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 4px;
            color: transparent !important;
            height: 18px;
        }
        [data-theme="dark"] .skeleton-row td {
            background: linear-gradient(90deg, #2d3748 25%, #3d4758 50%, #2d3748 75%);
            background-size: 700px 100%;
        }

        /* ===== SCROLLBAR ESTILIZADO ===== */
        .table-wrap::-webkit-scrollbar { height: 5px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-bg); }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        /* ===== MEJORAS RESPONSIVE ADICIONALES ===== */
        @media (max-width: 600px) {
            .topbar-actions { gap: 5px; }
            .topbar-actions .dark-toggle:last-child { display: none; } /* Ocultar ctrl+k en muy pequeño */
            .pipeline-row { gap: 8px; }
            .section { padding: 12px; }
            .card-body { padding: 12px; }
            .card-header { padding: 12px 14px; }
            /* Tabla tipo lista en móvil muy pequeño */
            #dash-tabla-asp thead { display: none; }
            #dash-tabla-asp tbody tr { display: grid; grid-template-columns: 1fr auto; gap: 4px; padding: 10px 12px; border-bottom: 1px solid var(--border); }
            #dash-tabla-asp tbody td { border: none; padding: 0; font-size: 13px; }
            #dash-tabla-asp tbody td:nth-child(2) { display: none; } /* email */
            #dash-tabla-asp tbody td:nth-child(3) { grid-column: 1; font-size: 11px; color: var(--text-light); }
            #dash-tabla-asp tbody td:nth-child(4) { grid-column: 2; grid-row: 1; display: flex; align-items: flex-start; }
            #dash-tabla-asp tbody td:nth-child(5) { grid-column: 2; grid-row: 2; display: flex; align-items: flex-end; }
            #dash-tabla-asp tbody tr:last-child { border-bottom: none; }
        }

        @media (max-width: 768px) {
            #quick-view { width:88vw; right:-90vw; }
            #quick-view.open { right:0; }
            #global-search-box { width:94vw; }
            .bell-btn .bell-text, .dark-toggle .toggle-text { display:none; }
            .dark-toggle { padding:7px 10px; }
        }
        /* ===== ESTILOS DE IMPRESIÓN / PDF ===== */
        @media print {
            /* Ocultar todo lo que no es el reporte */
            .sidebar, .topbar, .section:not(#sec-reportes),
            .section-header button, #sec-reportes .section-header button,
            .bell-btn, .dark-toggle, .sidebar-overlay,
            #global-search-overlay, #quick-view, #toast,
            .modal-overlay { display: none !important; }

            /* Resetear layout */
            body { display: block !important; background: white !important; color: #000 !important; font-family: Arial, sans-serif; }
            .main-content { margin-left: 0 !important; }
            #sec-reportes { display: block !important; padding: 0 !important; }

            /* Cabecera del reporte */
            #print-header { display: block !important; }

            /* Grilla a 2 columnas en papel */
            .reportes-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 14px; }
            .stats-grid    { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 10px; margin-bottom: 14px; }

            /* Cards sin sombra */
            .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; margin-bottom: 14px; }
            .card-header { background: #f5f5f5 !important; }

            /* Barras de progreso visibles */
            .bar-fill { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .etapa-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .stat-box { border: 1px solid #ddd !important; }

            /* Tablas */
            table { font-size: 11px; }
            th { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            /* Charts: asegurar que se vean */
            canvas { max-width: 100% !important; height: auto !important; }

            /* Pie de página */
            #print-footer { display: block !important; margin-top: 20px; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }

            @page { margin: 1.5cm; size: A4; }
        }
        /* Elementos solo visibles al imprimir */
        #print-header, #print-footer { display: none; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
</head>
<body>
<!-- ID de sesión para aislar localStorage por usuario -->
<meta name="crm-uid" content="<?= (int)$_SESSION['id_usuario'] ?>">

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

        <a class="nav-item" onclick="mostrarSeccion('papelera')" data-seccion="papelera">
            <span class="ni-icon">🗑️</span> Papelera
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

<!-- OVERLAY PARA SIDEBAR MÓVIL -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="cerrarSidebar()"></div>

<!-- MAIN CONTENT -->
<main class="main-content">

    <div class="topbar">
        <div style="display:flex;align-items:center;gap:10px;">
            <button class="menu-toggle" id="menu-toggle" onclick="toggleSidebar()" aria-label="Menú">
                <span></span><span></span><span></span>
            </button>
            <div>
                <h1 id="topbar-title">Panel Principal</h1>
                <div class="topbar-sub" id="topbar-sub">Bienvenido, <?= htmlspecialchars($usuario_nombre) ?></div>
            </div>
        </div>
        <div class="topbar-actions" style="display:flex;align-items:center;gap:8px;">
            <span class="topbar-badge">📅 <?= date('d M Y') ?></span>
            <button class="bell-btn" onclick="toggleBellMenu()" title="Tareas hoy">
                🔔<span class="bell-badge" id="bell-badge" style="display:none">0</span>
            </button>
            <button class="dark-toggle" onclick="toggleDark()" title="Modo oscuro">🌙</button>
            <button class="dark-toggle" onclick="abrirBusqueda()" title="Búsqueda global (Ctrl+K)">🔍 Ctrl+K</button>
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
                    <select id="dash-filtro-etapa" onchange="cargarDashboard(1)">
                        <option value="">Todas las etapas</option>
                        <option value="Contacto">Contacto</option>
                        <option value="Interesado">Interesado</option>
                        <option value="Inscrito">Inscrito</option>
                        <option value="No Interesado">No Interesado</option>
                    </select>
                    <select id="dash-filtro-carrera" onchange="cargarDashboard(1)">
                        <option value="">Todas las carreras</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="dash-busqueda" placeholder="🔍 Buscar..." oninput="debounceDash()">
                </div>
                <div class="table-wrap">
                    <table id="dash-tabla-asp">
                        <thead><tr><th>Nombre</th><th>Email</th><th>Carrera</th><th>Etapa</th><th>Acciones</th></tr></thead>
                        <tbody id="dash-tbody">
                            <tr><td colspan="5" class="empty-table" style="padding:28px;">⏳ Cargando...</td></tr>
                        </tbody>
                    </table>
               </div>
               <div id="dash-paginacion" style="display:none;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid var(--border);background:var(--gray-bg);flex-wrap:wrap;gap:8px;"></div>

            </div> <!-- cierra card -->

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
                    <div class="form-group"><label>Origen / Canal</label>
                        <select id="dash-origen">
                            <option value="Otro">🔹 Otro</option>
                            <option value="Web">🌐 Web</option>
                            <option value="Redes sociales">📱 Redes sociales</option>
                            <option value="Feria">🏫 Feria</option>
                            <option value="Referido">🤝 Referido</option>
                            <option value="Llamada">📞 Llamada</option>
                        </select>
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
            <div style="display:flex;gap:8px;">
                <button class="btn-sm btn-success" onclick="exportarExcelAspirantes()">📥 Exportar Excel</button>
                <button class="btn-sm" style="background:#7c3aed;color:white;border:none;" onclick="abrirModalImportar()">📂 Importar CSV</button>
                <button class="btn-sm btn-primary" onclick="abrirModalAspirante()">+ Nuevo Aspirante</button>
            </div>
        </div>
        <div class="card">
            <!-- Filtros: onchange llama a cargarAspirantes(1) para reiniciar en página 1 -->
            <div class="filters-row">
                <select id="asp-filtro-etapa" onchange="cargarAspirantes(1)">
                    <option value="">Todas las etapas</option>
                    <option value="Contacto">Contacto</option>
                    <option value="Interesado">Interesado</option>
                    <option value="Inscrito">Inscrito</option>
                    <option value="No Interesado">No Interesado</option>
                </select>
                <select id="asp-filtro-carrera" onchange="cargarAspirantes(1)">
                    <option value="">Todas las carreras</option>
                    <?php foreach ($carreras as $c): ?>
                        <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="asp-filtro-origen" onchange="cargarAspirantes(1)">
                    <option value="">Todos los orígenes</option>
                    <option value="Web">🌐 Web</option>
                    <option value="Redes sociales">📱 Redes sociales</option>
                    <option value="Feria">🏫 Feria</option>
                    <option value="Referido">🤝 Referido</option>
                    <option value="Llamada">📞 Llamada</option>
                    <option value="Otro">🔹 Otro</option>
                </select>
                <input type="text" id="asp-busqueda" placeholder="🔍 Buscar nombre o email..."
                       oninput="debounceAsp()">
            </div>
            <div class="table-wrap">
                <table id="asp-tabla">
                    <thead><tr><th style="width:32px"><input type="checkbox" class="chk-all" id="chk-all" onchange="toggleSelAll(this)"></th><th>Nombre / Email</th><th>Teléfono</th><th>Carrera</th><th>Origen</th><th>Etapa</th><th>Registrado</th><th>Acciones</th></tr></thead>
                    <tbody id="asp-tbody">
                        <tr><td colspan="8" class="empty-table" style="padding:30px;">⏳ Cargando aspirantes...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Barra de selección masiva -->
            <div class="bulk-bar" id="bulk-bar">
    <span id="bulk-count">0 seleccionados</span>
    <button class="bulk-btn" style="background:#28a745" onclick="bulkAccion('cambiar_etapa')">🔄 Cambiar etapa</button>
    <button class="bulk-btn" style="background:#0077cc" onclick="bulkAccion('agregar_nota')">📝 Agregar nota</button>
    <button class="bulk-btn" style="background:#7c3aed" onclick="bulkAccion('carrera_interes')">🎓 Carrera de interés</button>
    <button class="bulk-btn" style="background:#1ebe57" onclick="abrirEnvioMasivoWA()">💬 Enviar WhatsApp masivo</button>
    <button class="bulk-btn" style="background:#e07b00" onclick="exportarSeleccion()">📥 Exportar selección</button>
    <button class="bulk-btn" style="background:#e24b4a" onclick="bulkAccion('eliminar')">🗑️ Eliminar</button>
    <button class="bulk-btn" style="background:rgba(255,255,255,.2)" onclick="limpiarSeleccion()">✕</button>
</div>
            <!-- Paginación dinámica generada por JS -->
            <div id="asp-paginacion" style="display:none;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border);background:var(--gray-bg);">
                <span id="asp-paginacion-info" style="font-size:13px;color:var(--text-light);"></span>
                <div id="asp-paginacion-btns" style="display:flex;gap:6px;align-items:center;"></div>
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
            <div class="agenda-view-toggle">
                <button class="view-btn active" onclick="switchAgendaView('tabla',this)">📋 Lista</button>
                <button class="view-btn" onclick="switchAgendaView('calendario',this)">📅 Calendario</button>
            </div>
            <div id="agenda-tabla-wrap">
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
            </div><!-- /agenda-tabla-wrap -->
            <div id="fc-container" style="display:none"></div>
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

        <!-- Cabecera visible solo al imprimir -->
        <div id="print-header" style="margin-bottom:18px;">
            <h1 style="font-size:20px;margin:0 0 4px;">CRM Universitario — Reporte General</h1>
            <p style="font-size:12px;color:#666;margin:0;">Generado el <span id="print-fecha"></span> · <?= htmlspecialchars($usuario_nombre) ?></p>
            <hr style="margin:10px 0;">
        </div>

        <div class="section-header">
            <h2>📊 Reportes del Sistema</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button class="btn-sm btn-success" onclick="exportarExcelReportes()">📥 Exportar Excel</button>
                <button class="btn-sm" style="background:#fff0e8;color:#c2410c;border:1px solid #fdba74;" onclick="imprimirPDF()">🖨️ Imprimir / PDF</button>
                <button class="btn-sm btn-primary" onclick="cargarReportes()">🔄 Actualizar</button>
            </div>
        </div>

        <div id="reportes-contenido"><div class="loading-spinner">⏳ Cargando datos...</div></div>

        <!-- Pie de página visible solo al imprimir -->
        <div id="print-footer">
            CRM Universitario &copy; <?= date('Y') ?> · <?= htmlspecialchars($usuario_nombre) ?> · Página generada el <span id="print-fecha-footer"></span>
        </div>
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

            <!-- Mensajes de WhatsApp por etapa -->
            <div class="card" id="card-wa-config">
                <div class="card-header"><h3>💬 Mensajes de WhatsApp por Etapa</h3></div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:12px;">Personaliza el texto que se envía al pulsar el botón WhatsApp según la etapa del aspirante. Usa <code>{nombre}</code> para insertar el nombre automáticamente.</p>
                    <div style="display:flex;align-items:center;gap:8px;background:#eef6ff;border:1px solid #bfdbfe;border-radius:8px;padding:9px 13px;margin-bottom:16px;font-size:12px;color:#1e40af;">
                        🌐 <span>Estos mensajes se guardan en el servidor y son <strong>compartidos entre todos los administradores</strong>, sin importar la computadora que usen.</span>
                    </div>
                    <div class="form-group">
                        <label>🟠 Etapa: Contacto</label>
                        <textarea id="wa-msg-contacto" rows="3">Hola {nombre}, te contactamos desde la universidad 👋 Nos gustaría darte información sobre nuestras carreras. ¿Tienes un momento?</textarea>
                    </div>
                    <div class="form-group">
                        <label>🟡 Etapa: Interesado</label>
                        <textarea id="wa-msg-interesado" rows="3">Hola {nombre}! Vimos que estás interesado en nuestra oferta educativa 🎓 Con gusto te orientamos en el proceso de admisión. ¿Cuándo podemos hablar?</textarea>
                    </div>
                    <div class="form-group">
                        <label>🟢 Etapa: Inscrito</label>
                        <textarea id="wa-msg-inscrito" rows="3">¡Hola {nombre}! Felicitaciones por tu inscripción 🎉 Aquí tienes los próximos pasos para iniciar tu proceso de bienvenida.</textarea>
                    </div>
                    <div class="form-group">
                        <label>🔴 Etapa: No Interesado</label>
                        <textarea id="wa-msg-no-interesado" rows="3">Hola {nombre}, entendemos tu decisión. Si en el futuro reconsideras o necesitas información, estamos aquí. ¡Mucho éxito! 🙌</textarea>
                    </div>
                    <div id="wa-cfg-alert" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:10px;"></div>
                    <button class="btn-guardar" onclick="guardarMensajesWA()">💾 Guardar mensajes</button>
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

    <!-- ============ SECCIÓN: PAPELERA ============ -->
    <div id="sec-papelera" class="section">
        <div class="section-header">
            <h2>🗑️ Papelera de Aspirantes</h2>
            <span style="font-size:13px;color:var(--text-light)">Los aspirantes eliminados se pueden restaurar o borrar permanentemente.</span>
        </div>
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nombre</th><th>Email</th><th>Carrera</th><th>Etapa</th><th>Eliminado el</th><th>Acciones</th></tr></thead>
                    <tbody id="papelera-tbody">
                        <tr><td colspan="6" class="empty-table">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<!-- Modal Importar CSV -->
<div class="modal-overlay" id="modal-importar">
    <div class="modal" style="width:660px;max-height:85vh;overflow-y:auto;">
        <h3>📂 Importar Aspirantes desde CSV</h3>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">
            El archivo debe tener encabezados en la primera fila. Columnas reconocidas:<br>
            <code>nombre</code>, <code>email</code>, <code>telefono</code>, <code>carrera</code>, <code>etapa</code>, <code>origen</code>, <code>notas</code>
        </p>
        <div class="form-group">
            <label>Selecciona archivo CSV</label>
            <input type="file" id="imp-archivo" accept=".csv" onchange="previsualizarCSV(this)">
        </div>
        <div id="imp-preview" style="display:none;margin:10px 0;">
            <div style="font-size:13px;font-weight:700;margin-bottom:8px;">Vista previa (<span id="imp-count">0</span> filas)</div>
            <div class="table-wrap" style="max-height:200px;overflow-y:auto;">
                <table><thead id="imp-thead"></thead><tbody id="imp-tbody"></tbody></table>
            </div>
        </div>
        <div id="imp-resultado" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;margin:10px 0;"></div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px;" id="imp-btn" onclick="ejecutarImportacion()" disabled>⬆️ Importar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-importar')">Cerrar</button>
        </div>
    </div>
</div>

<!-- ============ MODALES ============ -->

<!-- Modal: Aspirante (crear/editar) -->
<div class="modal-overlay" id="modal-aspirante">
    <div class="modal" style="width:500px;">
        <h3 id="modal-asp-title">📝 Nuevo Aspirante</h3>
        <input type="hidden" id="asp-id">
        <input type="hidden" id="asp-etapa-actual" value="Contacto">
        <div class="form-row">
            <div class="form-group"><label>Nombre completo *</label><input type="text" id="asp-nombre" placeholder="Nombre completo"></div>
            <div class="form-group"><label>Email *</label><input type="email" id="asp-email" placeholder="correo@ejemplo.com"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Teléfono</label><input type="text" id="asp-telefono" placeholder="555 000 0000"></div>
            
        </div>
        <div class="form-row">
            <div class="form-group"><label>Origen / Canal</label>
                <select id="asp-origen">
                    <option value="Otro">🔹 Otro</option>
                    <option value="Web">🌐 Web</option>
                    <option value="Redes sociales">📱 Redes sociales</option>
                    <option value="Feria">🏫 Feria</option>
                    <option value="Referido">🤝 Referido</option>
                    <option value="Llamada">📞 Llamada</option>
                </select>
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
            <select id="hist-aspirante"><option value="">Cargando...</option></select>
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
            <select id="ag-aspirante"><option value="">— Sin aspirante —</option></select>
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
// Responsive sidebar toggle
// ============================================================
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}
function cerrarSidebar() {
    document.querySelector('.sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

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

    // Cerrar sidebar en móvil al navegar
    if (window.innerWidth <= 768) cerrarSidebar();

    const el = document.getElementById('sec-' + sec);
    if (el) el.classList.add('active');

    const nav = document.querySelector(`.nav-item[data-seccion="${sec}"]`);
    if (nav) nav.classList.add('active');

    if (titulos[sec]) {
        document.getElementById('topbar-title').textContent = titulos[sec][0];
        document.getElementById('topbar-sub').textContent   = titulos[sec][1];
    }

    if (sec === 'reportes')    cargarReportes();
    if (sec === 'aspirantes')  cargarAspirantes(_aspPaginaActual);
    if (sec === 'papelera')    cargarPapelera();
    if (sec === 'configuracion') cargarWAMensajesEnForm();
    if (sec === 'dashboard')   cargarDashboard(1);
}

function irAspirantes(etapa) {
    mostrarSeccion('aspirantes');
    document.getElementById('asp-filtro-etapa').value = etapa;
    cargarAspirantes(1);
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
// ============================================================
// Tabla Dashboard — carga AJAX (sin recargar página)
// ============================================================
let _dashPaginaActual = 1;
let _dashDebounce = null;

async function cargarDashboard(pagina = 1) {
    _dashPaginaActual = pagina;
    const etapa   = document.getElementById('dash-filtro-etapa').value;
    const carrera = document.getElementById('dash-filtro-carrera').value;
    const busq    = document.getElementById('dash-busqueda').value.trim();

    const tbody = document.getElementById('dash-tbody');
    const pag   = document.getElementById('dash-paginacion');
    tbody.innerHTML = '<tr><td colspan="5" class="empty-table" style="padding:28px;">⏳ Cargando...</td></tr>';
    pag.style.display = 'none';

    const params = new URLSearchParams({ accion: 'listar', pagina, porPagina: 10 });
    if (etapa)   params.set('etapa',    etapa);
    if (carrera) params.set('carrera',  carrera);
    if (busq)    params.set('busqueda', busq);

    try {
        const resp = await fetch('assets/api/aspirantes_api.php?' + params);
        const data = await resp.json();
        if (!data.ok || !data.aspirantes.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-table" style="padding:28px;">Sin resultados.</td></tr>';
            return;
        }
        tbody.innerHTML = data.aspirantes.map(a => `
            <tr>
                <td style="cursor:pointer" onclick="abrirQuickView(${a.id_aspirante})" title="Ver detalle">
                    <strong style="color:var(--blue);text-decoration:underline dotted">${_esc(a.nombre)}</strong>
                </td>
                <td class="td-email">${_esc(a.email)}</td>
                <td class="td-carrera">${_esc(a.carrera || '—')}</td>
                <td>
                    <span class="etapa-badge stage-pill"
                          style="background:${_colorEtapa[a.etapa]||'#666'};cursor:pointer;"
                          data-id="${a.id_aspirante}" data-etapa="${_esc(a.etapa)}"
                          onclick="toggleMenuEtapa(event, this)">${_esc(a.etapa)}</span>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-edit" onclick="editarAspirante(${a.id_aspirante})">✏️</button>
                        <button class="btn-del"  onclick="confirmarEliminar(${a.id_aspirante},'${a.nombre.replace(/'/g,"\\'")}','aspirante')">🗑️</button>
                    </div>
                </td>
            </tr>`).join('');

        // Paginación dashboard
        const totalPag = data.totalPaginas || 1;
        const total    = data.total || 0;
        if (totalPag > 1) {
            const desde = (pagina - 1) * 10 + 1;
            const hasta = Math.min(pagina * 10, total);
            let html = `<span style="font-size:12px;color:var(--text-light);">${desde}–${hasta} de ${total}</span><div style="display:flex;gap:5px;">`;
            if (pagina > 1) html += `<button class="btn-sm btn-primary" style="padding:5px 10px" onclick="cargarDashboard(${pagina-1})">‹</button>`;
            const ini = Math.max(1, pagina-1), fin = Math.min(totalPag, pagina+1);
            for (let p = ini; p <= fin; p++) {
                html += `<button class="btn-sm ${p===pagina?'btn-primary':''}" style="padding:5px 10px;${p===pagina?'':'background:var(--white);border:1.5px solid var(--border);color:var(--text);'}" onclick="cargarDashboard(${p})">${p}</button>`;
            }
            if (pagina < totalPag) html += `<button class="btn-sm btn-primary" style="padding:5px 10px" onclick="cargarDashboard(${pagina+1})">›</button>`;
            html += '</div>';
            pag.innerHTML = html;
            pag.style.display = 'flex';
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-table">Error de conexión.</td></tr>';
    }
}

function debounceDash() {
    clearTimeout(_dashDebounce);
    _dashDebounce = setTimeout(() => cargarDashboard(1), 350);
}

function _esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
// ============================================================
// Carga AJAX de aspirantes — filtros + paginación server-side
// ============================================================
let _aspPaginaActual = 1;
let _aspDebounce = null;

async function cargarAspirantes(pagina = 1) {
    _aspPaginaActual = pagina;
    const etapa   = document.getElementById('asp-filtro-etapa').value;
    const carrera = document.getElementById('asp-filtro-carrera').value;
    const busq    = document.getElementById('asp-busqueda').value.trim();

    const tbody = document.getElementById('asp-tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="empty-table" style="padding:24px;">⏳ Cargando...</td></tr>';
    document.getElementById('asp-paginacion').style.display = 'none';

    const origen  = document.getElementById('asp-filtro-origen').value;
    const params = new URLSearchParams({ accion: 'listar', pagina });
    if (etapa)   params.set('etapa',    etapa);
    if (carrera) params.set('carrera',  carrera);
    if (origen)  params.set('origen',   origen);
    if (busq)    params.set('busqueda', busq);

    try {
        const resp = await fetch('assets/api/aspirantes_api.php?' + params);
        const data = await resp.json();
        if (!data.ok) { tbody.innerHTML = '<tr><td colspan="7" class="empty-table">Error al cargar datos.</td></tr>'; return; }

        renderTablaAspirantes(data.aspirantes);
        renderPaginacionAspirantes(data);
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-table">Error de conexión.</td></tr>';
    }
}

function debounceAsp() {
    clearTimeout(_aspDebounce);
    _aspDebounce = setTimeout(() => cargarAspirantes(1), 350);
}

const _colorEtapa = {
    'Contacto':      '#0077cc',
    'Interesado':    '#e07b00',
    'Inscrito':      '#28a745',
    'No Interesado': '#c0392b',
};

// ── Plantillas WhatsApp por etapa ────────────────────────────
// ============================================================
// CLAVE DE ALMACENAMIENTO POR USUARIO
// Cada administrador tiene su propia clave para evitar
// que los datos se mezclen en equipos compartidos.
// ============================================================
const _uid = (() => {
    const meta = document.querySelector('meta[name="crm-uid"]');
    return meta ? meta.getAttribute('content') : '0';
})();
const _storageKey = (base) => `${base}_u${_uid}`;

// ============================================================
// MENSAJES WHATSAPP — guardados en BD (config_api.php)
// Se comparten entre todos los administradores y computadoras.
// ============================================================
const _waMsgDefaults = {
    'Contacto':      'Hola {nombre}, te contactamos desde la universidad 👋 Nos gustaría darte información sobre nuestras carreras. ¿Tienes un momento?',
    'Interesado':    'Hola {nombre}! Vimos que estás interesado en nuestra oferta educativa 🎓 Con gusto te orientamos en el proceso de admisión. ¿Cuándo podemos hablar?',
    'Inscrito':      '¡Hola {nombre}! Felicitaciones por tu inscripción 🎉 Aquí tienes los próximos pasos para iniciar tu proceso de bienvenida.',
    'No Interesado': 'Hola {nombre}, entendemos tu decisión. Si en el futuro reconsideras o necesitas información, estamos aquí. ¡Mucho éxito! 🙌',
};

// Mensajes en memoria — se llenan al cargar la sección configuración
// y se usan en toda la app para armar el link de WhatsApp.
const _waMensaje = {
    'Contacto':      n => _waMsgDefaults['Contacto'].replace(/\{nombre\}/g, n),
    'Interesado':    n => _waMsgDefaults['Interesado'].replace(/\{nombre\}/g, n),
    'Inscrito':      n => _waMsgDefaults['Inscrito'].replace(/\{nombre\}/g, n),
    'No Interesado': n => _waMsgDefaults['No Interesado'].replace(/\{nombre\}/g, n),
};

// Mapa entre etapa y clave de BD
const _waClaveDB = {
    'Contacto':      'wa_msg_contacto',
    'Interesado':    'wa_msg_interesado',
    'Inscrito':      'wa_msg_inscrito',
    'No Interesado': 'wa_msg_no_interesado',
};

// Carga mensajes desde BD y actualiza _waMensaje + los textareas del formulario
async function cargarWAMensajesEnForm() {
    try {
        const resp = await fetch('assets/api/config_api.php');
        const data = await resp.json();
        if (!data.ok) return;

        const cfg = data.config || {};
        const dbToEtapa = {};
        for (const [etapa, clave] of Object.entries(_waClaveDB)) dbToEtapa[clave] = etapa;

        for (const [clave, valor] of Object.entries(cfg)) {
            if (!valor) continue;
            const etapa = dbToEtapa[clave];
            if (!etapa) continue;
            // Actualizar función en memoria
            _waMensaje[etapa] = n => valor.replace(/\{nombre\}/g, n);
            // Poner en el textarea si la sección está abierta
            const idMap = {
                'Contacto':'wa-msg-contacto','Interesado':'wa-msg-interesado',
                'Inscrito':'wa-msg-inscrito','No Interesado':'wa-msg-no-interesado'
            };
            const el = document.getElementById(idMap[etapa]);
            if (el) el.value = valor;
        }
    } catch(e) {
        console.warn('No se pudieron cargar los mensajes WA desde BD:', e);
    }
}

// Guarda mensajes en BD vía config_api.php
async function guardarMensajesWA() {
    const al = document.getElementById('wa-cfg-alert');
    const mostrarAlerta = (msg, tipo = 'ok') => {
        al.style.display = 'block';
        al.style.background = tipo === 'ok' ? '#e8f5e9' : '#fff0f0';
        al.style.color      = tipo === 'ok' ? '#1e7e3a' : '#c0392b';
        al.textContent = msg;
        setTimeout(() => { al.style.display = 'none'; }, 3500);
    };

    const config = {
        'wa_msg_contacto':      document.getElementById('wa-msg-contacto').value.trim(),
        'wa_msg_interesado':    document.getElementById('wa-msg-interesado').value.trim(),
        'wa_msg_inscrito':      document.getElementById('wa-msg-inscrito').value.trim(),
        'wa_msg_no_interesado': document.getElementById('wa-msg-no-interesado').value.trim(),
    };

    // Validar que ninguno esté vacío
    for (const [clave, val] of Object.entries(config)) {
        if (!val) { mostrarAlerta('⚠️ Ningún mensaje puede estar vacío.', 'error'); return; }
    }

    try {
        const resp = await fetch('assets/api/config_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ config }),
        });
        const data = await resp.json();
        if (data.ok) {
            // Actualizar funciones en memoria
            const dbToEtapa = {};
            for (const [etapa, clave] of Object.entries(_waClaveDB)) dbToEtapa[clave] = etapa;
            for (const [clave, valor] of Object.entries(config)) {
                const etapa = dbToEtapa[clave];
                if (etapa) _waMensaje[etapa] = n => valor.replace(/\{nombre\}/g, n);
            }
            mostrarAlerta('✅ Mensajes guardados y sincronizados en el servidor.');
            toast('✅ Mensajes WhatsApp guardados');
        } else {
            mostrarAlerta('⚠️ ' + (data.mensaje || 'Error al guardar.'), 'error');
        }
    } catch(e) {
        mostrarAlerta('❌ Error de conexión al guardar.', 'error');
    }
}

function _limpiarTel(tel) {
    if (!tel) return null;
    let t = String(tel).replace(/[\s\-\.\(\)]/g, '');
    if (/^\d{10}$/.test(t)) t = '52' + t;
    if (/^0\d{10}$/.test(t)) t = '52' + t.slice(1);
    return t.replace(/[^\d]/g, '') || null;
}

function renderTablaAspirantes(aspirantes) {
    const tbody = document.getElementById('asp-tbody');
    if (!aspirantes || aspirantes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-table">No se encontraron aspirantes con los filtros aplicados.</td></tr>';
        return;
    }
    tbody.innerHTML = aspirantes.map(a => {
        const color  = _colorEtapa[a.etapa] || '#888';
        const fecha  = new Date(a.creado_en).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });
        const origenIcon = {Web:'🌐',Redes_sociales:'📱',Feria:'🏫',Referido:'🤝',Llamada:'📞',Otro:'🔹'};
        const orKey  = (a.origen||'Otro').replace(' ','_');
        const tel    = _limpiarTel(a.telefono);
        const msgWA  = encodeURIComponent((_waMensaje[a.etapa]||_waMensaje['Contacto'])(a.nombre));
        const mailSub= encodeURIComponent('Información — ' + a.nombre);
        const nameEsc= esc(a.nombre).replace(/'/g,"\\'");

        const btnWA   = tel
            ? `<a href="https://wa.me/${tel}?text=${msgWA}" target="_blank" class="btn-wa" title="WhatsApp (mensaje adaptado a la etapa)" onclick="logContacto(${a.id_aspirante},'llamada')">💬</a>`
            : `<span class="no-tel" title="Sin teléfono — no se puede abrir WhatsApp">💬</span>`;
        const btnCall = tel
            ? `<a href="tel:+${tel}" class="btn-call" title="Llamar a ${esc(a.nombre)}" onclick="logContacto(${a.id_aspirante},'llamada')">📞</a>`
            : `<span class="no-tel" title="Sin teléfono registrado">📞</span>`;
        const btnMail = `<a href="mailto:${esc(a.email)}?subject=${mailSub}" class="btn-mail" title="Enviar email a ${esc(a.email)}" onclick="logContacto(${a.id_aspirante},'correo')">✉️</a>`;

        return `<tr data-etapa="${a.etapa}" data-carrera="${a.id_carrera||''}">
            <td style="width:32px"><input type="checkbox" class="chk-row" value="${a.id_aspirante}" onchange="actualizarBulkBar()"></td>
            <td style="cursor:pointer" onclick="abrirQuickView(${a.id_aspirante})">
                <div style="font-weight:600;font-size:13px;color:var(--primary)">${esc(a.nombre)}</div>
                <div style="font-size:11px;color:var(--text-light)">${esc(a.email)}</div>
            </td>
            <td style="font-size:12px">${tel ? esc(a.telefono||'') : '<span style="color:var(--text-light)">—</span>'}</td>
            <td style="font-size:12px">${esc(a.carrera||'—')}</td>
            <td style="font-size:11px">${origenIcon[orKey]||'🔹'} ${esc(a.origen||'Otro')}</td>
            <td>
                <span class="etapa-badge stage-pill"
                      style="background:${color};cursor:pointer;"
                      data-id="${a.id_aspirante}"
                      data-etapa="${a.etapa}"
                      onclick="toggleMenuEtapa(event,this)">${esc(a.etapa)}</span>
            </td>
            <td style="font-size:11px;color:var(--text-light);white-space:nowrap">${fecha}</td>
            <td>
                <div class="contact-bar">
                    ${btnWA}
                    ${btnCall}
                    ${btnMail}
                    <button class="btn-sched" onclick="agendarDesdeRow(${a.id_aspirante},'${nameEsc}')" title="Programar seguimiento">📅</button>
                    <button class="btn-edit-sm" onclick="editarAspirante(${a.id_aspirante})" title="Editar aspirante">✏️</button>
                    <button class="btn-del-sm" onclick="confirmarEliminar(${a.id_aspirante},'${nameEsc}','aspirante')" title="Mover a papelera">🗑️</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderPaginacionAspirantes(data) {
    const { total, pagina, porPagina, totalPaginas } = data;
    const wrap = document.getElementById('asp-paginacion');
    const info = document.getElementById('asp-paginacion-info');
    const btns = document.getElementById('asp-paginacion-btns');

    if (totalPaginas <= 1) { wrap.style.display = 'none'; return; }

    const desde = (pagina - 1) * porPagina + 1;
    const hasta = Math.min(pagina * porPagina, total);
    info.textContent = `Mostrando ${desde}–${hasta} de ${total} aspirantes`;

    let html = '';
    if (pagina > 1) html += `<button class="btn-sm btn-primary" onclick="cargarAspirantes(${pagina-1})">‹ Anterior</button>`;
    const ini = Math.max(1, pagina - 2);
    const fin = Math.min(totalPaginas, pagina + 2);
    for (let p = ini; p <= fin; p++) {
        const active = p === pagina;
        html += `<button class="btn-sm ${active ? 'btn-primary' : ''}"
            style="${active ? '' : 'background:white;border:1.5px solid var(--border);color:var(--text);'}min-width:34px;"
            onclick="cargarAspirantes(${p})">${p}</button>`;
    }
    if (pagina < totalPaginas) html += `<button class="btn-sm btn-primary" onclick="cargarAspirantes(${pagina+1})">Siguiente ›</button>`;
    btns.innerHTML = html;
    wrap.style.display = 'flex';
}

// ============================================================
// CRUD Aspirantes
// ============================================================
function abrirModalAspirante() {
    document.getElementById('asp-id').value       = '';
    document.getElementById('asp-nombre').value   = '';
    document.getElementById('asp-email').value    = '';
    document.getElementById('asp-telefono').value = '';
    document.getElementById('asp-carrera').value  = '';
    document.getElementById('asp-beca').value     = '';
    document.getElementById('asp-notas').value    = '';
    document.getElementById('asp-origen').value   = 'Otro';
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
    document.getElementById('asp-origen').value   = a.origen    || 'Otro';
    document.getElementById('asp-beca').value     = a.id_beca || '';
    document.getElementById('asp-etapa-actual').value = a.etapa;
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
        etapa:      id ? document.getElementById('asp-etapa-actual').value : 'Contacto',
        origen:     document.getElementById('asp-origen').value,
        id_beca:    document.getElementById('asp-beca').value,
        notas:      document.getElementById('asp-notas').value,
    };

    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    if (data.ok) {
        cerrarModal('modal-aspirante');
        toast(id ? '✅ Aspirante actualizado' : '✅ Aspirante registrado');
        cargarAspirantes(_aspPaginaActual);
        cargarDashboard(1);
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
        origen:     document.getElementById('dash-origen').value || 'Otro',
        etapa: 'Contacto',
    };
    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(datos)
    });
    const data = await resp.json();
    if (data.ok) {
        toast('✅ Aspirante registrado');
        // Limpiar form
        ['dash-nombre','dash-email','dash-telefono'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('dash-carrera').value = '';
        document.getElementById('dash-origen').value = 'Otro';
        cargarAspirantes(1);
        cargarDashboard(1); // Refrescar tabla del dashboard
    } else { toast(data.mensaje || 'Error al guardar', 'error'); }
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
    if (data.ok) {
        toast('🗑️ Eliminado correctamente');
        cargarDashboard(1);
        if (_delTipo === 'aspirante') {
            cargarAspirantes(_aspPaginaActual);
        } else {
            setTimeout(() => location.reload(), 1000);
        }
    } else { toast(data.mensaje || 'Error al eliminar', 'error'); }
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
function abrirModalAgenda(idAsp) {
    poblarSelectAspirantes('ag-aspirante', idAsp||0);
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

        const colores = { Contacto:'#0077cc', Interesado:'#e07b00', Inscrito:'#28a745', 'No Interesado':'#c0392b' };
        const iconosH = { llamada:'📞', correo:'✉️', visita:'🏢', nota:'📝' };

        cont.innerHTML = `
        <div class="stats-grid">
            <div class="stat-box"><div class="stat-num">${d.totalAspirantes}</div><div class="stat-label">Total Aspirantes</div></div>
            ${d.porEtapa.map(e => `<div class="stat-box"><div class="stat-num" style="color:${colores[e.etapa]||'var(--red)'}">${e.total}</div><div class="stat-label">${e.etapa}</div></div>`).join('')}
            <div class="stat-box"><div class="stat-num" style="color:var(--amazul)">${d.totalConBeca}</div><div class="stat-label">Con Beca Asignada</div></div>
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
        </div>

        <div class="card" style="margin-top:18px;">
            <div class="card-header"><h3>📍 Canal de Captación</h3></div>
            <div class="card-body">
                ${!d.porOrigen || d.porOrigen.length === 0 ? '<p style="color:var(--text-light);text-align:center">Sin datos de origen</p>' :
                  d.porOrigen.map(o => `<div class="bar-item">`+
                    `<div class="bar-label">${o.origen||'Otro'}</div>`+
                    `<div class="bar-track"><div class="bar-fill" style="width:${(o.total/Math.max(...d.porOrigen.map(x=>x.total),1)*100).toFixed(1)}%;background:#7c3aed"></div></div>`+
                    `<div class="bar-count">${o.total}</div></div>`).join('')}
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px;">
            <div class="card"><div class="card-header"><h3>🍩 Pipeline</h3></div>
                <div class="card-body" style="display:flex;justify-content:center;height:240px;">
                    <canvas id="chart-pipeline" style="max-width:240px;"></canvas>
                </div></div>
            <div class="card"><div class="card-header"><h3>📈 Actividad 30 días</h3></div>
                <div class="card-body" style="height:240px;position:relative;">
                    <canvas id="chart-actividad"></canvas>
                </div></div>
        </div>`;
        requestAnimationFrame(() => _renderCharts(d));
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

// ============================================================
// Menú de cambio de etapa inline
// ============================================================
const etapaOpciones = [
    { valor: 'Contacto',      color: '#0077cc', bg: '#fff3e0', texto: '#034676' },
    { valor: 'Interesado',    color: '#e07b00', bg: '#fff3e0', texto: '#7a3800' },
    { valor: 'Inscrito',      color: '#28a745', bg: '#e8f5e9', texto: '#1a5c2e' },
    { valor: 'No Interesado', color: '#c0392b', bg: '#fff0f0', texto: '#7a1a1a' },
];

let _menuActivo = null;

function toggleMenuEtapa(event, pill) {
    event.stopPropagation();
    if (_menuActivo) { _menuActivo.remove(); _menuActivo = null; return; }

    const menu = document.createElement('div');
    menu.className = 'stage-menu';
    menu.id = 'stage-floating-menu';

    etapaOpciones.forEach(op => {
        const item = document.createElement('div');
        item.className = 'stage-menu-item';
        item.innerHTML = `<span class="stage-dot" style="background:${op.color}"></span>${op.valor}`;
        item.onclick = async (e) => {
            e.stopPropagation();
            const id = pill.dataset.id;
            const resp = await fetch('assets/api/aspirantes_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'actualizar_etapa', id, etapa: op.valor })
            });
            const data = await resp.json();
            menu.remove(); _menuActivo = null;
            if (data.ok) {
                pill.textContent = op.valor;
                pill.style.background = op.color;
                pill.dataset.etapa = op.valor;
                // actualizar data-etapa en la fila para que funcionen los filtros
                pill.closest('tr').dataset.etapa = op.valor;
                toast(`✅ Etapa cambiada a "${op.valor}"`);
                // Refrescar contadores del pipeline en el dashboard
                if (typeof cargarReportes === 'function') cargarReportes();
            } else {
                toast(data.mensaje || 'Error al cambiar etapa', 'error');
            }
        };
        menu.appendChild(item);
    });

    const rect = pill.getBoundingClientRect();
    menu.style.top  = (rect.bottom + 4) + 'px';
    menu.style.left = rect.left + 'px';
    document.body.appendChild(menu);
    _menuActivo = menu;
}

document.addEventListener('click', () => {
    if (_menuActivo) { _menuActivo.remove(); _menuActivo = null; }
});
// Carga inicial: si la sección activa al inicio es aspirantes, cargar de inmediato
// En cualquier caso, mostrarSeccion() la carga cuando el usuario entra
// Reabrir sección si hay parámetro de paginación del dashboard en URL
const _params = new URLSearchParams(window.location.search);
if (_params.has('paginad')) mostrarSeccion('dashboard');

// ============================================================
// Chart.js — _renderCharts
// ============================================================
const _charts = {};
function _renderCharts(d) {
    // Destruir instancias previas para reusar canvas
    Object.keys(_charts).forEach(k => { if (_charts[k]) { _charts[k].destroy(); _charts[k]=null; } });

    // ── Donut: pipeline ─────────────────────────────────────
    const ctxP = document.getElementById('chart-pipeline');
    if (ctxP && d.porEtapa && d.porEtapa.length) {
        const colPipe = { Contacto:'#0077cc', Interesado:'#e07b00', Inscrito:'#28a745', 'No Interesado':'#c0392b' };
        _charts.pipeline = new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: d.porEtapa.map(e => e.etapa),
                datasets: [{ data: d.porEtapa.map(e => e.total),
                    backgroundColor: d.porEtapa.map(e => colPipe[e.etapa]||'#aaa'),
                    borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive:true, maintainAspectRatio:true,
                plugins: { legend:{ position:'bottom', labels:{ font:{size:11}, boxWidth:12 } } } }
        });
    }

    // ── Line: actividad diaria 30 días ───────────────────────
    const ctxA = document.getElementById('chart-actividad');
    if (ctxA && d.actividadDiaria && d.actividadDiaria.length) {
        _charts.actividad = new Chart(ctxA, {
            type: 'line',
            data: {
                labels: d.actividadDiaria.map(r => r.dia),
                datasets: [{
                    label: 'Nuevos aspirantes',
                    data: d.actividadDiaria.map(r => r.nuevos),
                    borderColor: '#003d99', backgroundColor: 'rgba(0,61,153,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3
                }]
            },
            options: { responsive:true, maintainAspectRatio:false,
                scales: {
                    x: { ticks:{ font:{size:10}, maxRotation:45 } },
                    y: { beginAtZero:true, ticks:{ stepSize:1, font:{size:10} } }
                },
                plugins: { legend:{ display:false } }
            }
        });
    }
}

// ============================================================
// Exportar Excel — Aspirantes (TODOS via API, respeta filtros)
// ============================================================
async function exportarExcelAspirantes() {
    toast('⏳ Generando Excel de aspirantes...');
    try {
        const etapa   = document.getElementById('asp-filtro-etapa').value;
        const carrera = document.getElementById('asp-filtro-carrera').value;
        const origen  = document.getElementById('asp-filtro-origen').value;
        const busq    = document.getElementById('asp-busqueda').value.trim();

        const params = new URLSearchParams({ accion: 'exportar' });
        if (etapa)   params.set('etapa',    etapa);
        if (carrera) params.set('carrera',  carrera);
        if (origen)  params.set('origen',   origen);
        if (busq)    params.set('busqueda', busq);

        const resp = await fetch('assets/api/aspirantes_api.php?' + params);
        const data = await resp.json();
        if (!data.ok || !data.aspirantes.length) {
            toast('No hay aspirantes para exportar', 'error'); return;
        }

        const headers = ['Nombre', 'Email', 'Teléfono', 'Carrera', 'Beca', 'Etapa', 'Descuento %', 'Notas', 'Registrado'];
        const filas = [
            headers,
            ...data.aspirantes.map(a => [
                a.nombre,
                a.email,
                a.telefono || '',
                a.carrera  || '',
                a.beca     || '',
                a.etapa,
                parseFloat(a.descuento_aplicado) || 0,
                a.notas    || '',
                a.creado_en ? a.creado_en.split(' ')[0] : '',
            ])
        ];

        const ws = XLSX.utils.aoa_to_sheet(filas);
        ws['!cols'] = headers.map((_, i) => ({
            wch: Math.max(...filas.map(r => (r[i] ?? '').toString().length), 10)
        }));

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Aspirantes');
        XLSX.writeFile(wb, `aspirantes_${new Date().toISOString().slice(0,10)}.xlsx`);
        toast(`📥 Excel generado: ${data.aspirantes.length} registros`);
    } catch(e) {
        toast('Error al generar Excel: ' + e.message, 'error');
    }
}

// ============================================================
// Exportar Excel — Reportes
// ============================================================
// ============================================================
// Imprimir / Exportar PDF del reporte
// ============================================================
async function imprimirPDF() {
    // Si el reporte aún no se ha cargado, cargarlo primero
    const cont = document.getElementById('reportes-contenido');
    if (cont.querySelector('.loading-spinner')) {
        toast('⏳ Cargando reporte antes de imprimir...');
        await cargarReportes();
        // Esperar a que los canvas de charts terminen de renderizar
        await new Promise(r => setTimeout(r, 600));
    }
    // Poner fecha actual en los spans del encabezado/pie
    const now = new Date().toLocaleDateString('es-MX', { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' });
    document.querySelectorAll('#print-fecha, #print-fecha-footer').forEach(el => el.textContent = now);
    window.print();
}

async function exportarExcelReportes() {
    toast('⏳ Generando Excel de reportes...');
    try {
        const resp = await fetch('assets/api/reportes_api.php');
        const d    = await resp.json();
        if (!d.ok) { toast('Error al obtener datos', 'error'); return; }

        const wb = XLSX.utils.book_new();

        // Hoja 1: Pipeline por etapa
        const wsEtapa = XLSX.utils.aoa_to_sheet([
            ['Etapa', 'Total'],
            ...d.porEtapa.map(r => [r.etapa, r.total]),
            [],
            ['Total Aspirantes', d.totalAspirantes],
            ['Con Beca Asignada', d.totalConBeca],
        ]);
        wsEtapa['!cols'] = [{ wch: 20 }, { wch: 10 }];
        XLSX.utils.book_append_sheet(wb, wsEtapa, 'Pipeline Etapas');

        // Hoja 2: Por carrera
        const wsCarrera = XLSX.utils.aoa_to_sheet([
            ['Carrera', 'Total Aspirantes'],
            ...d.porCarrera.map(r => [r.carrera, r.total]),
        ]);
        wsCarrera['!cols'] = [{ wch: 35 }, { wch: 18 }];
        XLSX.utils.book_append_sheet(wb, wsCarrera, 'Por Carrera');

        // Hoja 3: Becas asignadas
        if (d.becasAsignadas && d.becasAsignadas.length > 0) {
            const wsBecas = XLSX.utils.aoa_to_sheet([
                ['Beca', 'Aspirantes Asignados', 'Descuento %'],
                ...d.becasAsignadas.map(b => [b.beca, b.asignadas, b.descuento + '%']),
            ]);
            wsBecas['!cols'] = [{ wch: 30 }, { wch: 22 }, { wch: 14 }];
            XLSX.utils.book_append_sheet(wb, wsBecas, 'Becas Asignadas');
        }

        XLSX.writeFile(wb, `reporte_crm_${new Date().toISOString().slice(0,10)}.xlsx`);
        toast('📥 Reporte Excel generado');
    } catch(e) {
        toast('Error al generar Excel: ' + e.message, 'error');
    }
}

// ============================================================
// Papelera
// ============================================================
async function cargarPapelera() {
    const tbody = document.getElementById('papelera-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="empty-table">⏳ Cargando...</td></tr>';
    const resp = await fetch('assets/api/aspirantes_api.php?accion=papelera');
    const data = await resp.json();
    if (!data.ok || !data.eliminados.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-table">✅ La papelera está vacía.</td></tr>';
        return;
    }
    tbody.innerHTML = data.eliminados.map(a => {
        const fechaEl = new Date(a.eliminado_en).toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'});
        const colPipe = {Contacto:'#0077cc',Interesado:'#e07b00',Inscrito:'#28a745','No Interesado':'#c0392b'};
        return `<tr>
            <td><strong>${esc(a.nombre)}</strong></td>
            <td>${esc(a.email)}</td>
            <td>${esc(a.carrera||'—')}</td>
            <td><span class="etapa-badge" style="background:${colPipe[a.etapa]||'#aaa'}">${esc(a.etapa)}</span></td>
            <td style="font-size:12px;color:var(--text-light)">${fechaEl}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-edit" onclick="restaurarAspirante(${a.id_aspirante},'${esc(a.nombre).replace(/'/g,"\'")}')">↩️ Restaurar</button>
                    <button class="btn-del" onclick="eliminarDefinitivo(${a.id_aspirante},'${esc(a.nombre).replace(/'/g,"\'")}')">🗑️ Borrar</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function restaurarAspirante(id, nombre) {
    if (!confirm(`¿Restaurar a "${nombre}" de la papelera?`)) return;
    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'restaurar', id})
    });
    const data = await resp.json();
    if (data.ok) { toast('↩️ Aspirante restaurado'); cargarPapelera(); }
    else toast(data.mensaje||'Error', 'error');
}

async function eliminarDefinitivo(id, nombre) {
    if (!confirm(`⚠️ BORRADO PERMANENTE\n"${nombre}" se eliminará para siempre y no se podrá recuperar.\n\n¿Confirmas?`)) return;
    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'eliminar_definitivo', id})
    });
    const data = await resp.json();
    if (data.ok) { toast('🗑️ Eliminado permanentemente'); cargarPapelera(); }
    else toast(data.mensaje||'Error', 'error');
}

// ============================================================
// Importación masiva CSV
// ============================================================
let _csvFilas = [];

function abrirModalImportar() {
    _csvFilas = [];
    document.getElementById('imp-archivo').value = '';
    document.getElementById('imp-preview').style.display = 'none';
    document.getElementById('imp-resultado').style.display = 'none';
    document.getElementById('imp-btn').disabled = true;
    abrirModal('modal-importar');
}

function previsualizarCSV(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const text = e.target.result;
        const lineas = text.split(/\r?\n/).filter(l => l.trim());
        if (lineas.length < 2) { toast('El archivo no tiene datos suficientes', 'error'); return; }

        const sep = lineas[0].includes(';') ? ';' : ',';
        const headers = lineas[0].split(sep).map(h => h.trim().toLowerCase().replace(/['"]/g,''));
        const filas = [];

        for (let i = 1; i < lineas.length; i++) {
            const cols = lineas[i].split(sep).map(c => c.trim().replace(/^"|"$/g,''));
            if (cols.every(c => !c)) continue;
            const fila = {};
            headers.forEach((h, j) => fila[h] = cols[j] || '');
            // Mapear variantes de nombre de columna
            const row = {
                nombre:        fila.nombre || fila.name || '',
                email:         fila.email  || fila.correo || '',
                telefono:      fila.telefono || fila.phone || fila.tel || '',
                carrera_nombre:fila.carrera || fila.carrera_nombre || '',
                etapa:         fila.etapa  || 'Contacto',
                origen:        fila.origen || 'Otro',
                notas:         fila.notas  || fila.observaciones || '',
            };
            filas.push(row);
        }

        _csvFilas = filas;
        document.getElementById('imp-count').textContent = filas.length;

        // Encabezados preview
        document.getElementById('imp-thead').innerHTML =
            '<tr><th>#</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Carrera</th><th>Etapa</th><th>Origen</th></tr>';

        // Primeras 5 filas
        document.getElementById('imp-tbody').innerHTML = filas.slice(0,5).map((f,i) =>
            `<tr><td>${i+1}</td><td>${esc(f.nombre)}</td><td>${esc(f.email)}</td><td>${esc(f.telefono)}</td><td>${esc(f.carrera_nombre)}</td><td>${esc(f.etapa)}</td><td>${esc(f.origen)}</td></tr>`
        ).join('') + (filas.length > 5 ? `<tr><td colspan="7" style="text-align:center;color:var(--text-light);font-size:12px">… y ${filas.length-5} más</td></tr>` : '');

        document.getElementById('imp-preview').style.display = 'block';
        document.getElementById('imp-resultado').style.display = 'none';
        document.getElementById('imp-btn').disabled = filas.length === 0;
    };
    reader.readAsText(file, 'UTF-8');
}

async function ejecutarImportacion() {
    if (!_csvFilas.length) return;
    const btn = document.getElementById('imp-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Importando...';
    const res = document.getElementById('imp-resultado');

    try {
        const resp = await fetch('assets/api/aspirantes_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({accion:'importar', filas: _csvFilas})
        });
        const data = await resp.json();
        const color = data.fallidos?.length ? '#fff8e1' : '#e8f5e9';
        const border = data.fallidos?.length ? '#f59e0b' : '#28a745';
        let html = `<strong>✅ ${data.exitosos} importados correctamente</strong>`;
        if (data.fallidos?.length) {
            html += `<br><span style="color:#c0392b">⚠️ ${data.fallidos.length} con errores:</span><ul style="margin:6px 0 0 16px;font-size:12px">`;
            html += data.fallidos.map(f => `<li>Fila ${f.fila}: <b>${esc(f.nombre||'?')}</b> — ${esc(f.error)}</li>`).join('');
            html += '</ul>';
        }
        res.innerHTML = html;
        res.style.cssText = `display:block;background:${color};border:1px solid ${border};border-radius:8px;padding:12px 14px;font-size:13px`;
        if (data.exitosos > 0) cargarAspirantes(1);
        toast(`📂 Importación completa: ${data.exitosos} registros`);
    } catch(err) {
        res.innerHTML = '❌ Error de conexión: ' + err.message;
        res.style.cssText = 'display:block;background:#fff0f0;border:1px solid #e74c3c;border-radius:8px;padding:12px 14px;font-size:13px';
    }
    btn.disabled = false;
    btn.textContent = '⬆️ Importar';
}

// ============================================================
// Configuración de correo SMTP
// ============================================================
async function cargarMailConfig() {
    try {
        const resp = await fetch('assets/api/notificaciones_api.php?accion=config');
        const data = await resp.json();
        if (!data.ok) return;
        const c = data.config || {};
        document.getElementById('mail-host').value    = c.smtp_host    || '';
        document.getElementById('mail-port').value    = c.smtp_port    || '587';
        document.getElementById('mail-usuario').value = c.smtp_usuario || '';
        document.getElementById('mail-pass').value    = c.smtp_pass    || '';
        document.getElementById('mail-from').value    = c.from_email   || '';
        document.getElementById('mail-nombre').value  = c.from_name    || 'CRM Universitario';
    } catch(e) { console.warn('Mail config no disponible'); }
}

async function guardarMailConfig() {
    const datos = {
        accion:       'guardar_config',
        smtp_host:    document.getElementById('mail-host').value,
        smtp_port:    document.getElementById('mail-port').value,
        smtp_usuario: document.getElementById('mail-usuario').value,
        smtp_pass:    document.getElementById('mail-pass').value,
        from_email:   document.getElementById('mail-from').value,
        from_name:    document.getElementById('mail-nombre').value,
    };
    const alertEl = document.getElementById('mail-cfg-alert');
    try {
        const resp = await fetch('assets/api/notificaciones_api.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify(datos)
        });
        const data = await resp.json();
        alertEl.textContent = data.mensaje;
        alertEl.style.cssText = `display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:${data.ok?'#e8f5e9':'#fff0f0'}`;
        setTimeout(() => alertEl.style.display = 'none', 3500);
    } catch(e) { toast('Error al guardar configuración', 'error'); }
}

async function enviarCorreoPrueba() {
    const email = document.getElementById('mail-prueba').value.trim();
    if (!email) { toast('Ingresa un email de destino', 'error'); return; }
    toast('⏳ Enviando correo de prueba...');
    const resp = await fetch('assets/api/notificaciones_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'enviar_prueba', email_prueba: email})
    });
    const data = await resp.json();
    toast(data.mensaje, data.ok ? 'success' : 'error');
}

async function enviarRecordatorios() {
    const dest = document.getElementById('mail-recordatorio-dest').value.trim();
    if (!dest) { toast('Ingresa el email de destino', 'error'); return; }
    toast('⏳ Enviando recordatorios...');
    const resp = await fetch('assets/api/notificaciones_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'enviar_recordatorios', email_destino: dest})
    });
    const data = await resp.json();
    toast(data.mensaje, data.ok ? 'success' : 'error');
}


// ============================================================
// Log automático de contacto al hacer clic en WA / llamada / email
// ============================================================
async function logContacto(idAspirante, tipo) {
    // Fire-and-forget: no bloquear la apertura del enlace
    try {
        const desc = tipo === 'correo'
            ? 'Email enviado desde acceso directo del CRM'
            : 'Contacto iniciado desde acceso directo del CRM';
        await fetch('assets/api/historial_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion:        'crear',
                id_aspirante:  idAspirante,
                tipo:          tipo,
                descripcion:   desc,
            })
        });
    } catch(_) { /* silencioso */ }
}


// ============================================================
// DARK MODE
// ============================================================
function toggleDark() {
    const html  = document.documentElement;
    const isDark = html.dataset.theme === 'dark';
    html.dataset.theme = isDark ? 'light' : 'dark';
    localStorage.setItem(_storageKey('crm_theme'), html.dataset.theme);
    _actualizarIconoTema();
}
function _actualizarIconoTema() {
    const isDark = document.documentElement.dataset.theme === 'dark';
    document.querySelectorAll('.dark-toggle').forEach(btn => {
        if (btn.getAttribute('onclick') === 'toggleDark()') {
            btn.innerHTML = isDark ? '\u2600\uFE0F' : '\uD83C\uDF19';
            btn.title = isDark ? 'Modo claro' : 'Modo oscuro';
        }
    });
}
(function() {
    const t = localStorage.getItem(_storageKey('crm_theme')) || 'light';
    document.documentElement.dataset.theme = t;
    document.addEventListener('DOMContentLoaded', _actualizarIconoTema);
})();

// ============================================================
// BADGE TAREAS PENDIENTES HOY
// ============================================================
async function refreshBadge() {
    try {
        const r = await fetch('assets/api/agenda_api.php?accion=pendientes_hoy');
        const d = await r.json();
        const b = document.getElementById('bell-badge');
        if (d.total > 0) { b.textContent = d.total; b.style.display = 'flex'; }
        else               b.style.display = 'none';
    } catch(_) {}
}
function toggleBellMenu() {
    mostrarSeccion('agenda');
    refreshBadge();
}
refreshBadge();
setInterval(refreshBadge, 60000); // refrescar cada minuto

// Cargar datos iniciales al arrancar
document.addEventListener('DOMContentLoaded', () => {
    cargarDashboard(1);        // tabla principal sin recarga
    cargarWAMensajesEnForm();  // mensajes WA desde BD (para que estén listos en memoria)
    _actualizarIconoTema();
});

// ============================================================
// BÚSQUEDA GLOBAL (Ctrl+K)
// ============================================================
let _gsDebounce = null;
function abrirBusqueda() {
    document.getElementById('global-search-overlay').classList.add('open');
    document.getElementById('global-search-input').value = '';
    document.getElementById('global-search-results').innerHTML = '<div class="gs-hint">Escribe para buscar · <kbd>Esc</kbd> para cerrar</div>';
    setTimeout(() => document.getElementById('global-search-input').focus(), 80);
}
function cerrarBusqueda(e) {
    if (!e || e.target.id === 'global-search-overlay') {
        document.getElementById('global-search-overlay').classList.remove('open');
    }
}
async function busquedaGlobal(q) {
    clearTimeout(_gsDebounce);
    if (q.length < 2) {
        document.getElementById('global-search-results').innerHTML = '<div class="gs-hint">Escribe al menos 2 caracteres</div>';
        return;
    }
    _gsDebounce = setTimeout(async () => {
        const r    = await fetch('assets/api/aspirantes_api.php?accion=listar&busqueda=' + encodeURIComponent(q) + '&pagina=1');
        const data = await r.json();
        const cont = document.getElementById('global-search-results');
        const colP = {Contacto:'#0077cc',Interesado:'#e07b00',Inscrito:'#28a745','No Interesado':'#c0392b'};
        if (!data.aspirantes?.length) { cont.innerHTML = '<div class="gs-hint">Sin resultados para "'+esc(q)+'"</div>'; return; }
        cont.innerHTML = data.aspirantes.slice(0,8).map(a => `
            <div class="gs-item" onclick="gsSeleccionar(${a.id_aspirante})">
                <span class="gs-item-badge" style="background:${colP[a.etapa]||'#888'}">${esc(a.etapa)}</span>
                <div class="gs-item-info">
                    <div class="gs-item-name">${esc(a.nombre)}</div>
                    <div class="gs-item-sub">${esc(a.email)} · ${esc(a.carrera||'Sin carrera')}</div>
                </div>
            </div>`).join('') + (data.total > 8 ? `<div class="gs-hint">${data.total} resultados — refina la búsqueda</div>` : '');
    }, 280);
}
function gsSeleccionar(id) {
    cerrarBusqueda({target:{id:'global-search-overlay'}});
    mostrarSeccion('aspirantes');
    abrirQuickView(id);
}

// ============================================================
// QUICK VIEW DRAWER
// ============================================================
let _qvId = null, _qvNombre = '';
async function abrirQuickView(id) {
    _qvId = id;
    const qv = document.getElementById('quick-view');
    qv.classList.add('open');
    document.getElementById('qv-nombre').textContent  = '⏳ Cargando...';
    document.getElementById('qv-email').textContent   = '';
    document.getElementById('qv-historial').innerHTML = '<div style="color:var(--text-light);font-size:12px">Cargando historial...</div>';
    document.getElementById('qv-agenda').innerHTML    = '<div style="color:var(--text-light);font-size:12px">Cargando agenda...</div>';

    try {
        const r = await fetch('assets/api/aspirantes_api.php?accion=obtener&id=' + id);
        const d = await r.json();
        if (!d.ok) return;
        const a = d.aspirante;
        _qvNombre = a.nombre;
        const colP = {Contacto:'#0077cc',Interesado:'#e07b00',Inscrito:'#28a745','No Interesado':'#c0392b'};
        const tel  = _limpiarTel(a.telefono);
        const msgWA = encodeURIComponent((_waMensaje[a.etapa]||_waMensaje['Contacto'])(a.nombre));

        document.getElementById('qv-nombre').textContent  = a.nombre;
        document.getElementById('qv-email').textContent   = a.email;
        document.getElementById('qv-tel').textContent     = a.telefono || '—';
        document.getElementById('qv-carrera').textContent = a.carrera  || '—';
        document.getElementById('qv-origen').textContent  = a.origen   || '—';
        document.getElementById('qv-ci').textContent      = a.carreras_interes || '—';
        document.getElementById('qv-notas').textContent   = a.notas    || '—';
        document.getElementById('qv-etapa').innerHTML     = `<span class="etapa-badge" style="background:${colP[a.etapa]||'#888'}">${esc(a.etapa)}</span>`;

        const bar = document.getElementById('qv-contacto-bar');
        bar.innerHTML = (tel ? `<a href="https://wa.me/${tel}?text=${msgWA}" target="_blank" class="btn-wa">💬 WhatsApp</a>
            <a href="tel:+${tel}" class="btn-call">📞 Llamar</a>` : '') +
            `<a href="mailto:${esc(a.email)}" class="btn-mail">✉️ Email</a>`;

        // Historial
        const rh = await fetch('assets/api/historial_api.php?accion=listar&id_aspirante=' + id);
        const dh = await rh.json();
        const iconH = {llamada:'📞',correo:'✉️',visita:'🏢',nota:'📝'};
        document.getElementById('qv-historial').innerHTML = dh.ok && dh.historial?.length
            ? dh.historial.slice(0,5).map(h => `<div class="qv-hist-item">
                <span>${iconH[h.tipo]||'📌'}</span>
                <div><div style="font-weight:600">${esc(h.tipo)}</div>
                <div style="color:var(--text-light)">${esc(h.descripcion||'')}</div></div></div>`).join('')
            : '<div style="color:var(--text-light);font-size:12px">Sin interacciones registradas</div>';

        // Agenda próxima
        const ra = await fetch('assets/api/agenda_api.php?accion=calendario');
        const da = await ra.json();
        const prox = da.eventos?.filter(e => e.title?.includes(a.nombre)).slice(0,3) || [];
        document.getElementById('qv-agenda').innerHTML = prox.length
            ? prox.map(ev => `<div class="qv-hist-item">
                <span style="background:${ev.backgroundColor};color:#fff;padding:2px 7px;border-radius:8px;font-size:11px">${ev.extendedProps.tipo}</span>
                <div style="font-size:12px">${esc(ev.title)} · ${new Date(ev.start).toLocaleDateString('es-MX',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}</div></div>`).join('')
            : '<div style="color:var(--text-light);font-size:12px">Sin eventos próximos</div>';
    } catch(e) { console.error(e); }
}
function cerrarQuickView() { document.getElementById('quick-view').classList.remove('open'); }

// ============================================================
// AGENDAR SEGUIMIENTO RÁPIDO DESDE FILA
// ============================================================
function agendarDesdeRow(id, nombre) {
    document.getElementById('sched-id-asp').value  = id;
    document.getElementById('sched-label').textContent = '👤 Aspirante: ' + (nombre || '');
    document.getElementById('sched-titulo').value  = '';
    document.getElementById('sched-fecha').value   = new Date(Date.now() + 86400000).toISOString().slice(0,16);
    cerrarQuickView();
    abrirModal('modal-agendar-rapido');
}
async function guardarAgendarRapido() {
    const idAsp  = document.getElementById('sched-id-asp').value;
    const titulo = document.getElementById('sched-titulo').value.trim();
    const tipo   = document.getElementById('sched-tipo').value;
    const fecha  = document.getElementById('sched-fecha').value;
    if (!titulo || !fecha) { toast('Completa título y fecha', 'error'); return; }
    const resp = await fetch('assets/api/agenda_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'crear', titulo, tipo, fecha_hora: fecha.replace('T',' ') + ':00', id_aspirante: idAsp || null})
    });
    const d = await resp.json();
    if (d.ok) { toast('📅 Seguimiento programado'); cerrarModal('modal-agendar-rapido'); refreshBadge(); }
    else        toast(d.mensaje || 'Error', 'error');
}

// ============================================================
// MULTI-SELECT + ACCIONES MASIVAS
// ============================================================
function getSeleccionados() {
    return [...document.querySelectorAll('.chk-row:checked')].map(c => parseInt(c.value));
}
function actualizarBulkBar() {
    const ids = getSeleccionados();
    const bar = document.getElementById('bulk-bar');
    if (ids.length > 0) {
        bar.classList.add('visible');
        document.getElementById('bulk-count').textContent = ids.length + ' seleccionado' + (ids.length > 1 ? 's' : '');
    } else {
        bar.classList.remove('visible');
    }
}
function toggleSelAll(chk) {
    document.querySelectorAll('.chk-row').forEach(c => c.checked = chk.checked);
    actualizarBulkBar();
}
function limpiarSeleccion() {
    document.querySelectorAll('.chk-row, .chk-all, #chk-all').forEach(c => c.checked = false);
    actualizarBulkBar();
}
let _bulkOp = '';
function bulkAccion(op) {
    const ids = getSeleccionados();
    if (!ids.length) { toast('Selecciona al menos un aspirante', 'error'); return; }
    _bulkOp = op;
    const titles = {cambiar_etapa:'🔄 Cambiar etapa', agregar_nota:'📝 Agregar nota masiva', carrera_interes:'🎓 Agregar carrera de interés', eliminar:'🗑️ Mover a papelera'};
    const bodies  = {
        cambiar_etapa: `<select id="bulk-value" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">
            <option value="Contacto">Contacto</option><option value="Interesado">Interesado</option>
            <option value="Inscrito">Inscrito</option><option value="No Interesado">No Interesado</option></select>`,
        agregar_nota: `<textarea id="bulk-value" placeholder="Escribe la nota que se añadirá a los ${ids.length} aspirantes seleccionados..." style="width:100%;height:100px;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;resize:vertical"></textarea>`,
        carrera_interes: `<input type="text" id="bulk-value" placeholder="Ej: Ingeniería en Sistemas, Administración..." style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px">`,
        eliminar: `<p style="font-size:14px;color:#c0392b">⚠️ Se moverán <strong>${ids.length} aspirante(s)</strong> a la papelera. Podrás restaurarlos desde la sección Papelera.</p>`,
    };
    document.getElementById('bulk-modal-title').textContent = titles[op] || 'Acción masiva';
    document.getElementById('bulk-modal-body').innerHTML = bodies[op] || '';
    abrirModal('modal-bulk');
}
async function ejecutarBulk() {
    const ids  = getSeleccionados();
    const val  = document.getElementById('bulk-value')?.value;
    if (!ids.length) return;
    const payload = { accion:'masivo', operacion: _bulkOp, ids };
    if (_bulkOp === 'cambiar_etapa')    payload.etapa   = val;
    if (_bulkOp === 'agregar_nota')     payload.nota    = val;
    if (_bulkOp === 'carrera_interes')  payload.carrera = val;
    const resp = await fetch('assets/api/aspirantes_api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const d = await resp.json();
    cerrarModal('modal-bulk');
    if (d.ok) { toast('✅ ' + d.mensaje); limpiarSeleccion(); cargarAspirantes(_aspPaginaActual); }
    else        toast(d.mensaje || 'Error', 'error');
}
async function exportarSeleccion() {
    const ids = getSeleccionados();
    if (!ids.length) { toast('Selecciona registros para exportar', 'error'); return; }
    // Exportar solo los seleccionados leyendo de la tabla en DOM
    const filas = [['Nombre','Email','Teléfono','Carrera','Origen','Etapa','Registrado']];
    document.querySelectorAll('.chk-row:checked').forEach(chk => {
        const tr = chk.closest('tr');
        const tds = tr.querySelectorAll('td');
        filas.push([tds[1]?.querySelector('div')?.textContent?.trim()||'', tds[1]?.querySelectorAll('div')[1]?.textContent?.trim()||'',
            tds[2]?.textContent?.trim()||'', tds[3]?.textContent?.trim()||'', tds[4]?.textContent?.trim()||'',
            tds[5]?.textContent?.trim()||'', tds[6]?.textContent?.trim()||'']);
    });
    const ws = XLSX.utils.aoa_to_sheet(filas);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Selección');
    XLSX.writeFile(wb, `seleccion_${new Date().toISOString().slice(0,10)}.xlsx`);
    toast(`📥 ${ids.length} registros exportados`);
}

// ============================================================
// POBLAR SELECTS DE ASPIRANTES EN MODALES (fix bug $aspirantes)
// ============================================================
async function poblarSelectAspirantes(selectId, valorSeleccionado) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    try {
        const r = await fetch('assets/api/aspirantes_api.php?accion=listar&pagina=1&pp=999');
        const d = await r.json();
        if (!d.ok) return;
        const base = selectId === 'ag-aspirante' ? '<option value="">— Sin aspirante —</option>' : '';
        sel.innerHTML = base + d.aspirantes.map(a =>
            `<option value="${a.id_aspirante}" ${a.id_aspirante == valorSeleccionado ? 'selected' : ''}>${esc(a.nombre)}</option>`
        ).join('');
    } catch(_) {}
}

// ============================================================
// AGENDA: VISTA CALENDARIO FULLCALENDAR
// ============================================================
let _fcInstance = null;
function iniciarCalendario() {
    const el = document.getElementById('fc-container');
    if (!el || _fcInstance) return;
    _fcInstance = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'es',
        height: 540,
        headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,listWeek' },
        events: async (fetchInfo, ok, err) => {
            try {
                const r = await fetch('assets/api/agenda_api.php?accion=calendario');
                const d = await r.json();
                ok(d.eventos || []);
            } catch(e) { err(e); }
        },
        eventClick: info => {
            toast(`📅 ${info.event.title} — ${new Date(info.event.start).toLocaleDateString('es-MX',{day:'2-digit',month:'long',hour:'2-digit',minute:'2-digit'})}`);
        },
    });
    _fcInstance.render();
}
function switchAgendaView(v, btn) {
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('agenda-tabla-wrap').style.display = v === 'tabla' ? '' : 'none';
    document.getElementById('fc-container').style.display = v === 'calendario' ? '' : 'none';
    if (v === 'calendario') iniciarCalendario();
}

// ============================================================
// ATAJOS DE TECLADO
// ============================================================
document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.key === 'k') { e.preventDefault(); abrirBusqueda(); return; }
    if (e.key === 'Escape') {
        cerrarQuickView();
        cerrarBusqueda({target:{id:'global-search-overlay'}});
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
        return;
    }
    if (document.querySelector('.modal-overlay.open') || document.getElementById('global-search-overlay').classList.contains('open')) return;
    const sec = document.querySelector('.section.active')?.id;
    if (e.key === 'n' && !e.ctrlKey && sec === 'sec-aspirantes') { abrirModalAspirante(); return; }
    if (e.key === 'd' && !e.ctrlKey && sec === 'sec-aspirantes') { toggleDark(); return; }
});
// ============================================================
// Envío masivo de WhatsApp con mensaje promocional
// ============================================================
let _waMasivoDatos = [];

async function abrirEnvioMasivoWA() {
    const ids = getSeleccionados();
    if (!ids.length) { toast('Selecciona al menos un aspirante', 'error'); return; }

    document.getElementById('wa-masivo-texto').value = '';
    document.getElementById('wa-masivo-alert').style.display = 'none';

    // Tomar nombre y teléfono directamente de las filas seleccionadas (ya están en el DOM)
    _waMasivoDatos = [];
    document.querySelectorAll('.chk-row:checked').forEach(chk => {
        const tr = chk.closest('tr');
        const nombre = tr.querySelector('td:nth-child(2) div')?.textContent?.trim() || 'Aspirante';
        const telTexto = tr.querySelector('td:nth-child(3)')?.textContent?.trim() || '';
        const tel = _limpiarTel(telTexto);
        _waMasivoDatos.push({ id: chk.value, nombre, tel });
    });

    const conTel = _waMasivoDatos.filter(a => a.tel).length;
    const sinTel = _waMasivoDatos.length - conTel;

    document.getElementById('wa-masivo-lista').innerHTML =
        `<strong>${_waMasivoDatos.length} seleccionados</strong> · ✅ ${conTel} con teléfono · ` +
        (sinTel > 0 ? `<span style="color:#c0392b">⚠️ ${sinTel} sin teléfono (se omitirán)</span>` : '✅ todos tienen teléfono') +
        `<ul style="margin:8px 0 0 16px;">` +
        _waMasivoDatos.map(a => `<li>${a.nombre}${a.tel ? '' : ' — sin teléfono'}</li>`).join('') +
        `</ul>`;

    abrirModal('modal-wa-masivo');
}

function ejecutarEnvioMasivoWA() {
    const texto = document.getElementById('wa-masivo-texto').value.trim();
    const alertEl = document.getElementById('wa-masivo-alert');
    if (!texto) {
        alertEl.style.display = 'block';
        alertEl.style.background = '#fff0f0';
        alertEl.style.color = '#c0392b';
        alertEl.textContent = '⚠️ Escribe un mensaje antes de enviar.';
        return;
    }
    const destinatarios = _waMasivoDatos.filter(a => a.tel);
    if (!destinatarios.length) {
        alertEl.style.display = 'block';
        alertEl.style.background = '#fff0f0';
        alertEl.style.color = '#c0392b';
        alertEl.textContent = '⚠️ Ninguno de los seleccionados tiene teléfono registrado.';
        return;
    }

    const lista = document.getElementById('wa-masivo-lista');
    lista.innerHTML = '<strong>Haz clic en cada enlace para enviar:</strong><ul style="margin:8px 0 0 16px;">' +
        destinatarios.map(a => {
            const msg = encodeURIComponent(texto.replace(/\{nombre\}/g, a.nombre));
            return `<li><a href="https://wa.me/${a.tel}?text=${msg}" target="_blank" onclick="logContacto(${a.id},'llamada')" style="color:#1ebe57;font-weight:700;">💬 ${a.nombre}</a></li>`;
        }).join('') + '</ul>';

    toast(`💬 Lista lista — haz clic en cada nombre para abrir su chat`);
}
</script>

<!-- ═══ BÚSQUEDA GLOBAL ════════════════════════════════════════ -->
<div id="global-search-overlay" onclick="cerrarBusqueda(event)">
    <div id="global-search-box">
        <input id="global-search-input" type="text" placeholder="🔍  Buscar aspirante por nombre, email o etapa..." autocomplete="off" oninput="busquedaGlobal(this.value)">
        <div id="global-search-results"><div class="gs-hint">Escribe para buscar · <kbd>Esc</kbd> para cerrar</div></div>
    </div>
</div>

<!-- ═══ QUICK VIEW DRAWER ══════════════════════════════════════ -->
<div id="quick-view">
    <div class="qv-header">
        <div>
            <div id="qv-nombre"></div>
            <div id="qv-email"></div>
        </div>
        <button class="qv-close" onclick="cerrarQuickView()">✕</button>
    </div>
    <div class="qv-section" id="qv-contacto-bar" style="display:flex;gap:6px;flex-wrap:wrap;padding:12px 20px;background:var(--gray-bg);border-bottom:1px solid var(--border);"></div>
    <div class="qv-section">
        <h4>Información</h4>
        <div class="qv-row"><span class="qv-label">Teléfono</span><span class="qv-val" id="qv-tel">—</span></div>
        <div class="qv-row"><span class="qv-label">Carrera</span><span class="qv-val" id="qv-carrera">—</span></div>
        <div class="qv-row"><span class="qv-label">Etapa</span><span class="qv-val" id="qv-etapa"></span></div>
        <div class="qv-row"><span class="qv-label">Origen</span><span class="qv-val" id="qv-origen">—</span></div>
        <div class="qv-row"><span class="qv-label">C. Interés</span><span class="qv-val" id="qv-ci">—</span></div>
        <div class="qv-row"><span class="qv-label">Notas</span><span class="qv-val" id="qv-notas" style="white-space:pre-wrap;font-size:12px">—</span></div>
    </div>
    <div class="qv-section">
        <h4>Historial reciente</h4>
        <div id="qv-historial"><div style="color:var(--text-light);font-size:12px;padding:6px 0">Cargando...</div></div>
    </div>
    <div class="qv-section" style="border-bottom:none">
        <h4>Próximos eventos</h4>
        <div id="qv-agenda"><div style="color:var(--text-light);font-size:12px;padding:6px 0">Cargando...</div></div>
    </div>
    <div class="qv-footer" style="padding:14px 20px;display:flex;gap:8px;position:sticky;bottom:0;background:white;border-top:1px solid var(--border);box-shadow:0 -4px 12px rgba(0,0,0,0.06);">
        <button class="btn-sm btn-primary" style="flex:1;padding:10px" onclick="editarAspirante(_qvId)">✏️ Editar</button>
        <button class="btn-sm" style="flex:1;padding:10px;background:#7c3aed;color:#fff;border:none" onclick="agendarDesdeRow(_qvId,_qvNombre)">📅 Agendar</button>
    </div>
</div>

<!-- ═══ MODAL: Acción masiva ════════════════════════════════════ -->
<div class="modal-overlay" id="modal-bulk">
    <div class="modal" style="width:420px">
        <h3 id="bulk-modal-title">Acción masiva</h3>
        <div id="bulk-modal-body"></div>
        <div style="display:flex;gap:10px;margin-top:14px">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="ejecutarBulk()">✅ Aplicar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg)" onclick="cerrarModal('modal-bulk')">Cancelar</button>
        </div>
    </div>
</div>
<!-- ═══ MODAL: Envío masivo de WhatsApp ════════════════════════ -->
<div class="modal-overlay" id="modal-wa-masivo">
    <div class="modal" style="width:480px;max-height:85vh;overflow-y:auto;">
        <h3>💬 Enviar mensaje de WhatsApp masivo</h3>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:10px;">
            Se abrirá una pestaña de WhatsApp por cada aspirante seleccionado con teléfono registrado. Usa <code>{nombre}</code> para personalizar el saludo.
        </p>
        <div class="form-group">
            <label>Mensaje promocional</label>
            <textarea id="wa-masivo-texto" rows="5" placeholder="Hola {nombre}, te escribimos de la universidad para contarte sobre..."></textarea>
        </div>
        <div id="wa-masivo-lista" style="font-size:12px;color:var(--text-light);margin-bottom:10px;max-height:140px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 12px;"></div>
        <div id="wa-masivo-alert" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:10px;"></div>
        <div style="display:flex;gap:10px;margin-top:6px;">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px;background:#1ebe57;border-color:#1ebe57;" id="wa-masivo-btn" onclick="ejecutarEnvioMasivoWA()">💬 Enviar a todos</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg);" onclick="cerrarModal('modal-wa-masivo')">Cancelar</button>
        </div>
    </div>
</div>
<!-- ═══ MODAL: Agendar seguimiento rápido ═══════════════════════ -->
<div class="modal-overlay" id="modal-agendar-rapido">
    <div class="modal" style="width:440px">
        <h3>📅 Programar seguimiento</h3>
        <p id="sched-label" style="font-size:13px;color:var(--text-light);margin-bottom:12px"></p>
        <input type="hidden" id="sched-id-asp">
        <div class="form-group"><label>Tipo</label>
            <select id="sched-tipo">
                <option value="llamada">📞 Llamada</option>
                <option value="reunion">🤝 Cita / Reunión</option>
                <option value="correo">✉️ Correo</option>
                <option value="tarea">✅ Tarea interna</option>
            </select>
        </div>
        <div class="form-group"><label>Descripción</label>
            <input type="text" id="sched-titulo" placeholder="Ej: Llamada de bienvenida, Cita de admisión...">
        </div>
        <div class="form-group"><label>Fecha y hora</label>
            <input type="datetime-local" id="sched-fecha">
        </div>
        <div style="display:flex;gap:10px;margin-top:6px">
            <button class="btn-sm btn-primary" style="flex:1;padding:11px" onclick="guardarAgendarRapido()">💾 Guardar</button>
            <button class="btn-sm" style="flex:1;padding:11px;background:var(--gray-bg)" onclick="cerrarModal('modal-agendar-rapido')">Cancelar</button>
        </div>
    </div>
</div>

</body>
</html>

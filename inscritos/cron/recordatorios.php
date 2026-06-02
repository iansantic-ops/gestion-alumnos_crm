<?php
// ============================================================
// CRON: Envío automático de recordatorios de agenda
// Ejecución sugerida (crontab):
//   0 8 * * *  php /ruta/al/proyecto/cron/recordatorios.php
// ============================================================
define('DESDE_CRON', true);
require_once __DIR__ . '/../assets/sentenciasSQL/Notificador.php';

$notif = new Notificador();
if (!$notif->configurado()) {
    echo "[CRON] Correo no configurado. Saliendo.\n"; exit(1);
}

$tareas = $notif->obtenerTareasPendientes(24);
echo "[CRON] Tareas próximas 24h: " . count($tareas) . "\n";

$cfg     = $notif->getCfg();
$destino = $cfg['from_email'] ?? '';
$enviados = 0;

foreach ($tareas as $t) {
    $fecha    = date('d M Y H:i', strtotime($t['fecha_hora']));
    $aspNombre = $t['aspirante'] ?? 'Sin aspirante';
    $html     = "<h2>📅 Recordatorio: {$t['tipo']}</h2><p><b>Cuando:</b> {$fecha}</p><p><b>Aspirante:</b> ".htmlspecialchars($aspNombre)."</p><p>".htmlspecialchars($t['descripcion']??'')."</p>";
    $ok       = $notif->enviar($destino, "📅 Recordatorio CRM: {$t['tipo']} — {$fecha}", $html);
    echo "[CRON] " . ($ok ? "✓" : "✗") . " → {$destino} | {$t['tipo']} — {$fecha}\n";
    if ($ok) $enviados++;
}
echo "[CRON] Completado: {$enviados}/" . count($tareas) . " enviados.\n";

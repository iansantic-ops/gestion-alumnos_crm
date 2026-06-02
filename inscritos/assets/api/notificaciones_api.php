<?php
// ============================================================
// API Notificaciones v5 — configurar SMTP, probar, ver pendientes
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['ok'=>false,'mensaje'=>'No autorizado.']); exit();
}

require_once __DIR__ . '/../sentenciasSQL/Notificador.php';
$notif = new Notificador();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = trim($_GET['accion'] ?? '');

    if ($accion === 'config') {
        $cfg = $notif->getCfg();
        // No devolver contraseña completa
        if (!empty($cfg['smtp_pass'])) $cfg['smtp_pass'] = str_repeat('•', 8);
        echo json_encode(['ok'=>true,'config'=>$cfg,'configurado'=>$notif->configurado()]);

    } elseif ($accion === 'pendientes') {
        echo json_encode(['ok'=>true,'tareas'=>$notif->obtenerTareasPendientes(24)]);

    } else {
        echo json_encode(['ok'=>false,'mensaje'=>'Acción no reconocida.']);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = trim($body['accion'] ?? '');

    if ($accion === 'guardar_config') {
        $campos = ['smtp_host','smtp_port','smtp_usuario','smtp_pass','from_email','from_name'];
        $datos  = [];
        foreach ($campos as $c) $datos[$c] = trim($body[$c] ?? '');
        // Si smtp_pass viene con bullets (no editada), conservar la anterior
        if (str_contains($datos['smtp_pass'], '•')) {
            $anterior = $notif->getCfg();
            $datos['smtp_pass'] = $anterior['smtp_pass'] ?? '';
        }
        $ok = $notif->guardarCfg($datos);
        echo json_encode(['ok'=>$ok,'mensaje'=>$ok ? '✅ Configuración guardada.' : 'Error al guardar.']);

    } elseif ($accion === 'enviar_prueba') {
        if (!$notif->configurado()) {
            echo json_encode(['ok'=>false,'mensaje'=>'Configura primero el servidor de correo.']); exit();
        }
        $destino = filter_var(trim($body['email_prueba']??''), FILTER_VALIDATE_EMAIL);
        if (!$destino) { echo json_encode(['ok'=>false,'mensaje'=>'Email de destino inválido.']); exit(); }
        $html = '<h2 style="color:#003d99">🎓 CRM Universitario</h2><p>Este es un correo de prueba. El sistema de notificaciones está funcionando correctamente.</p><hr><small>Enviado desde CRM v5</small>';
        $ok   = $notif->enviar($destino, '✅ Prueba de correo — CRM Universitario', $html);
        echo json_encode(['ok'=>$ok,'mensaje'=>$ok ? "✅ Correo enviado a {$destino}" : '❌ Error al enviar. Revisa la configuración SMTP.']);

    } elseif ($accion === 'enviar_recordatorios') {
        if (!$notif->configurado()) {
            echo json_encode(['ok'=>false,'mensaje'=>'Correo no configurado.']); exit();
        }
        $tareas = $notif->obtenerTareasPendientes(24);
        if (empty($tareas)) { echo json_encode(['ok'=>true,'enviados'=>0,'mensaje'=>'Sin tareas pendientes para las próximas 24h.']); exit(); }

        $cfg     = $notif->getCfg();
        $destino = !empty($body['email_destino']) ? $body['email_destino'] : ($cfg['from_email'] ?? '');
        $enviados = 0;

        foreach ($tareas as $t) {
            $fecha = date('d M Y H:i', strtotime($t['fecha_hora']));
            $aspNombre = $t['aspirante'] ?? 'Sin aspirante vinculado';
            $html  = "<h2 style='color:#003d99'>📅 Recordatorio de Agenda</h2>
                      <table style='font-size:14px;border-collapse:collapse;width:100%'>
                        <tr><td style='padding:8px;font-weight:700;color:#555'>Tipo</td><td style='padding:8px'>".htmlspecialchars($t['tipo'])."</td></tr>
                        <tr style='background:#f5f7fa'><td style='padding:8px;font-weight:700;color:#555'>Fecha</td><td style='padding:8px'>{$fecha}</td></tr>
                        <tr><td style='padding:8px;font-weight:700;color:#555'>Aspirante</td><td style='padding:8px'>".htmlspecialchars($aspNombre)."</td></tr>
                        <tr style='background:#f5f7fa'><td style='padding:8px;font-weight:700;color:#555'>Descripción</td><td style='padding:8px'>".htmlspecialchars($t['descripcion']??'—')."</td></tr>
                      </table>
                      <p style='color:#888;font-size:12px;margin-top:16px'>CRM Universitario v5</p>";
            if ($notif->enviar($destino, "📅 Recordatorio: {$t['tipo']} — {$fecha}", $html)) $enviados++;
        }
        echo json_encode(['ok'=>true,'enviados'=>$enviados,'total'=>count($tareas),'mensaje'=>"Se enviaron {$enviados} de ".count($tareas)." recordatorios."]);
    } else {
        echo json_encode(['ok'=>false,'mensaje'=>'Acción desconocida.']);
    }
    exit();
}
echo json_encode(['ok'=>false,'mensaje'=>'Método no soportado.']);

<?php
// ============================================================
// Notificador — envío de correos via SMTP o mail()
// Configuración en: config/mail_config.json
// ============================================================
class Notificador {

    private array $cfg;

    public function __construct() {
        $archivo = __DIR__ . '/../../config/mail_config.json';
        $this->cfg = file_exists($archivo)
            ? (json_decode(file_get_contents($archivo), true) ?? [])
            : [];
    }

    public function configurado(): bool {
        return !empty($this->cfg['smtp_host']) && !empty($this->cfg['from_email']);
    }

    /**
     * Envía un correo.
     * Usa SMTP con stream_socket si smtp_host está configurado, o mail() como fallback.
     */
    public function enviar(string $para, string $asunto, string $cuerpoHtml): bool {
        if (!$this->configurado()) return false;

        if (!empty($this->cfg['smtp_host'])) {
            return $this->enviarSmtp($para, $asunto, $cuerpoHtml);
        }
        // Fallback: mail() nativo
        $headers  = "From: {$this->cfg['from_name']} <{$this->cfg['from_email']}>\r\n";
        $headers .= "Reply-To: {$this->cfg['from_email']}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($para, $asunto, $cuerpoHtml, $headers);
    }

    private function enviarSmtp(string $para, string $asunto, string $cuerpoHtml): bool {
        $host    = $this->cfg['smtp_host'];
        $port    = (int)($this->cfg['smtp_port'] ?? 587);
        $usuario = $this->cfg['smtp_usuario'] ?? '';
        $pass    = $this->cfg['smtp_pass']    ?? '';
        $from    = $this->cfg['from_email'];
        $nombre  = $this->cfg['from_name'] ?? 'CRM Universitario';
        $tls     = ($port === 587);

        $ctx = stream_context_create(['ssl' => ['verify_peer'=>false,'verify_peer_name'=>false]]);
        $prefix = ($port === 465) ? 'ssl://' : '';
        $sock = @stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) return false;

        $read = fn() => fgets($sock, 512);
        $send = function(string $cmd) use ($sock, $read) { fwrite($sock, $cmd . "\r\n"); return $read(); };

        $read();
        $send("EHLO localhost");
        if ($tls) { $send("STARTTLS"); stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $send("EHLO localhost"); }
        if ($usuario) { $send("AUTH LOGIN"); $send(base64_encode($usuario)); $send(base64_encode($pass)); }

        $send("MAIL FROM:<{$from}>");
        $send("RCPT TO:<{$para}>");
        $send("DATA");

        $boundary = md5(uniqid());
        $msg  = "From: =?UTF-8?B?".base64_encode($nombre)."?= <{$from}>\r\n";
        $msg .= "To: {$para}\r\n";
        $msg .= "Subject: =?UTF-8?B?".base64_encode($asunto)."?=\r\n";
        $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $cuerpoHtml . "\r\n.\r\n";
        fwrite($sock, $msg);
        $resp = $read();
        $send("QUIT");
        fclose($sock);
        return str_starts_with(trim($resp), '2');
    }

    /** Obtiene tareas de agenda que vencen en las próximas N horas */
    public function obtenerTareasPendientes(int $horas = 24): array {
        $archivo = __DIR__ . '/../sentenciasSQL/Conexion.php';
        if (!file_exists($archivo)) return [];
        include $archivo;
        $stmt = $pdo->prepare(
            'SELECT ag.*, a.nombre AS aspirante, a.email AS aspirante_email
             FROM agenda ag
             LEFT JOIN aspirantes a ON ag.id_aspirante = a.id_aspirante
             WHERE ag.completado = 0
               AND ag.fecha_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :horas HOUR)
             ORDER BY ag.fecha_hora ASC'
        );
        $stmt->execute([':horas' => $horas]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCfg(): array { return $this->cfg; }

    public function guardarCfg(array $datos): bool {
        $dir = __DIR__ . '/../../config';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return (bool)file_put_contents(
            $dir . '/mail_config.json',
            json_encode(array_map('trim', $datos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}

<?php
header('Content-Type: application/json; charset=UTF-8');

function smtp_read($sock) {
    $resp = '';
    while ($line = fgets($sock, 512)) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $resp;
}

function smtp_cmd($sock, $cmd, $expect) {
    fwrite($sock, $cmd . "\r\n");
    $r = smtp_read($sock);
    if (substr($r, 0, 3) !== (string)$expect) {
        throw new RuntimeException("SMTP error after '$cmd': $r");
    }
    return $r;
}

$nombre   = strip_tags(trim($_POST['nombre']   ?? ''));
$telefono = strip_tags(trim($_POST['telefono'] ?? ''));
$email    = strip_tags(trim($_POST['email']    ?? ''));
$servicio = strip_tags(trim($_POST['servicio'] ?? ''));
$mensaje  = strip_tags(trim($_POST['mensaje']  ?? ''));

if (!$nombre || !$telefono) {
    echo json_encode(['ok' => false, 'msg' => 'Nombre y teléfono son obligatorios']);
    exit;
}

$smtpHost  = 'smtp.ionos.es';
$smtpPort  = 587;
$smtpUser  = 'no-reply@smefontaneria24h.com';
$smtpPass  = 'RX8Yq5iPBZu41aPx1PtRdT';
$fromEmail = 'no-reply@smefontaneria24h.com';
$toEmail   = 'info@smefontaneria24h.com';
$subject   = 'Solicitud web: ' . $nombre;

$body  = "NUEVA SOLICITUD - smefontaneria24h.com\n";
$body .= str_repeat("=", 40) . "\n";
$body .= "Nombre:    $nombre\n";
$body .= "Teléfono:  $telefono\n";
if ($email)    $body .= "Email:     $email\n";
if ($servicio) $body .= "Servicio:  $servicio\n";
if ($mensaje)  $body .= "\nMensaje:\n$mensaje\n";

$msgDate     = date('r');
$msgHeaders  = "Date: $msgDate\r\n";
$msgHeaders .= "From: SME Fontaneria Web <$fromEmail>\r\n";
$msgHeaders .= "To: SME Fontaneria <$toEmail>\r\n";
$msgHeaders .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
$msgHeaders .= "MIME-Version: 1.0\r\n";
$msgHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
$msgHeaders .= "Content-Transfer-Encoding: base64\r\n";

$msgBody = chunk_split(base64_encode($body));

try {
    $sock = @fsockopen('tcp://' . $smtpHost, $smtpPort, $errno, $errstr, 30);
    if (!$sock) throw new RuntimeException("Conexión SMTP fallida: $errstr ($errno)");

    smtp_read($sock);
    smtp_cmd($sock, "EHLO smefontaneria24h.com", 250);
    smtp_cmd($sock, "STARTTLS", 220);

    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
        throw new RuntimeException("No se pudo activar TLS");
    }

    smtp_cmd($sock, "EHLO smefontaneria24h.com", 250);
    smtp_cmd($sock, "AUTH LOGIN", 334);
    smtp_cmd($sock, base64_encode($smtpUser), 334);
    smtp_cmd($sock, base64_encode($smtpPass), 235);
    smtp_cmd($sock, "MAIL FROM:<$fromEmail>", 250);
    smtp_cmd($sock, "RCPT TO:<$toEmail>", 250);
    smtp_cmd($sock, "DATA", 354);
    fwrite($sock, $msgHeaders . "\r\n" . $msgBody . "\r\n.\r\n");
    $r = smtp_read($sock);
    if (substr($r, 0, 3) !== '250') throw new RuntimeException("Error al enviar: $r");
    smtp_cmd($sock, "QUIT", 221);
    fclose($sock);

    echo json_encode(['ok' => true, 'msg' => '¡Solicitud enviada! Te contactaremos pronto.']);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al enviar. Llámanos al 624 17 52 83.', 'debug' => $e->getMessage()]);
}

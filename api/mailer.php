<?php
/**
 * Mailer utility — envía correos usando PHPMailer
 * Alejandro Lopez | Portal de Clientes
 */

require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarCorreo($destinatario, $nombreDestinatario, $asunto, $cuerpoHTML) {
    $mail = new PHPMailer(true);

    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($destinatario, $nombreDestinatario);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;
        $mail->AltBody = strip_tags($cuerpoHTML);

        $mail->send();
        return ['ok' => true, 'msg' => 'Correo enviado'];
    } catch (Exception $e) {
        // En desarrollo, registrar el error sin bloquear
        $log = date('Y-m-d H:i:s') . " | ERROR: " . $mail->ErrorInfo . "\n";
        @file_put_contents(__DIR__ . '/mail_log.txt', $log, FILE_APPEND);
        return ['ok' => false, 'msg' => 'Error al enviar correo: ' . $mail->ErrorInfo];
    }
}

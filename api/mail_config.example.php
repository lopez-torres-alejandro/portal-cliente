<?php
/**
 * Configuración SMTP para envío de correos
 * Copia este archivo como mail_config.php y completa tus datos
 */

define('SMTP_HOST', getenv('PORTAL_SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('PORTAL_SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('PORTAL_SMTP_USER') ?: '');
define('SMTP_PASS', getenv('PORTAL_SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('PORTAL_SMTP_FROM') ?: SMTP_USER);
define('SMTP_FROM_NAME', getenv('PORTAL_SMTP_FROM_NAME') ?: 'Alejandro Lopez | Portal de Clientes');

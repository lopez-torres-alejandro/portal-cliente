<?php
/** Shared HTTP bootstrap for the portal API. */
require_once __DIR__ . '/config.php';

ini_set('session.use_strict_mode', '1');
$cookieSecureSetting = getenv('PORTAL_COOKIE_SECURE');
if ($cookieSecureSetting === false) {
    $https = $_SERVER['HTTPS'] ?? '';
    $secureCookie = $https !== '' && strtolower((string)$https) !== 'off';
} else {
    $secureCookie = filter_var($cookieSecureSetting, FILTER_VALIDATE_BOOLEAN);
}
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Strict',
]);
session_start();
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function jsonError(string $message, int $status = 400): never {
    jsonResponse(['error' => $message], $status);
}

function requestJson(): array {
    $body = file_get_contents('php://input');
    if ($body === false || strlen($body) > 1_048_576 || !str_starts_with(ltrim($body), '{')) {
        jsonError('El cuerpo debe ser un objeto JSON válido', 400);
    }
    try {
        $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        jsonError('El cuerpo debe ser un objeto JSON válido', 400);
    }
    if (!is_array($data)) jsonError('El cuerpo debe ser un objeto JSON válido', 400);
    return $data;
}

function requireRole(array $roles): void {
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) jsonError('No autorizado', 401);
}

function isMailerConfigured(): bool {
    return file_exists(__DIR__ . '/mail_config.php')
        && file_exists(__DIR__ . '/vendor/autoload.php')
        && getenv('PORTAL_SMTP_USER') !== false
        && getenv('PORTAL_SMTP_USER') !== ''
        && getenv('PORTAL_SMTP_PASS') !== false
        && getenv('PORTAL_SMTP_PASS') !== '';
}

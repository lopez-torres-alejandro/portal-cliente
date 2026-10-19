<?php
/** Portal REST API entry point. */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/clients.php';
require_once __DIR__ . '/routes/registration.php';

$routes = [
    'session' => ['GET', 'handleSession', []],
    'get-all' => ['GET', 'handleGetAll', ['admin', 'client']],
    'save-clients' => ['POST', 'handleSaveClients', ['admin']],
    'login' => ['POST', 'handleLogin', []],
    'logout' => ['POST', 'handleLogout', []],
    'register' => ['POST', 'handleRegister', []],
    'verify' => ['POST', 'handleVerify', []],
    'resend-code' => ['POST', 'handleResendCode', []],
];

$action = $_GET['action'] ?? '';
if (!isset($routes[$action])) jsonError('Acción no válida', 404);
[$method, $handler, $roles] = $routes[$action];
if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
    header('Allow: ' . $method);
    jsonError('Método no permitido', 405);
}
if ($roles) requireRole($roles);

try {
    $handler();
} catch (Throwable $e) {
    error_log($e->__toString());
    jsonError('Error interno del servidor', 500);
}

<?php
/** Authentication and session endpoints. */

function handleSession(): void {
    if (empty($_SESSION['role'])) jsonError('No hay una sesión activa', 401);
    $session = ['role' => $_SESSION['role']];
    if ($_SESSION['role'] === 'client') $session['client_id'] = (int)$_SESSION['client_id'];
    jsonResponse($session);
}

function handleLogout(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict'
        ]);
    }
    session_destroy();
    jsonResponse(['message' => 'Sesión cerrada']);
}

/* ==============================================================
 * LOGIN
 * ============================================================== */
function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $input = requestJson();
    if (!isset($input['email'], $input['password']) || !is_string($input['email']) || !is_string($input['password'])) {
        jsonError('Email y contraseña requeridos');
    }
    if (strlen($input['password']) > 72) jsonError('Credenciales inválidas', 401);

    $db = getDB();
    $email = strtolower(trim($input['email']));

    // Admin
    $r = $db->prepare("SELECT email, password FROM admin_credentials WHERE LOWER(email) = ?");
    $r->execute([$email]);
    if ($admin = $r->fetch()) {
        if (empty($admin['password']) || !password_verify($input['password'], $admin['password'])) jsonError('Credenciales inválidas', 401);
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin';
        jsonResponse(['role' => 'admin', 'message' => 'Login admin exitoso']);
    }

    // Registered clients
    $r = $db->prepare("SELECT id, password FROM clients WHERE (LOWER(email) = ? OR ruc = ?) AND verified = true");
    $r->execute([$email, trim($input['email'])]);
    if ($cl = $r->fetch()) {
        if (!empty($cl['password']) && password_verify($input['password'], $cl['password'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'client';
            $_SESSION['client_id'] = (int)$cl['id'];
            jsonResponse(['role' => 'client', 'message' => 'Login cliente exitoso']);
        }
    }

    jsonError('Credenciales inválidas', 401);
}

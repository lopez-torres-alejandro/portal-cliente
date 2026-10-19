<?php
/** Public registration and email verification endpoints. */

function privateDataDirectory(): string {
    $privateBase = getenv('PORTAL_PRIVATE_DATA_DIR') ?: sys_get_temp_dir();
    $directory = rtrim($privateBase, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'portal-client-' . hash('sha256', PG_DSN);
    if (is_link($directory)) jsonError('Almacenamiento temporal no disponible', 503);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        jsonError('Almacenamiento temporal no disponible', 503);
    }
    @chmod($directory, 0700);
    $publicRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $privateDirectory = realpath($directory);
    if ($publicRoot && $privateDirectory) {
        $publicPrefix = strtolower(rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
        $privatePath = strtolower(rtrim($privateDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
        if ($privatePath === $publicPrefix || str_starts_with($privatePath, $publicPrefix)) {
            jsonError('Configura un directorio temporal privado fuera del directorio público', 503);
        }
    }
    return $directory;
}

function pendingRegistrationsFile(): string {
    return privateDataDirectory() . DIRECTORY_SEPARATOR . 'pendings.json';
}

function readPendingRegistrations(string $path): array {
    $handle = @fopen($path, 'c+');
    if (!$handle) jsonError('Almacenamiento temporal no disponible', 503);
    @chmod($path, 0600);
    flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $data = json_decode($contents ?: '[]', true);
    return is_array($data) ? $data : [];
}

function writePendingRegistrations(string $path, array $data): void {
    $handle = @fopen($path, 'c+');
    if (!$handle) jsonError('Almacenamiento temporal no disponible', 503);
    @chmod($path, 0600);
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        jsonError('Almacenamiento temporal no disponible', 503);
    }
    $contents = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    rewind($handle);
    ftruncate($handle, 0);
    $written = fwrite($handle, $contents);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if ($written === false || $written !== strlen($contents)) jsonError('No se pudo guardar el registro temporal', 503);
}

function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $input = requestJson();
    foreach (['company','email','password'] as $f) {
        if (!isset($input[$f]) || !is_string($input[$f]) || trim($input[$f]) === '') jsonError("El campo '$f' es requerido");
    }
    foreach (['ruc', 'phone'] as $f) {
        if (isset($input[$f]) && !is_string($input[$f])) jsonError("El campo '$f' no es válido");
    }

    $company = trim($input['company']);
    $email = strtolower(trim($input['email']));
    $password = $input['password'];
    $ruc = trim($input['ruc'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if (strlen($company) > 255 || strlen($email) > 255 || strlen($ruc) > 20 || strlen($phone) > 50) {
        jsonError('Uno de los campos supera el tamaño permitido');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Correo inválido');
    if (strlen($password) < 8 || strlen($password) > 72) jsonError('La contraseña debe tener entre 8 y 72 bytes');
    if (!preg_match('/[A-Z]/', $password)) jsonError('Debe tener una mayúscula');
    if (!preg_match('/[0-9]/', $password)) jsonError('Debe tener un número');

    $db = getDB();

    // Check existing
    $r = $db->prepare("SELECT id FROM clients WHERE LOWER(email) = ?");
    $r->execute([$email]);
    if ($r->fetch()) jsonError('Este correo ya está registrado');

    // Rate limit using recent codes stored for this pending registration.
    $pendings = [];
    $pf = pendingRegistrationsFile();
    $pendings = readPendingRegistrations($pf);

    $hourAgo = time() - 3600;
    $attempts = 0;
    foreach ($pendings[$email]['codes'] ?? [] as $c) {
        if ($c['t'] > $hourAgo) $attempts++;
    }
    if ($attempts >= 3) jsonError('Límite de intentos. Espera una hora.');

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $pendings[$email] = [
        'company' => $company, 'email' => $email, 'password' => $hashed,
        'ruc' => $ruc, 'phone' => $phone,
        'codes' => array_merge($pendings[$email]['codes'] ?? [], [['code' => $code, 't' => time()]])
    ];
    writePendingRegistrations($pf, $pendings);

    // Try email
    $mailSent = false;
    if (isMailerConfigured()) {
        require_once dirname(__DIR__) . '/mailer.php';
        $asunto = 'Código de verificación — Alejandro Lopez | Portal de Clientes';
        $cuerpo = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;background:#1a1a2e;color:#e2e8f0;border-radius:12px">
            <div style="text-align:center;margin-bottom:24px">
                <div style="width:48px;height:48px;border-radius:12px;background:#e8a45b;color:#111311;font-size:24px;font-weight:700;display:inline-flex;align-items:center;justify-content:center">A</div>
                <h1 style="font-size:18px;margin:8px 0 4px;color:#fff">Alejandro Lopez</h1>
                <p style="font-size:13px;color:#94a3b8;margin:0">Portal de Clientes</p>
            </div>
            <h2 style="font-size:16px;color:#fff;margin:0 0 12px">Verifica tu correo</h2>
            <p style="font-size:14px;color:#cbd5e1;margin:0 0 16px">Tu código de verificación es:</p>
            <div style="text-align:center;font-size:32px;font-weight:700;letter-spacing:8px;color:#e8a45b;background:#0f172a;padding:16px;border-radius:8px;margin-bottom:16px">' . $code . '</div>
            <p style="font-size:13px;color:#64748b;margin:0">Este código expira en 15 minutos.</p></div>';
        $res = enviarCorreo($email, $company, $asunto, $cuerpo);
        $mailSent = $res['ok'];
    }
    if (!$mailSent) {
        unset($pendings[$email]);
        writePendingRegistrations($pf, $pendings);
    }

    jsonResponse([
        'email' => $email, 'mail_sent' => $mailSent,
        'message' => $mailSent ? 'Código enviado a tu correo.' : 'No se pudo enviar el correo. Contacta al administrador.'
    ]);
}

/* ==============================================================
 * VERIFY
 * ============================================================== */
function handleVerify(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $input = requestJson();
    if (!isset($input['email'], $input['code']) || !is_string($input['email']) || !is_string($input['code'])) {
        jsonError('Email y código requeridos');
    }

    $email = strtolower(trim($input['email']));
    $code = trim($input['code']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) jsonError('Código inválido o expirado');
    $pf = pendingRegistrationsFile();
    $pendings = readPendingRegistrations($pf);
    if (!isset($pendings[$email])) jsonError('No hay registro pendiente');

    $p = $pendings[$email];
    $found = false;
    foreach ($p['codes'] ?? [] as $c) {
        if ($c['code'] === $code && (time() - $c['t']) < 900) { $found = true; break; }
    }
    if (!$found) jsonError('Código inválido o expirado');

    $db = getDB();
    $db->beginTransaction();
    $ic = $db->prepare("INSERT INTO clients (company, email, ruc, phone, password, since, verified) VALUES (?,?,?,?,?,CURRENT_DATE,true) RETURNING id");
    $ic->execute([$p['company'], $email, $p['ruc'] ?? '', $p['phone'] ?? '', $p['password']]);
    $newId = (int)$ic->fetchColumn();

    // Create initial project
    $ip = $db->prepare("INSERT INTO projects (client_id, name, progress, phase) VALUES (?,?,0,'Reunión de Diagnóstico') RETURNING id");
    $ip->execute([$newId, 'Proyecto inicial']);
    $pid = $ip->fetchColumn();

    $im = $db->prepare("INSERT INTO milestones (project_id, title, status, position) VALUES (?,?,?,?)");
    $defaults = [
        ['Reunión de diagnóstico e información inicial', 'pending'],
        ['Definición de alcance y objetivos', 'pending'],
        ['Propuesta de solución', 'pending'],
        ['Firma de contrato y kickoff', 'pending']
    ];
    foreach ($defaults as $i => $d) $im->execute([$pid, $d[0], $d[1], $i + 1]);

    $ib = $db->prepare("INSERT INTO brand_resources (project_id, palette) VALUES (?,'[]')");
    $ib->execute([$pid]);
    $db->commit();

    // Cleanup pending
    unset($pendings[$email]);
    writePendingRegistrations($pf, $pendings);

    // Welcome email
    if (isMailerConfigured()) {
        require_once dirname(__DIR__) . '/mailer.php';
        enviarCorreo($email, $p['company'], '¡Bienvenido a tu Portal de Clientes!',
            '<div style="...">Tu cuenta ha sido verificada.</div>');
    }

    jsonResponse(['message' => 'Cuenta verificada. Ya puedes iniciar sesión.']);
}

/* ==============================================================
 * RESEND-CODE
 * ============================================================== */
function handleResendCode(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $input = requestJson();
    if (!isset($input['email']) || !is_string($input['email']) || !filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL)) jsonError('Email inválido');

    $email = strtolower(trim($input['email']));
    $pf = pendingRegistrationsFile();
    $pendings = readPendingRegistrations($pf);
    if (!isset($pendings[$email])) jsonError('No hay registro pendiente');

    $hourAgo = time() - 3600;
    $attempts = 0;
    foreach ($pendings[$email]['codes'] ?? [] as $c) {
        if ($c['t'] > $hourAgo) $attempts++;
    }
    if ($attempts >= 3) jsonError('Límite de reenvíos. Espera una hora.');

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pendings[$email]['codes'][] = ['code' => $code, 't' => time()];

    $mailSent = false;
    if (isMailerConfigured()) {
        require_once dirname(__DIR__) . '/mailer.php';
        $res = enviarCorreo($email, $pendings[$email]['company'], 'Nuevo código de verificación',
            '<div>Tu nuevo código: <strong>' . $code . '</strong></div>');
        $mailSent = $res['ok'];
    }
    if (!$mailSent) array_pop($pendings[$email]['codes']);
    writePendingRegistrations($pf, $pendings);

    jsonResponse(['message' => $mailSent ? 'Código reenviado' : 'No se pudo enviar el correo.', 'mail_sent' => $mailSent]);
}

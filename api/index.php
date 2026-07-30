<?php
/**
 * API principal — Alejandro Lopez | Portal de Clientes (PostgreSQL)
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'init': handleInit(); break;
        case 'get-all': handleGetAll(); break;
        case 'save-clients': handleSaveClients(); break;
        case 'login': handleLogin(); break;
        case 'register': handleRegister(); break;
        case 'verify': handleVerify(); break;
        case 'resend-code': handleResendCode(); break;
        default: jsonError('Acción no válida', 404);
    }
} catch (\Throwable $e) {
    jsonError($e->getMessage(), 500);
}

/* ==============================================================
 * INIT
 * ============================================================== */
function handleInit() {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS clients (
        id SERIAL PRIMARY KEY, company VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL, ruc VARCHAR(20) DEFAULT '',
        phone VARCHAR(50) DEFAULT '', password VARCHAR(255) DEFAULT '',
        since DATE DEFAULT CURRENT_DATE, verified BOOLEAN DEFAULT false,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS projects (
        id SERIAL PRIMARY KEY, client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
        name VARCHAR(255) NOT NULL, progress INTEGER DEFAULT 0,
        phase VARCHAR(255) DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS milestones (
        id SERIAL PRIMARY KEY, project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        title VARCHAR(255) NOT NULL, status VARCHAR(50) DEFAULT 'pending', position INTEGER DEFAULT 0
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS services (
        id SERIAL PRIMARY KEY, project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        name VARCHAR(255) NOT NULL, status VARCHAR(50) DEFAULT 'pending',
        due_date DATE DEFAULT NULL, price VARCHAR(50) DEFAULT ''
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS brand_resources (
        id SERIAL PRIMARY KEY, project_id INTEGER UNIQUE NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        palette JSONB DEFAULT '[]', logo_url VARCHAR(500) DEFAULT '#',
        brand_manual_url VARCHAR(500) DEFAULT '#', repo_url VARCHAR(500) DEFAULT '#'
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_credentials (
        id SERIAL PRIMARY KEY, email VARCHAR(255) UNIQUE NOT NULL, password VARCHAR(255) DEFAULT ''
    )");

    $r = $db->query("SELECT COUNT(*) AS c FROM clients");
    if ($r->fetch()['c'] > 0) {
        jsonResponse(['message' => 'Ya hay datos']);
        return;
    }

    $db->exec("INSERT INTO admin_credentials (email) VALUES ('admin@developer.com')");
    seedClients($db);
    jsonResponse(['message' => 'Base de datos inicializada']);
}

function seedClients($db) {
    $clients = [
        ['company' => 'Virgo Moto Sport', 'email' => 'contacto@virgomotosport.com', 'ruc' => '20456789012', 'since' => '2026-03-15', 'projects' => [
            ['name' => 'E-commerce + Catálogo Interactivo', 'progress' => 65, 'phase' => 'Maquetación y Conexión de Base de Datos',
             'ms' => [['title' => 'Reunión de diagnóstico e información inicial', 'status' => 'completed', 'pos' => 1],
                      ['title' => 'Prototipo e interfaz de usuario (UI)', 'status' =>'completed', 'pos' => 2],
                      ['title' => 'Programación del catálogo interactivo y WhatsApp', 'status' =>'in-progress', 'pos' => 3],
                      ['title' => 'Pruebas de rendimiento y optimización móvil', 'status' =>'pending', 'pos' => 4],
                      ['title' => 'Configuración de Dominio, Hosting y Lanzamiento', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Diseño Web Profesional', 'status' =>'in-progress', 'due' => '2026-08-15', 'price' => '$2,500'],
                      ['name' => 'Mantenimiento Mensual', 'status' =>'active', 'due' => '2026-07-31', 'price' => '$150/mes'],
                      ['name' => 'SEO On-Page Básico', 'status' =>'about-to-expire', 'due' => '2026-07-10', 'price' => '$350'],
                      ['name' => 'Certificado SSL', 'status' =>'active', 'due' => '2027-03-01', 'price' => '$45/año']],
             'br' => [['name' => 'Naranja Principal', 'hex' =>'#FF5722'],['name' => 'Negro Carbón', 'hex' =>'#1A1A2E'],['name' => 'Gris Oscuro', 'hex' =>'#4A4A4A'],['name' => 'Blanco', 'hex' =>'#FFFFFF'],['name' => 'Rojo Acento', 'hex' =>'#E53935']]],
            ['name' => 'Sistema de Inventarios', 'progress' => 10, 'phase' => 'Reunión de Requerimientos',
             'ms' => [['title' => 'Diagnóstico de necesidades', 'status' =>'in-progress', 'pos' => 1],['title' => 'Diseño de base de datos', 'status' =>'pending', 'pos' => 2],['title' => 'Desarrollo del módulo de inventario', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas y despliegue', 'status' =>'pending', 'pos' => 4]],
             'sv' => [['name' => 'Hosting + Dominio .com', 'status' =>'active', 'due' => '2027-01-20', 'price' => '$120/año']],
             'br' => [['name' => 'Azul Técnico', 'hex' =>'#1976D2'],['name' => 'Gris Pizarra', 'hex' =>'#455A64'],['name' => 'Blanco', 'hex' =>'#FFFFFF']]]
        ]],
        ['company' => 'TechSolutions Perú', 'email' => 'info@techsolutions.pe', 'ruc' => '20512345678', 'since' => '2026-04-01', 'projects' => [
            ['name' => 'Plataforma SaaS Intra-empresarial', 'progress' => 30, 'phase' => 'Prototipo y Definición de Features',
             'ms' => [['title' => 'Reunión de requerimientos y alcance', 'status' =>'completed', 'pos' => 1],['title' => 'Arquitectura del sistema y base de datos', 'status' =>'in-progress', 'pos' => 2],['title' => 'Desarrollo del MVP funcional', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas beta y correcciones', 'status' =>'pending', 'pos' => 4],['title' => 'Despliegue en producción', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Desarrollo de Software a Medida', 'status' =>'in-progress', 'due' => '2026-09-30', 'price' => '$8,000'],['name' => 'Hosting Cloud + Dominio .pe', 'status' =>'active', 'due' => '2027-04-01', 'price' => '$240/año']],
             'br' => [['name' => 'Azul Corporativo', 'hex' =>'#1565C0'],['name' => 'Gris Plata', 'hex' =>'#9E9E9E'],['name' => 'Blanco', 'hex' =>'#FFFFFF']]]
        ]],
        ['company' => 'GreenLeaf Organics', 'email' => 'hola@greenleaf.pe', 'ruc' => '20698765432', 'since' => '2026-05-10', 'projects' => [
            ['name' => 'Tienda Online + Blog de Recetas', 'progress' => 15, 'phase' => 'Reunión de Diagnóstico Inicial',
             'ms' => [['title' => 'Reunión de diagnóstico e información inicial', 'status' =>'in-progress', 'pos' => 1],['title' => 'Diseño de interfaz y experiencia de usuario', 'status' =>'pending', 'pos' => 2],['title' => 'Programación de tienda y pasarela de pago', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas de calidad y carga', 'status' =>'pending', 'pos' => 4],['title' => 'Configuración de dominio y lanzamiento', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Diseño Web Profesional', 'status' =>'in-progress', 'due' => '2026-08-01', 'price' => '$2,800'],['name' => 'Hosting + Dominio .pe', 'status' =>'pending', 'due' => '2026-08-01', 'price' => '$120/año']],
             'br' => [['name' => 'Verde Orgánico', 'hex' =>'#2E7D32'],['name' => 'Crema', 'hex' =>'#FFF8E1'],['name' => 'Marrón Tierra', 'hex' =>'#5D4037']]]
        ]]
    ];

    $ic = $db->prepare("INSERT INTO clients (company, email, ruc, since, verified) VALUES (?,?,?,?,true) RETURNING id");
    $ip = $db->prepare("INSERT INTO projects (client_id, name, progress, phase) VALUES (?,?,?,?) RETURNING id");
    $im = $db->prepare("INSERT INTO milestones (project_id, title, status, position) VALUES (?,?,?,?)");
    $is = $db->prepare("INSERT INTO services (project_id, name, status, due_date, price) VALUES (?,?,?,?,?)");
    $ib = $db->prepare("INSERT INTO brand_resources (project_id, palette) VALUES (?,?)");

    foreach ($clients as $c) {
        $ic->execute([$c['company'], $c['email'], $c['ruc'], $c['since']]);
        $cid = $ic->fetchColumn();
        foreach ($c['projects'] as $p) {
            $ip->execute([$cid, $p['name'], $p['progress'], $p['phase']]);
            $pid = $ip->fetchColumn();
            foreach ($p['ms'] as $m) $im->execute([$pid, $m['title'], $m['status'], $m['pos']]);
            foreach ($p['sv'] as $s) $is->execute([$pid, $s['name'], $s['status'], $s['due'] ?? null, $s['price']]);
            $ib->execute([$pid, json_encode($p['br'])]);
        }
    }
}

/* ==============================================================
 * GET-ALL: Reconstruir JSON completo desde tablas relacionales
 * ============================================================== */
function handleGetAll() {
    $db = getDB();

    // Admin credentials
    $admin = ['email' => 'admin@developer.com'];
    $r = $db->query("SELECT email FROM admin_credentials LIMIT 1");
    if ($a = $r->fetch()) $admin = $a;

    // Dev brand
    $devBrand = ['name' => 'Alejandro Lopez', 'tagline' => 'Desarrollo Web & Tecnología'];

    // Client demo credentials
    $clientCreds = ['identifier' => DEMO_CLIENT_EMAIL, 'password' => DEMO_CLIENT_PASS];

    // All clients with nested data
    $clients = [];
    $rc = $db->query("SELECT id, company, email, ruc, phone, since, verified FROM clients ORDER BY id");
    while ($c = $rc->fetch()) {
        $c['since'] = $c['since'] ?? date('Y-m-d');
        $c['verified'] = $c['verified'] === 't' || $c['verified'] === true;
        $c['password'] = '';
        $projects = [];
        $rp = $db->prepare("SELECT id, name, progress, phase FROM projects WHERE client_id = ? ORDER BY id");
        $rp->execute([$c['id']]);
        while ($p = $rp->fetch()) {
            $p['progress'] = (int)$p['progress'];

            // Milestones
            $milestones = [];
            $rm = $db->prepare("SELECT id, title, status FROM milestones WHERE project_id = ? ORDER BY position");
            $rm->execute([$p['id']]);
            while ($m = $rm->fetch()) {
                $m['id'] = (int)$m['id'];
                $milestones[] = $m;
            }

            // Services
            $services = [];
            $rs = $db->prepare("SELECT id, name, status, due_date, price FROM services WHERE project_id = ?");
            $rs->execute([$p['id']]);
            while ($s = $rs->fetch()) {
                $s['id'] = (int)$s['id'];
                $s['dueDate'] = $s['due_date'];
                unset($s['due_date']);
                $services[] = $s;
            }

            // Brand
            $brand = ['palette' => [], 'logoUrl' => '#', 'brandManualUrl' => '#', 'repoUrl' => '#'];
            $rb = $db->prepare("SELECT palette, logo_url, brand_manual_url, repo_url FROM brand_resources WHERE project_id = ?");
            $rb->execute([$p['id']]);
            if ($b = $rb->fetch()) {
                $brand['palette'] = json_decode($b['palette'], true) ?? [];
                $brand['logoUrl'] = $b['logo_url'] ?? '#';
                $brand['brandManualUrl'] = $b['brand_manual_url'] ?? '#';
                $brand['repoUrl'] = $b['repo_url'] ?? '#';
            }

            unset($p['id']);
            $p['milestones'] = $milestones;
            $p['services'] = $services;
            $p['brand'] = $brand;
            $projects[] = $p;
        }
        $c['projects'] = $projects;
        $clients[] = $c;
    }

    jsonResponse([
        'dev_brand' => $devBrand,
        'admin_credentials' => $admin,
        'client_credentials' => $clientCreds,
        'clients' => $clients
    ]);
}

/* ==============================================================
 * SAVE-CLIENTS: Reemplazar todos los datos
 * ============================================================== */
function handleSaveClients() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['clients'])) jsonError('Se requiere clients');

    $db = getDB();
    $db->exec("DELETE FROM milestones");
    $db->exec("DELETE FROM services");
    $db->exec("DELETE FROM brand_resources");
    $db->exec("DELETE FROM projects");
    $db->exec("DELETE FROM clients");

    $ic = $db->prepare("INSERT INTO clients (id, company, email, ruc, phone, since, verified, password) VALUES (?,?,?,?,?,?,?,?)");
    $ip = $db->prepare("INSERT INTO projects (id, client_id, name, progress, phase) VALUES (?,?,?,?,?)");
    $im = $db->prepare("INSERT INTO milestones (id, project_id, title, status, position) VALUES (?,?,?,?,?)");
    $is = $db->prepare("INSERT INTO services (id, project_id, name, status, due_date, price) VALUES (?,?,?,?,?,?)");
    $ib = $db->prepare("INSERT INTO brand_resources (project_id, palette, logo_url, brand_manual_url, repo_url) VALUES (?,?,?,?,?)");

    $nextMsId = 1;
    $nextSvcId = 1;

    foreach ($input['clients'] as $c) {
        $verified = !empty($c['verified']) ? 'true' : 'false';
        $ic->execute([$c['id'], $c['company'], $c['email'] ?? '', $c['ruc'] ?? '', $c['phone'] ?? '', $c['since'] ?? date('Y-m-d'), $verified, $c['password'] ?? '']);
        foreach ($c['projects'] as $pi => $p) {
            $ip->execute([$p['id'], $c['id'], $p['name'], (int)($p['progress'] ?? 0), $p['phase'] ?? '']);
            $pos = 0;
            foreach ($p['milestones'] ?? [] as $m) {
                $im->execute([$m['id'] ?? $nextMsId++, $p['id'], $m['title'], $m['status'] ?? 'pending', $pos++]);
            }
            foreach ($p['services'] ?? [] as $s) {
                $is->execute([$s['id'] ?? $nextSvcId++, $p['id'], $s['name'], $s['status'] ?? 'pending', $s['dueDate'] ?? null, $s['price'] ?? '']);
            }
            $brand = $p['brand'] ?? [];
            $palette = json_encode($brand['palette'] ?? [], JSON_UNESCAPED_UNICODE);
            $ib->execute([$p['id'], $palette, $brand['logoUrl'] ?? '#', $brand['brandManualUrl'] ?? '#', $brand['repoUrl'] ?? '#']);
        }
    }

    jsonResponse(['message' => 'Clientes guardados correctamente']);
}

/* ==============================================================
 * LOGIN
 * ============================================================== */
function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['email']) || !isset($input['password'])) jsonError('Email y contraseña requeridos');

    $db = getDB();
    $email = strtolower(trim($input['email']));

    // Admin
    $r = $db->prepare("SELECT email FROM admin_credentials WHERE LOWER(email) = ?");
    $r->execute([$email]);
    if ($r->fetch()) jsonResponse(['role' => 'admin', 'message' => 'Login admin exitoso']);

    // Demo
    if ($email === DEMO_CLIENT_EMAIL && $input['password'] === DEMO_CLIENT_PASS) {
        jsonResponse(['role' => 'client', 'message' => 'Login cliente exitoso']);
    }

    // Registered clients
    $r = $db->prepare("SELECT password FROM clients WHERE LOWER(email) = ? AND verified = true");
    $r->execute([$email]);
    if ($cl = $r->fetch()) {
        if (!empty($cl['password']) && password_verify($input['password'], $cl['password'])) {
            jsonResponse(['role' => 'client', 'message' => 'Login cliente exitoso']);
        }
    }

    jsonError('Credenciales inválidas', 401);
}

/* ==============================================================
 * REGISTER
 * ============================================================== */
function handleRegister() {
    $input = json_decode(file_get_contents('php://input'), true);
    foreach (['company','email','password'] as $f) {
        if (empty(trim($input[$f] ?? ''))) jsonError("El campo '$f' es requerido");
    }

    $company = trim($input['company']);
    $email = strtolower(trim($input['email']));
    $password = $input['password'];
    $ruc = trim($input['ruc'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Correo inválido');
    if (strlen($password) < 8) jsonError('Mínimo 8 caracteres');
    if (!preg_match('/[A-Z]/', $password)) jsonError('Debe tener una mayúscula');
    if (!preg_match('/[0-9]/', $password)) jsonError('Debe tener un número');

    $db = getDB();

    // Check existing
    $r = $db->prepare("SELECT id FROM clients WHERE LOWER(email) = ?");
    $r->execute([$email]);
    if ($r->fetch()) jsonError('Este correo ya está registrado');

    // Rate limit
    $r = $db->query("SELECT value FROM app_settings WHERE key = 'pending_registrations'");
    // We'll store pending in a simple JSON file approach for now
    $pendings = [];
    $pf = __DIR__ . '/pendings.json';
    if (file_exists($pf)) $pendings = json_decode(file_get_contents($pf), true) ?? [];

    $hourAgo = time() - 3600;
    $attempts = 0;
    foreach ($pendings[$email]['codes'] ?? [] as $c) {
        if ($c['t'] > $hourAgo) $attempts++;
    }
    if ($attempts >= 3) jsonError('Límite de intentos. Espera una hora.');

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashed = password_hash($password, PASSWORD_BCRYPT);

    $pendings[$email] = [
        'company' => $company, 'email' => $email, 'password' => $hashed,
        'ruc' => $ruc, 'phone' => $phone,
        'codes' => array_merge($pendings[$email]['codes'] ?? [], [['code' => $code, 't' => time()]])
    ];
    file_put_contents($pf, json_encode($pendings, JSON_UNESCAPED_UNICODE));

    // Try email
    $mailSent = false;
    $mailMsg = '';
    $codeDisplay = $code;
    if (file_exists(__DIR__ . '/mailer.php')) {
        require_once __DIR__ . '/mailer.php';
        $asunto = 'Código de verificación — Alejandro Lopez | Portal de Clientes';
        $cuerpo = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;background:#1a1a2e;color:#e2e8f0;border-radius:12px">
            <div style="text-align:center;margin-bottom:24px">
                <div style="width:48px;height:48px;border-radius:12px;background:#10b981;color:#fff;font-size:24px;font-weight:700;display:inline-flex;align-items:center;justify-content:center">A</div>
                <h1 style="font-size:18px;margin:8px 0 4px;color:#fff">Alejandro Lopez</h1>
                <p style="font-size:13px;color:#94a3b8;margin:0">Portal de Clientes</p>
            </div>
            <h2 style="font-size:16px;color:#fff;margin:0 0 12px">Verifica tu correo</h2>
            <p style="font-size:14px;color:#cbd5e1;margin:0 0 16px">Tu código de verificación es:</p>
            <div style="text-align:center;font-size:32px;font-weight:700;letter-spacing:8px;color:#10b981;background:#0f172a;padding:16px;border-radius:8px;margin-bottom:16px">' . $code . '</div>
            <p style="font-size:13px;color:#64748b;margin:0">Este código expira en 15 minutos.</p></div>';
        $res = enviarCorreo($email, $company, $asunto, $cuerpo);
        $mailSent = $res['ok'];
        $mailMsg = $res['msg'];
    }

    jsonResponse([
        'message' => 'Código enviado a tu correo.',
        'email' => $email, 'mail_sent' => $mailSent,
        'mail_msg' => $mailMsg, 'dev_code' => $codeDisplay
    ]);
}

/* ==============================================================
 * VERIFY
 * ============================================================== */
function handleVerify() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['email']) || !isset($input['code'])) jsonError('Email y código requeridos');

    $email = strtolower(trim($input['email']));
    $code = trim($input['code']);
    $pf = __DIR__ . '/pendings.json';

    if (!file_exists($pf)) jsonError('No hay registro pendiente');
    $pendings = json_decode(file_get_contents($pf), true) ?? [];
    if (!isset($pendings[$email])) jsonError('No hay registro pendiente');

    $p = $pendings[$email];
    $found = false;
    foreach ($p['codes'] ?? [] as $c) {
        if ($c['code'] === $code && (time() - $c['t']) < 900) { $found = true; break; }
    }
    if (!$found) jsonError('Código inválido o expirado');

    $db = getDB();
    $r = $db->query("SELECT COALESCE(MAX(id), 0) + 1 AS next FROM clients");
    $newId = (int)$r->fetch()['next'];

    $ic = $db->prepare("INSERT INTO clients (id, company, email, ruc, phone, password, since, verified) VALUES (?,?,?,?,?,?,CURRENT_DATE,true)");
    $ic->execute([$newId, $p['company'], $email, $p['ruc'] ?? '', $p['phone'] ?? '', $p['password']]);

    // Create initial project
    $ip = $db->prepare("INSERT INTO projects (client_id, name, progress, phase) VALUES (?,?,0,'Reunión de Diagnóstico') RETURNING id");
    $ip->execute([$newId]);
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

    // Cleanup pending
    unset($pendings[$email]);
    file_put_contents($pf, json_encode($pendings, JSON_UNESCAPED_UNICODE));

    // Welcome email
    if (file_exists(__DIR__ . '/mailer.php')) {
        require_once __DIR__ . '/mailer.php';
        enviarCorreo($email, $p['company'], '¡Bienvenido a tu Portal de Clientes!',
            '<div style="...">Tu cuenta ha sido verificada.</div>');
    }

    jsonResponse(['message' => 'Cuenta verificada. Ya puedes iniciar sesión.']);
}

/* ==============================================================
 * RESEND-CODE
 * ============================================================== */
function handleResendCode() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['email'])) jsonError('Email requerido');

    $email = strtolower(trim($input['email']));
    $pf = __DIR__ . '/pendings.json';
    if (!file_exists($pf)) jsonError('No hay registro pendiente');
    $pendings = json_decode(file_get_contents($pf), true) ?? [];
    if (!isset($pendings[$email])) jsonError('No hay registro pendiente');

    $hourAgo = time() - 3600;
    $attempts = 0;
    foreach ($pendings[$email]['codes'] ?? [] as $c) {
        if ($c['t'] > $hourAgo) $attempts++;
    }
    if ($attempts >= 3) jsonError('Límite de reenvíos. Espera una hora.');

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pendings[$email]['codes'][] = ['code' => $code, 't' => time()];
    file_put_contents($pf, json_encode($pendings, JSON_UNESCAPED_UNICODE));

    $mailSent = false;
    if (file_exists(__DIR__ . '/mailer.php')) {
        require_once __DIR__ . '/mailer.php';
        $res = enviarCorreo($email, $pendings[$email]['company'], 'Nuevo código de verificación',
            '<div>Tu nuevo código: <strong>' . $code . '</strong></div>');
        $mailSent = $res['ok'];
    }

    jsonResponse(['message' => 'Código reenviado', 'mail_sent' => $mailSent, 'dev_code' => $code]);
}
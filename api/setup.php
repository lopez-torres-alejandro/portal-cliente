<?php
/** Provision the first administrator from the CLI only. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$arguments = array_slice($argv, 1);
$seedDemo = in_array('--seed-demo', $arguments, true);
$unknownArguments = array_diff($arguments, ['--seed-demo']);
if ($unknownArguments) {
    fwrite(STDERR, "Uso: php api/setup.php [--seed-demo]\n");
    exit(2);
}

$db = null;
try {
    $db = getDB();
    $db->beginTransaction();

    if (!$db->query("SELECT to_regclass('public.admin_credentials')")->fetchColumn()) {
        throw new RuntimeException('Primero aplica database/init.sql en PostgreSQL.');
    }

    $existingAdmin = $db->query('SELECT id, password FROM admin_credentials ORDER BY id LIMIT 1')->fetch();
    if (!$existingAdmin || empty($existingAdmin['password'])) {
        $adminEmail = strtolower(trim(getenv('PORTAL_ADMIN_EMAIL') ?: ''));
        $adminPassword = getenv('PORTAL_ADMIN_PASSWORD') ?: '';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 12 || strlen($adminPassword) > 72) {
            throw new RuntimeException('Configura PORTAL_ADMIN_EMAIL y PORTAL_ADMIN_PASSWORD (12 a 72 bytes).');
        }

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        if ($existingAdmin) {
            $statement = $db->prepare('UPDATE admin_credentials SET email = ?, password = ? WHERE id = ?');
            $statement->execute([$adminEmail, $passwordHash, $existingAdmin['id']]);
        } else {
            $statement = $db->prepare('INSERT INTO admin_credentials (email, password) VALUES (?, ?)');
            $statement->execute([$adminEmail, $passwordHash]);
        }
    }

    if ($seedDemo && !(int)$db->query('SELECT COUNT(*) FROM clients')->fetchColumn()) {
        require_once __DIR__ . '/seed_demo.php';
        seedDemoClients($db);
    }

    $db->commit();
    fwrite(STDOUT, "Administrador configurado.\n");
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log($e->__toString());
    fwrite(STDERR, "No se pudo inicializar el portal. Revisa la configuración y los logs de PHP.\n");
    exit(1);
}

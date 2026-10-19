<?php
/**
 * Configuración PostgreSQL.
 * Copia este archivo como config.php y define las variables indicadas en README.
 */
$dbHost = getenv('PORTAL_DB_HOST') ?: '';
$dbPort = getenv('PORTAL_DB_PORT') ?: '5432';
$dbName = getenv('PORTAL_DB_NAME') ?: 'postgres';
$dbSslMode = getenv('PORTAL_DB_SSLMODE') ?: 'require';

define('PG_DSN', sprintf(
    'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
    $dbHost,
    $dbPort,
    $dbName,
    $dbSslMode
));
define('PG_USER', getenv('PORTAL_DB_USER') ?: '');
define('PG_PASS', getenv('PORTAL_DB_PASSWORD') ?: '');

function getDB(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;

    if (str_contains(PG_DSN, 'host=;') || PG_USER === '' || PG_PASS === '') {
        throw new RuntimeException('Falta configurar la conexión PostgreSQL del portal.');
    }

    $db = new PDO(PG_DSN, PG_USER, PG_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $db;
}

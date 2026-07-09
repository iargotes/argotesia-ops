<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

load_env(__DIR__ . '/../.env');

date_default_timezone_set(env('APP_TIMEZONE', 'America/Panama') ?? 'America/Panama');

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    env('DB_HOST', '127.0.0.1'),
    env('DB_PORT', '3310'),
    env('DB_DATABASE', 'argotesia_ops'),
    env('DB_CHARSET', 'utf8mb4')
);

try {
    $conn = new PDO($dsn, env('DB_USERNAME', 'ops_user'), env('DB_PASSWORD', 'ops_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('DB connection failed.');
}


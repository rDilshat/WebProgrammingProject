<?php

declare(strict_types=1);

$localConfigPath = __DIR__ . '/database.local.php';
$localConfig = file_exists($localConfigPath) ? require $localConfigPath : [];

$config = [
    'host' => getenv('DB_HOST') ?: ($localConfig['host'] ?? '127.0.0.1'),
    'port' => getenv('DB_PORT') ?: ($localConfig['port'] ?? '5432'),
    'database' => getenv('DB_NAME') ?: ($localConfig['database'] ?? 'kinomonster'),
    'user' => getenv('DB_USER') ?: ($localConfig['user'] ?? 'postgres'),
    'password' => getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? ''),
];

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $config['host'],
    $config['port'],
    $config['database']
);

return new PDO($dsn, $config['user'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

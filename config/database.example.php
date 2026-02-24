<?php
declare(strict_types=1);

function envValue(string $key, string $fallback): string
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? ($_SERVER[$key] ?? $fallback);
    }

    $value = is_string($value) ? trim($value) : $fallback;
    return $value !== '' ? $value : $fallback;
}

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envValue('DB_HOST', 'localhost');
    $port = envValue('DB_PORT', '3306');
    $dbName = envValue('DB_NAME', 'blog_php');
    $username = envValue('DB_USER', 'root');
    $password = envValue('DB_PASS', '');
    $charset = envValue('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    return $pdo;
}

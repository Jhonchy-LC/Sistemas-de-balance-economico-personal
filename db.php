<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

function app_base_path(string $path = ''): string
{
    $basePath = __DIR__;

    if ($path === '') {
        return $basePath;
    }

    return $basePath . DIRECTORY_SEPARATOR . $path;
}

function app_config(string $name, mixed $default = null): mixed
{
    if (defined($name)) {
        return constant($name);
    }

    $envValue = getenv($name);
    if ($envValue !== false) {
        return $envValue;
    }

    return $default;
}

function app_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = (string) app_config('DB_DRIVER', 'mysql');

    if ($driver === 'sqlite') {
        $databasePath = app_config('DB_DATABASE_PATH', app_base_path('data' . DIRECTORY_SEPARATOR . 'ahorros.sqlite'));
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        app_init_db($pdo);

        return $pdo;
    }

    $host = (string) app_config('DB_HOST', 'localhost');
    $port = (string) app_config('DB_PORT', '3306');
    $database = (string) app_config('DB_NAME', '');
    $username = (string) app_config('DB_USER', '');
    $password = (string) app_config('DB_PASS', '');

    if ($database === '' || $username === '') {
        throw new RuntimeException('Falta configurar la conexion MySQL en config.php.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    app_init_db($pdo);

    return $pdo;
}

function app_init_db(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK(type IN ("saving", "expense")),
                amount REAL NOT NULL,
                description TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_transactions_user_created ON transactions(user_id, created_at)');

        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type ENUM(\'saving\', \'expense\') NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            CONSTRAINT fk_transactions_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_transactions_user_created (user_id, created_at)
        )'
    );
}

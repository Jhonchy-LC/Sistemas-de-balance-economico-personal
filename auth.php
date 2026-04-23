<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Lima');

function app_flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }

    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $value = (string) $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function app_csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function app_verify_csrf(?string $token): void
{
    if (!$token || empty($_SESSION['_csrf']) || !hash_equals((string) $_SESSION['_csrf'], $token)) {
        throw new RuntimeException('Token de seguridad invalido.');
    }
}

function app_current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = app_pdo()->prepare('SELECT id, username, created_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $statement->fetch();

    return $user ?: null;
}

function app_require_login(): array
{
    $user = app_current_user();

    if ($user === null) {
        header('Location: index.php');
        exit;
    }

    return $user;
}

function app_login_user(string $username, string $password): bool
{
    $statement = app_pdo()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $statement->execute([':username' => $username]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = (int) $user['id'];

    return true;
}

function app_register_user(string $username, string $password): bool
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return false;
    }

    $statement = app_pdo()->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');

    try {
        $statement->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $exception) {
        return false;
    }

    $_SESSION['user_id'] = (int) app_pdo()->lastInsertId();

    return true;
}

function app_logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function app_money(float $amount): string
{
    return 'S/ ' . number_format($amount, 2, '.', ',');
}

function app_transaction_summary(int $userId, string $from = ''): array
{
    $pdo = app_pdo();

    $params = [':user_id' => $userId];
    $where = 'WHERE user_id = :user_id';

    if ($from !== '') {
        $where .= ' AND created_at >= :from_date';
        $params[':from_date'] = $from;
    }

    $statement = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'saving' THEN amount ELSE 0 END), 0) AS savings,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses
        FROM transactions
        $where"
    );
    $statement->execute($params);
    $summary = $statement->fetch() ?: ['savings' => 0, 'expenses' => 0];

    return [
        'savings' => (float) $summary['savings'],
        'expenses' => (float) $summary['expenses'],
        'balance' => (float) $summary['savings'] - (float) $summary['expenses'],
    ];
}

function app_transaction_summary_between(int $userId, string $from, string $toExclusive): array
{
    $statement = app_pdo()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'saving' THEN amount ELSE 0 END), 0) AS savings,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expenses
         FROM transactions
         WHERE user_id = :user_id
           AND created_at >= :from_date
           AND created_at < :to_date"
    );
    $statement->execute([
        ':user_id' => $userId,
        ':from_date' => $from,
        ':to_date' => $toExclusive,
    ]);
    $summary = $statement->fetch() ?: ['savings' => 0, 'expenses' => 0];

    return [
        'savings' => (float) $summary['savings'],
        'expenses' => (float) $summary['expenses'],
        'balance' => (float) $summary['savings'] - (float) $summary['expenses'],
    ];
}

function app_transaction_period_summary(int $userId, int $year, int $month): array
{
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    $end = $start->modify('+1 month');

    return app_transaction_summary_between($userId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
}

function app_transaction_year_summary(int $userId, int $year): array
{
    $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
    $end = $start->modify('+1 year');

    return app_transaction_summary_between($userId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
}

function app_transaction_month_panels(int $userId, int $year): array
{
    $panels = [];

    for ($month = 1; $month <= 12; $month++) {
        $panels[] = [
            'month' => $month,
            'label' => DateTimeImmutable::createFromFormat('!m', (string) $month)->format('F'),
            'summary' => app_transaction_period_summary($userId, $year, $month),
        ];
    }

    return $panels;
}

function app_available_years(int $userId): array
{
    $statement = app_pdo()->prepare(
        'SELECT DISTINCT YEAR(created_at) AS year
         FROM transactions
         WHERE user_id = :user_id
         ORDER BY year DESC'
    );
    $statement->execute([':user_id' => $userId]);

    $years = array_filter(array_map(static fn ($row) => (int) ($row['year'] ?? 0), $statement->fetchAll()), static fn ($year) => $year > 0);

    if ($years === []) {
        $years = [(int) date('Y')];
    }

    return array_values(array_unique($years));
}

function app_transactions_for_period(int $userId, int $year, int $month): array
{
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
    $end = $start->modify('+1 month');

    $statement = app_pdo()->prepare(
        'SELECT id, type, amount, description, created_at
         FROM transactions
         WHERE user_id = :user_id
           AND created_at >= :from_date
           AND created_at < :to_date
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute([
        ':user_id' => $userId,
        ':from_date' => $start->format('Y-m-d H:i:s'),
        ':to_date' => $end->format('Y-m-d H:i:s'),
    ]);

    return $statement->fetchAll();
}

function app_transaction_by_id(int $transactionId, int $userId): ?array
{
    $statement = app_pdo()->prepare(
        'SELECT id, type, amount, description, created_at
         FROM transactions
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $transactionId,
        ':user_id' => $userId,
    ]);

    $transaction = $statement->fetch();

    return $transaction ?: null;
}

function app_update_transaction(int $transactionId, int $userId, string $type, float $amount, string $description): bool
{
    $statement = app_pdo()->prepare(
        'UPDATE transactions
         SET type = :type,
             amount = :amount,
             description = :description
         WHERE id = :id AND user_id = :user_id'
    );

    return $statement->execute([
        ':type' => $type,
        ':amount' => $amount,
        ':description' => $description !== '' ? $description : null,
        ':id' => $transactionId,
        ':user_id' => $userId,
    ]);
}

function app_delete_transaction(int $transactionId, int $userId): bool
{
    $statement = app_pdo()->prepare(
        'DELETE FROM transactions WHERE id = :id AND user_id = :user_id'
    );

    return $statement->execute([
        ':id' => $transactionId,
        ':user_id' => $userId,
    ]);
}

function app_transaction_groups(int $userId): array
{
    $today = new DateTimeImmutable('today');
    $weekStart = new DateTimeImmutable('monday this week');
    $monthStart = new DateTimeImmutable('first day of this month');

    return [
        'daily' => app_transaction_summary($userId, $today->format('Y-m-d 00:00:00')),
        'weekly' => app_transaction_summary($userId, $weekStart->format('Y-m-d 00:00:00')),
        'monthly' => app_transaction_summary($userId, $monthStart->format('Y-m-d 00:00:00')),
    ];
}

function app_recent_transactions(int $userId, int $limit = 30): array
{
    $statement = app_pdo()->prepare(
        'SELECT id, type, amount, description, created_at
         FROM transactions
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

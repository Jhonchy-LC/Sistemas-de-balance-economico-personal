<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

try {
    app_verify_csrf($_POST['_csrf'] ?? null);
} catch (Throwable $throwable) {
    app_flash('error', $throwable->getMessage());
    header('Location: index.php');
    exit;
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'register') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        app_flash('error', 'Completa usuario y contrasena para registrarte.');
        header('Location: index.php');
        exit;
    }

    if (app_register_user($username, $password)) {
        app_flash('success', 'Usuario creado correctamente.');
        header('Location: index.php');
        exit;
    }

    app_flash('error', 'No se pudo crear el usuario. Puede que ya exista.');
    header('Location: index.php');
    exit;
}

if ($action === 'login') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (app_login_user($username, $password)) {
        app_flash('success', 'Sesion iniciada correctamente.');
        header('Location: index.php');
        exit;
    }

    app_flash('error', 'Usuario o contrasena incorrectos.');
    header('Location: index.php');
    exit;
}

$user = app_require_login();

if ($action === 'delete_transaction') {
    $transactionId = (int) ($_POST['transaction_id'] ?? 0);

    if ($transactionId <= 0) {
        app_flash('error', 'Movimiento invalido.');
        header('Location: index.php');
        exit;
    }

    if (app_delete_transaction($transactionId, (int) $user['id'])) {
        app_flash('success', 'Movimiento eliminado.');
    } else {
        app_flash('error', 'No se pudo eliminar el movimiento.');
    }

    header('Location: index.php');
    exit;
}

if ($action === 'edit_transaction') {
    $transactionId = (int) ($_POST['transaction_id'] ?? 0);
    $type = (string) ($_POST['type'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($transactionId <= 0) {
        app_flash('error', 'Movimiento invalido.');
        header('Location: index.php');
        exit;
    }

    if (!in_array($type, ['saving', 'expense'], true)) {
        app_flash('error', 'Tipo de movimiento invalido.');
        header('Location: index.php');
        exit;
    }

    if ($amount <= 0) {
        app_flash('error', 'El monto debe ser mayor que cero.');
        header('Location: index.php');
        exit;
    }

    if ($type === 'expense' && $description === '') {
        app_flash('error', 'La descripcion del gasto es obligatoria.');
        header('Location: index.php');
        exit;
    }

    if (app_update_transaction($transactionId, (int) $user['id'], $type, $amount, $description)) {
        app_flash('success', 'Movimiento actualizado.');
    } else {
        app_flash('error', 'No se pudo actualizar el movimiento.');
    }

    header('Location: index.php');
    exit;
}

if ($action === 'logout') {
    app_logout_user();
    session_start();
    app_flash('success', 'Sesion cerrada.');
    header('Location: index.php');
    exit;
}

if ($action === 'save_transaction') {
    $type = (string) ($_POST['type'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if (!in_array($type, ['saving', 'expense'], true)) {
        app_flash('error', 'Tipo de movimiento invalido.');
        header('Location: index.php');
        exit;
    }

    if ($amount <= 0) {
        app_flash('error', 'El monto debe ser mayor que cero.');
        header('Location: index.php');
        exit;
    }

    if ($type === 'expense' && $description === '') {
        app_flash('error', 'La descripcion del gasto es obligatoria.');
        header('Location: index.php');
        exit;
    }

    $statement = app_pdo()->prepare(
        'INSERT INTO transactions (user_id, type, amount, description, created_at)
         VALUES (:user_id, :type, :amount, :description, :created_at)'
    );
    $statement->execute([
        ':user_id' => (int) $user['id'],
        ':type' => $type,
        ':amount' => $amount,
        ':description' => $description !== '' ? $description : null,
        ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
    ]);

    app_flash('success', $type === 'saving' ? 'Ahorro agregado.' : 'Gasto agregado.');
    header('Location: index.php');
    exit;
}

app_flash('error', 'Accion no valida.');
header('Location: index.php');
exit;

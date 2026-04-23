<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = app_current_user();
$error = app_flash('error');
$success = app_flash('success');
$csrf = app_csrf_token();
$selectedYear = (int) ($_GET['year'] ?? date('Y'));
$selectedMonth = (int) ($_GET['month'] ?? date('n'));
$view = (string) ($_GET['view'] ?? 'main');

if ($selectedYear <= 0) {
    $selectedYear = (int) date('Y');
}

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int) date('n');
}

if (!in_array($view, ['main', 'year'], true)) {
    $view = 'main';
}

function app_period_label(string $key): string
{
    return match ($key) {
        'daily' => 'Dia',
        'weekly' => 'Semana',
        'monthly' => 'Mes',
        default => ucfirst($key),
    };
}

function app_transaction_label(string $type): string
{
    return $type === 'saving' ? 'Ahorro' : 'Gasto';
}

function app_transaction_class(string $type): string
{
    return $type === 'saving' ? 'is-saving' : 'is-expense';
}

function app_month_name_es(int $month): string
{
    return [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ][$month] ?? (string) $month;
}

function app_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);

    return $date ? $date->format('d/m/Y H:i') : $value;
}

$summaryOverall = ['savings' => 0.0, 'expenses' => 0.0, 'balance' => 0.0];
$summaryByPeriod = ['daily' => $summaryOverall, 'weekly' => $summaryOverall, 'monthly' => $summaryOverall];
$yearSummary = $summaryOverall;
$monthSummary = $summaryOverall;
$monthPanels = [];
$availableYears = [(int) date('Y')];
$selectedMonthTransactions = [];
$recentTransactions = [];

if ($user !== null) {
    $userId = (int) $user['id'];
    $summaryOverall = app_transaction_summary($userId);
    $summaryByPeriod = app_transaction_groups($userId);
    $yearSummary = app_transaction_year_summary($userId, $selectedYear);
    $monthSummary = app_transaction_period_summary($userId, $selectedYear, $selectedMonth);
    $monthPanels = app_transaction_month_panels($userId, $selectedYear);
    $availableYears = app_available_years($userId);
    $selectedMonthTransactions = app_transactions_for_period($userId, $selectedYear, $selectedMonth);
    $recentTransactions = app_recent_transactions($userId, 12);

    if (!in_array($selectedYear, $availableYears, true)) {
        $availableYears[] = $selectedYear;
        rsort($availableYears);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Ahorros Personal</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page-shell">
    <header class="hero">
        <div>
            <p class="eyebrow">Control de ahorros personal</p>
            <h1>Tu dinero, bajo control</h1>
            <p class="hero-copy">Registra tus ahorros y gastos, consulta tus periodos clave y revisa el historial mensual para tomar mejores decisiones financieras.</p>
        </div>
        <?php if ($user !== null): ?>
            <div class="user-card">
                <div class="user-card-main">
                    <span>Usuario</span>
                    <strong><?php echo htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <form method="post" action="action.php" class="logout-inline">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-ghost btn-small" type="submit">Cerrar sesion</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($error !== null): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($success !== null): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($user === null): ?>
        <section class="auth-grid">
            <article class="panel">
                <h2>Iniciar sesion</h2>
                <form class="form-stack" method="post" action="action.php">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="login">
                    <label>
                        Usuario
                        <input type="text" name="username" required autocomplete="username" maxlength="190">
                    </label>
                    <label>
                        Contrasena
                        <input type="password" name="password" required autocomplete="current-password" minlength="8">
                    </label>
                    <button class="btn btn-primary" type="submit">Entrar</button>
                </form>
            </article>

            <article class="panel panel-accent">
                <h2>Crear cuenta</h2>
                <p class="panel-text">Crea una cuenta para comenzar a registrar movimientos de forma segura.</p>
                <form class="form-stack" method="post" action="action.php">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="register">
                    <label>
                        Usuario
                        <input type="text" name="username" required autocomplete="username" maxlength="190">
                    </label>
                    <label>
                        Contrasena
                        <input type="password" name="password" required autocomplete="new-password" minlength="8">
                    </label>
                    <button class="btn btn-secondary" type="submit">Registrarme</button>
                </form>
            </article>
        </section>
    <?php else: ?>
        <nav class="view-tabs" aria-label="Vistas del resumen">
            <a class="tab <?php echo $view === 'main' ? 'is-active' : ''; ?>" href="index.php?view=main&amp;year=<?php echo (int) $selectedYear; ?>&amp;month=<?php echo (int) $selectedMonth; ?>">Vista principal</a>
            <a class="tab <?php echo $view === 'year' ? 'is-active' : ''; ?>" href="index.php?view=year&amp;year=<?php echo (int) $selectedYear; ?>&amp;month=<?php echo (int) $selectedMonth; ?>">Resumen anual</a>
        </nav>

        <?php if ($view === 'main'): ?>
            <section class="summary-grid">
                <article class="summary-card summary-total">
                    <span>Saldo total</span>
                    <strong><?php echo htmlspecialchars(app_money($summaryOverall['balance']), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>Disponible acumulado en todo tu historial</small>
                </article>
                <article class="summary-card">
                    <span>Ahorros</span>
                    <strong><?php echo htmlspecialchars(app_money($summaryOverall['savings']), ENT_QUOTES, 'UTF-8'); ?></strong>
                </article>
                <article class="summary-card">
                    <span>Gastos</span>
                    <strong><?php echo htmlspecialchars(app_money($summaryOverall['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong>
                </article>
            </section>

            <section class="period-grid">
                <?php foreach ($summaryByPeriod as $periodKey => $summary): ?>
                    <article class="panel period-card">
                        <h2><?php echo htmlspecialchars(app_period_label($periodKey), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="period-values">
                            <div>
                                <span>Ahorros</span>
                                <strong><?php echo htmlspecialchars(app_money($summary['savings']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Gastos</span>
                                <strong><?php echo htmlspecialchars(app_money($summary['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Saldo</span>
                                <strong><?php echo htmlspecialchars(app_money($summary['balance']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="actions-grid">
                <article class="panel">
                    <h2>Agregar ahorro</h2>
                    <form class="form-stack" method="post" action="action.php">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="save_transaction">
                        <input type="hidden" name="type" value="saving">
                        <label>
                            Monto
                            <input type="number" name="amount" step="0.01" min="0.01" required>
                        </label>
                        <label>
                            Nota opcional
                            <input type="text" name="description" maxlength="255" placeholder="Ej. Ahorro semanal">
                        </label>
                        <button class="btn btn-primary" type="submit">Agregar ahorro</button>
                    </form>
                </article>

                <article class="panel panel-accent">
                    <h2>Agregar gasto</h2>
                    <form class="form-stack" method="post" action="action.php">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="save_transaction">
                        <input type="hidden" name="type" value="expense">
                        <label>
                            Monto
                            <input type="number" name="amount" step="0.01" min="0.01" required>
                        </label>
                        <label>
                            Descripcion del gasto
                            <textarea name="description" rows="3" maxlength="500" placeholder="Ej. Comida, transporte, recarga" required></textarea>
                        </label>
                        <button class="btn btn-secondary" type="submit">Agregar gasto</button>
                    </form>
                </article>
            </section>
        <?php else: ?>
            <section class="panel history-panel">
                <div class="section-head">
                    <h2>Resumen anual</h2>
                    <form method="get" action="index.php" class="year-filter">
                        <input type="hidden" name="view" value="year">
                        <input type="hidden" name="month" value="<?php echo (int) $selectedMonth; ?>">
                        <label>
                            Ano
                            <select name="year" onchange="this.form.submit()">
                                <?php foreach ($availableYears as $yearOption): ?>
                                    <option value="<?php echo (int) $yearOption; ?>" <?php echo $yearOption === $selectedYear ? 'selected' : ''; ?>><?php echo (int) $yearOption; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </form>
                </div>
                <div class="summary-grid">
                    <article class="summary-card summary-total">
                        <span>Total del ano</span>
                        <strong><?php echo htmlspecialchars(app_money($yearSummary['balance']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small>Ano <?php echo (int) $selectedYear; ?></small>
                    </article>
                    <article class="summary-card">
                        <span>Ahorros del ano</span>
                        <strong><?php echo htmlspecialchars(app_money($yearSummary['savings']), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </article>
                    <article class="summary-card">
                        <span>Gastos del ano</span>
                        <strong><?php echo htmlspecialchars(app_money($yearSummary['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong>
                    </article>
                </div>
            </section>

            <section class="months-grid">
                <?php foreach ($monthPanels as $panel): ?>
                    <article class="panel month-card">
                        <div class="month-card-head">
                            <h2><?php echo htmlspecialchars(app_month_name_es((int) $panel['month']), ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $selectedYear; ?></h2>
                            <a class="btn btn-ghost" href="?view=year&amp;year=<?php echo (int) $selectedYear; ?>&amp;month=<?php echo (int) $panel['month']; ?>">Ver historial</a>
                        </div>
                        <div class="period-values">
                            <div>
                                <span>Ahorros</span>
                                <strong><?php echo htmlspecialchars(app_money($panel['summary']['savings']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Gastos</span>
                                <strong><?php echo htmlspecialchars(app_money($panel['summary']['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Saldo</span>
                                <strong><?php echo htmlspecialchars(app_money($panel['summary']['balance']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="panel history-panel">
            <div class="section-head">
                <h2>Historial de <?php echo htmlspecialchars(app_month_name_es($selectedMonth), ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) $selectedYear; ?></h2>
                <form method="get" action="index.php" class="year-filter">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                    <label>
                        Mes
                        <select name="month" onchange="this.form.submit()">
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <option value="<?php echo $month; ?>" <?php echo $month === $selectedMonth ? 'selected' : ''; ?>><?php echo htmlspecialchars(app_month_name_es($month), ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        Ano
                        <select name="year" onchange="this.form.submit()">
                            <?php foreach ($availableYears as $yearOption): ?>
                                <option value="<?php echo (int) $yearOption; ?>" <?php echo $yearOption === $selectedYear ? 'selected' : ''; ?>><?php echo (int) $yearOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </div>

            <div class="month-inline-summary">
                <span>Saldo del mes: <strong><?php echo htmlspecialchars(app_money($monthSummary['balance']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                <span>Ahorros: <strong><?php echo htmlspecialchars(app_money($monthSummary['savings']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                <span>Gastos: <strong><?php echo htmlspecialchars(app_money($monthSummary['expenses']), ENT_QUOTES, 'UTF-8'); ?></strong></span>
            </div>

            <?php if ($selectedMonthTransactions === []): ?>
                <p class="empty-state">No hay movimientos en este periodo.</p>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($selectedMonthTransactions as $movement): ?>
                        <article class="movement <?php echo htmlspecialchars(app_transaction_class((string) $movement['type']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div>
                                <strong><?php echo htmlspecialchars(app_transaction_label((string) $movement['type']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p><?php echo htmlspecialchars((string) ($movement['description'] ?: 'Sin descripcion'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="movement-meta">
                                <span><?php echo htmlspecialchars(app_format_datetime((string) $movement['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo htmlspecialchars(app_money((float) $movement['amount']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <details class="movement-edit">
                                    <summary>Editar</summary>
                                    <form method="post" action="action.php" class="form-stack edit-form">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="edit_transaction">
                                        <input type="hidden" name="transaction_id" value="<?php echo (int) $movement['id']; ?>">
                                        <label>
                                            Tipo
                                            <select name="type">
                                                <option value="saving" <?php echo $movement['type'] === 'saving' ? 'selected' : ''; ?>>Ahorro</option>
                                                <option value="expense" <?php echo $movement['type'] === 'expense' ? 'selected' : ''; ?>>Gasto</option>
                                            </select>
                                        </label>
                                        <label>
                                            Monto
                                            <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string) $movement['amount'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </label>
                                        <label>
                                            Descripcion
                                            <input type="text" name="description" maxlength="500" value="<?php echo htmlspecialchars((string) ($movement['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        </label>
                                        <button class="btn btn-primary" type="submit">Guardar cambios</button>
                                    </form>
                                </details>
                                <form method="post" action="action.php" onsubmit="return confirm('Eliminar este movimiento?');" class="movement-actions">
                                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="transaction_id" value="<?php echo (int) $movement['id']; ?>">
                                    <button class="btn btn-danger" type="submit">Eliminar</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel history-panel">
            <div class="section-head">
                <h2>Movimientos recientes</h2>
            </div>

            <?php if ($recentTransactions === []): ?>
                <p class="empty-state">Todavia no has registrado movimientos.</p>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($recentTransactions as $movement): ?>
                        <article class="movement <?php echo htmlspecialchars(app_transaction_class((string) $movement['type']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div>
                                <strong><?php echo htmlspecialchars(app_transaction_label((string) $movement['type']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p><?php echo htmlspecialchars((string) ($movement['description'] ?: 'Sin descripcion'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="movement-meta">
                                <span><?php echo htmlspecialchars(app_format_datetime((string) $movement['created_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong><?php echo htmlspecialchars(app_money((float) $movement['amount']), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
</body>
</html>

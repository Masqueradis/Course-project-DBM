<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

redirectIfNotAdmin();

$stats = [
    'total_users' => 0,
    'total_products' => 0,
    'total_orders' => 0,
    'total_balance' => 0
];

try {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn();
    $stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM Products WHERE is_active = 1")->fetchColumn();
    $stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn();
    $stats['total_balance'] = $pdo->query("SELECT SUM(balance) FROM Users")->fetchColumn();
} catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ панель</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap layout-container">
        <?php $current_page = 'dashboard'; require_once __DIR__ . '/sidebar_admin.php'; ?>

        <div class="admin-main">
            <h1>Панель управления</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Пользователи</h3>
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Товары</h3>
                    <div class="stat-number"><?= $stats['total_products'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Заказы</h3>
                    <div class="stat-number"><?= $stats['total_orders'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Общий баланс</h3>
                    <div class="stat-number"><?= number_format((float)$stats['total_balance'], 0, '.', ' ') ?> руб.</div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
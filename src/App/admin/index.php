<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';

if (!isAdmin()) {
    header("Location: /index.php");
    exit;
}

// Базовая статистика
$stats = [
    'total_users' => 0,
    'total_products' => 0,
    'total_orders' => 0,
    'total_balance' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM Users");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total_products FROM Products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM Orders");
    $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

    $stmt = $pdo->query("SELECT SUM(balance) as total_balance FROM Users");
    $stats['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0;
} catch (PDOException $e) {
    // Игнорируем ошибки для демонстрации
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель</title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
    <?php require_once __DIR__ . '/../../App/blocks/header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Админ панель</h3>
            <ul>
                <li><a href="index.php" class="active">Дашборд</a></li>
                <li><a href="users.php">Пользователи</a></li>
                <li><a href="products.php">Товары</a></li>
                <li><a href="orders.php">Заказы</a></li>
                <li><a href="/App/account/index.php">Личный кабинет</a></li>
            </ul>
        </div>

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
                    <div class="stat-number"><?= number_format($stats['total_balance'], 2) ?> руб.</div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../App/blocks/footer.php'; ?>
</body>
</html>
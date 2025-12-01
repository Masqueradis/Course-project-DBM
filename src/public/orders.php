<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo();

// Получение заказов пользователя
$stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.order_item_id) as items_count,
           SUM(oi.quantity) as total_quantity
    FROM Orders o 
    LEFT JOIN OrderItems oi ON o.order_id = oi.order_id 
    WHERE o.user_id = ? 
    GROUP BY o.order_id 
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/blocks/header.php'; ?>

    <div class="account-container">
        <div class="sidebar">
            <h3>Личный кабинет</h3>
            <ul>
                <li><a href="account.php">Профиль</a></li>
                <li><a href="balance.php">Баланс</a></li>
                <li><a href="orders.php" class="active">Мои заказы</a></li>
                <li><a href="cart.php">Корзина</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="/admin.php">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Мои заказы</h1>

            <?php if (empty($orders)): ?>
                <div class="alert info">
                    <h3>Заказов пока нет</h3>
                    <p>Сделайте ваш первый заказ в нашем <a href="/catalog.php">каталоге</a></p>
                </div>
            <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div>
                                    <h3 style="margin: 0;">Заказ #<?= $order['order_id'] ?></h3>
                                    <div style="color: #666; font-size: 14px;">
                                        <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="order-status status-<?= $order['status'] ?>">
                                        <?php 
                                            $status_names = [
                                                'pending' => 'Ожидает подтверждения',
                                                'confirmed' => 'Подтвержден',
                                                'completed' => 'Завершен',
                                                'cancelled' => 'Отменен'
                                            ];
                                            echo $status_names[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <strong>Товаров:</strong> <?= $order['items_count'] ?> позиций
                                </div>
                                <div>
                                    <strong>Количество:</strong> <?= $order['total_quantity'] ?> шт.
                                </div>
                                <div>
                                    <strong>Сумма:</strong> <?= number_format($order['total_amount'], 2) ?> руб.
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <strong>Адрес:</strong> <?= htmlspecialchars($order['shipping_address']) ?>
                                </div>
                                <div>
                                    <strong>Оплата:</strong> 
                                    <?php 
                                        $payment_names = [
                                            'balance' => 'С баланса',
                                            'card' => 'Картой',
                                            'cash' => 'Наличные'
                                        ];
                                        echo $payment_names[$order['payment_method']] ?? $order['payment_method'];
                                    ?>
                                </div>
                            </div>
                            
                            <div style="border-top: 1px solid #eee; padding-top: 15px;">
                                <a href="order_details.php?id=<?= $order['order_id'] ?>" class="btn-small">Подробнее о заказе</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/blocks/footer.php'; ?>
</body>
</html>
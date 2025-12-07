<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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
    <title>Мои заказы</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'orders'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                <h1>Мои заказы</h1>

                <?php if (empty($orders)): ?>
                    <div class="alert info">
                        <h3>Заказов пока нет</h3>
                        <p>Сделайте ваш первый заказ в нашем <a href="/catalog.php" style="text-decoration: underline;">каталоге</a></p>
                    </div>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="content-card" style="padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                    <div>
                                        <h3 style="margin: 0;">Заказ #<?= $order['order_id'] ?></h3>
                                        <span style="color: #666; font-size: 13px;">
                                            от <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 13px; background:#f0f0f0;">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <strong>Товаров:</strong> <?= $order['items_count'] ?> шт.
                                    </div>
                                    <div class="form-group">
                                        <strong>Сумма:</strong> <span style="color: #0156FF; font-weight: bold;"><?= formatPrice($order['total_amount']) ?></span>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                                    <a href="order_details.php?id=<?= $order['order_id'] ?>" class="btn-primary" style="font-size: 14px; padding: 8px 20px;">Подробнее</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
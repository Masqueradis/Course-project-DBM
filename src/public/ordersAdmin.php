<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$stmt = $pdo->query("SELECT o.*, u.first_name, u.last_name, u.email, COUNT(oi.order_item_id) as items_count, SUM(oi.quantity) as total_quantity FROM Orders o JOIN Users u ON o.user_id = u.user_id LEFT JOIN OrderItems oi ON o.order_id = oi.order_id GROUP BY o.order_id ORDER BY o.created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заказы (Админ)</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'orders'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Управление заказами</h1>
                <div class="data-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Покупатель</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['order_id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['first_name'].' '.$order['last_name']) ?><br>
                                        <small><?= htmlspecialchars($order['email']) ?></small>
                                    </td>
                                    <td><?= formatPrice($order['total_amount']) ?></td>
                                    <td><?= ucfirst($order['status']) ?></td>
                                    <td>
                                        <a href="admin_order_details.php?id=<?= $order['order_id'] ?>" class="btn-small" style="background:#0156FF;">Управление</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
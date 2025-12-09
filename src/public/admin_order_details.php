<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$order = null;
$items = [];
$errors = [];
$success = '';

if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT o.*, u.first_name, u.last_name, u.email, u.phone FROM Orders o JOIN Users u ON o.user_id = u.user_id WHERE o.order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'confirm') {
            $pdo->prepare("UPDATE Orders SET status = 'confirmed', updated_at = NOW() WHERE order_id = ?")->execute([$order_id]);
            $success = "Заказ подтвержден."; $order['status'] = 'confirmed';
        } 
        elseif ($_POST['action'] === 'complete') {
            $pdo->prepare("UPDATE Orders SET status = 'completed', updated_at = NOW() WHERE order_id = ?")->execute([$order_id]);
            $success = "Заказ выполнен."; $order['status'] = 'completed';
        } 
        elseif ($_POST['action'] === 'cancel') {
            if ($order['status'] !== 'cancelled') {
                $pdo->beginTransaction();

                // 1. Возврат товара
                $stmt = $pdo->prepare("SELECT product_id, quantity FROM OrderItems WHERE order_id = ?");
                $stmt->execute([$order_id]);
                foreach ($stmt->fetchAll() as $item) {
                    $pdo->prepare("UPDATE Products SET stock = stock + ? WHERE product_id = ?")->execute([$item['quantity'], $item['product_id']]);
                }

                if ($order['payment_method'] === 'balance') {
                    $refund_amount = number_format($order['total_amount'], 2, '.', '');
                    
                    $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?")->execute([$refund_amount, $order['user_id']]);
                    
                    $pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, 'refund', ?)")
                        ->execute([$order['user_id'], $refund_amount, "Возврат за заказ #$order_id"]);
                }

                $pdo->prepare("UPDATE Orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?")->execute([$order_id]);
                
                $pdo->commit();
                $success = "Заказ отменен, средства возвращены.";
                $order['status'] = 'cancelled';
            } else {
                $errors[] = "Заказ уже отменен.";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = "Ошибка: " . $e->getMessage();
    }
}

if ($order) {
    $stmt = $pdo->prepare("SELECT oi.*, p.image_url, p.product_type FROM OrderItems oi LEFT JOIN Products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление заказом #<?= $order_id ?></title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'orders'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <div style="margin-bottom: 20px;">
                    <a href="ordersAdmin.php" class="btn-small" style="background:#6c757d; text-decoration: none;">&larr; Назад к списку</a>
                </div>

                <?php if (!$order): ?>
                    <div class="alert error"><h3>Заказ не найден</h3></div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <h1>Заказ #<?= $order['order_id'] ?></h1>
                        <span style="padding:5px 15px; background:#eee; border-radius:4px; font-weight:bold; height: fit-content;"><?= ucfirst($order['status']) ?></span>
                    </div>

                    <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
                    <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>

                    <div class="content-card">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                            <div>
                                <h3>Данные</h3>
                                <p><strong>Покупатель:</strong> <?= htmlspecialchars($order['first_name'].' '.$order['last_name']) ?> (<?= $order['email'] ?>)</p>
                                <p><strong>Дата:</strong> <?= $order['created_at'] ?></p>
                            </div>
                            <div>
                                <h3>Оплата</h3>
                                <p><strong>Метод:</strong> <?= $order['payment_method'] === 'balance' ? 'Баланс' : 'Нал/Карта' ?></p>
                                <p><strong>Сумма:</strong> <?= formatPrice($order['total_amount']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3>Действия</h3>
                        <div style="display: flex; gap: 15px;">
                            <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'completed'): ?>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="post"><button name="action" value="confirm" class="btn-primary" style="background:#ffc107; color:black;">Подтвердить</button></form>
                                <?php endif; ?>
                                <form method="post"><button name="action" value="complete" class="btn-primary btn-success">Завершить</button></form>
                                <form method="post" style="margin-left: auto;"><button name="action" value="cancel" class="btn-primary btn-danger" onclick="return confirm('Вернуть деньги и отменить?')">Отменить</button></form>
                            <?php else: ?>
                                <p>Действия недоступны для статуса <?= $order['status'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
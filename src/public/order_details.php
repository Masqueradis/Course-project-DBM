<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

redirectIfNotLoggedIn();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';
$order = null;

// 1. Пытаемся найти заказ
if ($order_id > 0) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
        FROM Orders o 
        JOIN Users u ON o.user_id = u.user_id 
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Если заказ есть, обрабатываем POST (отмену)
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if ($order['status'] === 'pending') {
        try {
            $pdo->beginTransaction();
            // Возврат товара
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM OrderItems WHERE order_id = ?");
            $stmt->execute([$order_id]);
            foreach ($stmt->fetchAll() as $item) {
                $pdo->prepare("UPDATE Products SET stock = stock + ? WHERE product_id = ?")->execute([$item['quantity'], $item['product_id']]);
            }
            // Возврат денег
            if ($order['payment_method'] === 'balance') {
                $refund_amount = number_format($order['total_amount'], 2, '.', '');
                
                $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?")
                    ->execute([$refund_amount, $user_id]);
                
                $pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, 'refund', ?)")
                    ->execute([$user_id, $refund_amount, "Возврат за заказ #$order_id"]);
            }
            // Статус
            $pdo->prepare("UPDATE Orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?")->execute([$order_id]);
            
            $pdo->commit();
            $success = "Заказ успешно отменен.";
            $order['status'] = 'cancelled'; // Обновляем визуально
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка: " . $e->getMessage();
        }
    } else {
        $errors[] = "Нельзя отменить заказ в этом статусе.";
    }
}

// Получаем товары, если заказ найден
$items = [];
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
    <title>Детали заказа</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'orders'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                
                <?php if (!$order): ?>
                    <div class="alert error">
                        <h3>Заказ не найден</h3>
                        <p>Возможно, у вас нет прав на просмотр этого заказа или ссылка некорректна (ID: <?= $order_id ?>).</p>
                        <a href="orders.php" class="btn-primary" style="margin-top:10px;">Вернуться к списку</a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h1>Заказ #<?= $order['order_id'] ?></h1>
                        <span class="btn-small" style="background:#eee; color:#333; cursor:default; font-size:14px; font-weight:bold;">
                            <?= ucfirst($order['status']) ?>
                        </span>
                    </div>

                    <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
                    <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>

                    <div class="content-card">
                        <div style="line-height: 1.8;">
                            <strong>Адрес:</strong> <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                            <strong>Оплата:</strong> <?= $order['payment_method'] === 'balance' ? 'С баланса' : 'При получении' ?><br>
                            <strong>Сумма:</strong> <span style="color: #0156FF; font-weight: bold;"><?= formatPrice($order['total_amount']) ?></span>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3 style="margin-top:0;">Товары</h3>
                        <div class="data-table">
                            <table>
                                <thead><tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <a href="product.php?id=<?= $item['product_id'] ?>" style="font-weight:bold;">
                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= formatPrice($item['unit_price']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><?= formatPrice($item['unit_price'] * $item['quantity']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($order['status'] === 'pending'): ?>
                        <div class="content-card" style="text-align: right; background: #fff3cd;">
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="btn-primary btn-danger" onclick="return confirm('Точно отменить?')">Отменить заказ</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
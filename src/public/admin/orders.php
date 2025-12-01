<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';

// Обработка действий с заказами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $order_id = intval($_POST['order_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'confirm':
                $stmt = $pdo->prepare("UPDATE Orders SET status = 'confirmed', updated_at = NOW() WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $success = "Заказ подтвержден";
                break;
                
            case 'complete':
                $stmt = $pdo->prepare("UPDATE Orders SET status = 'completed', updated_at = NOW() WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $success = "Заказ завершен";
                break;
                
            case 'cancel':
                // При отмене заказа возвращаем товары на склад и деньги пользователю
                try {
                    $pdo->beginTransaction();
                    
                    // Получаем информацию о заказе
                    $stmt = $pdo->prepare("SELECT user_id, total_amount FROM Orders WHERE order_id = ?");
                    $stmt->execute([$order_id]);
                    $order = $stmt->fetch();
                    
                    if ($order) {
                        // Возвращаем товары на склад
                        $stmt = $pdo->prepare("
                            UPDATE Products p 
                            JOIN OrderItems oi ON p.product_id = oi.product_id 
                            SET p.stock = p.stock + oi.quantity 
                            WHERE oi.order_id = ?
                        ");
                        $stmt->execute([$order_id]);
                        
                        // Возвращаем деньги
                        $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?");
                        $stmt->execute([$order['total_amount'], $order['user_id']]);
                        
                        // Добавляем транзакцию возврата
                        $stmt = $pdo->prepare("
                            INSERT INTO BalanceTransactions (user_id, amount, type, description) 
                            VALUES (?, ?, 'refund', ?)
                        ");
                        $stmt->execute([$order['user_id'], $order['total_amount'], "Возврат средств за отмененный заказ #$order_id"]);
                        
                        // Обновляем статус заказа
                        $stmt = $pdo->prepare("UPDATE Orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?");
                        $stmt->execute([$order_id]);
                        
                        $pdo->commit();
                        $success = "Заказ отменен, средства возвращены";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Ошибка при отмене заказа: " . $e->getMessage();
                }
                break;
        }
    }
}

// Получение списка заказов
$stmt = $pdo->query("
    SELECT o.*, 
           u.first_name, 
           u.last_name, 
           u.email,
           COUNT(oi.order_item_id) as items_count,
           SUM(oi.quantity) as total_quantity
    FROM Orders o 
    JOIN Users u ON o.user_id = u.user_id 
    LEFT JOIN OrderItems oi ON o.order_id = oi.order_id 
    GROUP BY o.order_id 
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление заказами</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/../blocks/header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Админ панель</h3>
            <ul>
                <li><a href="index.php">Дашборд</a></li>
                <li><a href="users.php">Пользователи</a></li>
                <li><a href="products.php">Товары</a></li>
                <li><a href="orders.php" class="active">Заказы</a></li>
                <li><a href="/account/index.php">Личный кабинет</a></li>
            </ul>
        </div>

        <div class="admin-main">
            <h1>Управление заказами</h1>

            <?php if ($success): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="orders-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Пользователь</th>
                            <th>Дата</th>
                            <th>Товары</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Адрес</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['order_id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br>
                                    <small><?= htmlspecialchars($order['email']) ?></small>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <?= $order['items_count'] ?> поз.<br>
                                    <small><?= $order['total_quantity'] ?> шт.</small>
                                </td>
                                <td><?= number_format($order['total_amount'], 2) ?> руб.</td>
                                <td>
                                    <span class="order-status status-<?= $order['status'] ?>">
                                        <?php 
                                            $status_names = [
                                                'pending' => 'Ожидает',
                                                'confirmed' => 'Подтвержден',
                                                'completed' => 'Завершен',
                                                'cancelled' => 'Отменен'
                                            ];
                                            echo $status_names[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars(mb_strimwidth($order['shipping_address'], 0, 50, '...')) ?></small>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <button type="submit" name="action" value="confirm" class="btn-small btn-success">Подтвердить</button>
                                            </form>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <button type="submit" name="action" value="cancel" class="btn-small btn-danger" onclick="return confirm('Отменить заказ? Средства будут возвращены пользователю.')">Отменить</button>
                                            </form>
                                        <?php elseif ($order['status'] === 'confirmed'): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                <button type="submit" name="action" value="complete" class="btn-small btn-success">Завершить</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="order_details.php?id=<?= $order['order_id'] ?>" class="btn-small">Детали</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../blocks/footer.php'; ?>
</body>
</html>
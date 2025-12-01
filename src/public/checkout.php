<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo();
$errors = [];
$success = '';

// Получение корзины
$stmt = $pdo->prepare("
    SELECT ci.*, p.name, p.model, p.price, p.stock, p.image_url, b.name as brand_name 
    FROM CartItems ci 
    JOIN Products p ON ci.product_id = p.product_id 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE ci.user_id = ? AND p.is_active = 1
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Проверка корзины
if (empty($cart_items)) {
    header("Location: /cart.php");
    exit;
}

// Подсчет суммы
$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
}

// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $payment_method = $_POST['payment_method'];
    
    if (empty($shipping_address)) {
        $errors[] = "Укажите адрес доставки";
    }
    
    // Проверяем баланс
    if ($user_info['balance'] < $total_amount) {
        $errors[] = "Недостаточно средств на балансе";
    }
    
    // Проверяем наличие товаров
    foreach ($cart_items as $item) {
        if ($item['stock'] < $item['quantity']) {
            $errors[] = "Товар '{$item['name']}' недоступен в нужном количестве";
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Создаем заказ
            $stmt = $pdo->prepare("
                INSERT INTO Orders (user_id, total_amount, shipping_address, payment_method, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $total_amount, $shipping_address, $payment_method]);
            $order_id = $pdo->lastInsertId();
            
            // Добавляем товары в заказ
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO OrderItems (order_id, product_id, quantity, unit_price, product_name, product_brand) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price'],
                    $item['name'],
                    $item['brand_name'] ?? ''
                ]);
                
                // Уменьшаем количество на складе
                $stmt = $pdo->prepare("UPDATE Products SET stock = stock - ? WHERE product_id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Списание средств
            $stmt = $pdo->prepare("UPDATE Users SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$total_amount, $user_id]);
            
            // Добавляем транзакцию
            $stmt = $pdo->prepare("
                INSERT INTO BalanceTransactions (user_id, amount, type, description) 
                VALUES (?, ?, 'purchase', ?)
            ");
            $stmt->execute([$user_id, -$total_amount, "Оплата заказа #$order_id"]);
            
            // Очищаем корзину
            $stmt = $pdo->prepare("DELETE FROM CartItems WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            
            $_SESSION['cart_count'] = 0;
            $success = "Заказ #$order_id успешно оформлен!";
            $cart_items = []; // Очищаем корзину для отображения
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка при оформлении заказа: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа</title>
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
                <li><a href="orders.php">Мои заказы</a></li>
                <li><a href="cart.php">Корзина</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="/admin.php">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Оформление заказа</h1>

            <?php if ($success): ?>
                <div class="alert success">
                    <?= $success ?>
                    <div style="margin-top: 15px;">
                        <a href="/orders.php" class="btn-primary">Перейти к заказам</a>
                        <a href="/catalog.php" class="btn-small">Продолжить покупки</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($cart_items)): ?>
                <div class="order-summary">
                    <h3>Состав заказа</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 60px; height: 60px; object-fit: contain;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #ccc; border-radius: 4px;">
                                        Нет фото
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <div style="font-weight: bold;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="color: #666; font-size: 14px;"><?= htmlspecialchars($item['brand_name'] ?? '') ?> <?= htmlspecialchars($item['model']) ?></div>
                                </div>
                            </div>
                            
                            <div style="text-align: right;">
                                <div><?= $item['quantity'] ?> × <?= number_format($item['price'], 2) ?> руб.</div>
                                <div style="font-weight: bold;"><?= number_format($item['quantity'] * $item['price'], 2) ?> руб.</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-summary">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <span>Товаров:</span>
                            <span><?= count($cart_items) ?> шт.</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <span>Общая сумма:</span>
                            <span style="font-weight: bold;"><?= number_format($total_amount, 2) ?> руб.</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                            <span>Ваш баланс:</span>
                            <span style="color: <?= $user_info['balance'] >= $total_amount ? '#28a745' : '#dc3545' ?>;">
                                <?= number_format($user_info['balance'], 2) ?> руб.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="checkout-form" style="background: white; padding: 30px; border-radius: 8px; margin-top: 30px;">
                    <h3>Данные для заказа</h3>
                    <form method="post">
                        <div class="form-group">
                            <label>Адрес доставки *</label>
                            <textarea name="shipping_address" rows="3" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" 
                                      placeholder="Укажите полный адрес доставки"><?= htmlspecialchars($_POST['shipping_address'] ?? $user_info['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Способ оплаты</label>
                            <select name="payment_method" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="balance" selected>Оплата с баланса</option>
                                <option value="card">Банковская карта</option>
                                <option value="cash">Наличные при получении</option>
                            </select>
                        </div>
                        
                        <div class="alert info">
                            <p>После оформления заказа средства будут зарезервированы на вашем балансе.</p>
                            <p>Заказ будет обработан администратором в ближайшее время.</p>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn-primary" style="width: 100%; padding: 15px;">
                            Подтвердить заказ на <?= number_format($total_amount, 2) ?> руб.
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/blocks/footer.php'; ?>
</body>
</html>
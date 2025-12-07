<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo();
$errors = [];
$success = '';

// 1. Получаем товары
$stmt = $pdo->prepare("
    SELECT ci.*, p.name, p.price, p.stock 
    FROM CartItems ci 
    JOIN Products p ON ci.product_id = p.product_id 
    WHERE ci.user_id = ? AND p.is_active = 1
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    header("Location: /cart.php");
    exit;
}

$total_amount = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
}
// Приводим к float для математики
$total_amount = round((float)$total_amount, 2);

// 2. Обработка заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $payment_method = $_POST['payment_method'];
    
    if (empty($shipping_address)) $errors[] = "Укажите адрес доставки";

    if (empty($errors)) {
        try {
            // НАЧАЛО ТРАНЗАКЦИИ
            $pdo->beginTransaction();

            // --- ШАГ А: БЛОКИРОВКА И СПИСАНИЕ ---
            if ($payment_method === 'balance') {
                // 1. Блокируем строку пользователя, чтобы никто не изменил баланс параллельно
                $stmt = $pdo->prepare("SELECT balance FROM Users WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$user_id]);
                $current_balance = (float)$stmt->fetchColumn();

                // 2. Проверяем хватает ли денег (в PHP, чтобы избежать глюков SQL типов)
                if ($current_balance < $total_amount) {
                    throw new Exception("Недостаточно средств. Ваш баланс: " . formatPrice($current_balance));
                }

                // 3. Вычисляем новый баланс
                $new_balance = $current_balance - $total_amount;

                // 4. ЯВНО устанавливаем новый баланс
                $stmt = $pdo->prepare("UPDATE Users SET balance = ? WHERE user_id = ?");
                $stmt->execute([$new_balance, $user_id]);

                // 5. Пишем историю
                $stmt = $pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, 'purchase', ?)");
                $stmt->execute([$user_id, -$total_amount, "Оплата заказа"]); 
            }

            // --- ШАГ Б: СОЗДАНИЕ ЗАКАЗА ---
            $stmt = $pdo->prepare("INSERT INTO Orders (user_id, total_amount, shipping_address, payment_method, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $total_amount, $shipping_address, $payment_method]);
            $order_id = $pdo->lastInsertId();

            // Если платили балансом, добавляем ID заказа в описание транзакции
            if ($payment_method === 'balance') {
                $pdo->prepare("UPDATE BalanceTransactions SET description = ? WHERE user_id = ? ORDER BY transaction_id DESC LIMIT 1")
                    ->execute(["Оплата заказа #$order_id", $user_id]);
            }

            // --- ШАГ В: СПИСАНИЕ ТОВАРОВ ---
            $stmt_item = $pdo->prepare("INSERT INTO OrderItems (order_id, product_id, quantity, unit_price, product_name, product_brand) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE Products SET stock = ? WHERE product_id = ?"); // Тоже явное обновление
            
            // Запрос для получения стока с блокировкой
            $stmt_get_stock = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ? FOR UPDATE");

            foreach ($cart_items as $item) {
                // Блокируем товар
                $stmt_get_stock->execute([$item['product_id']]);
                $current_stock = (int)$stmt_get_stock->fetchColumn();

                if ($current_stock < $item['quantity']) {
                    throw new Exception("Товар '{$item['name']}' закончился (осталось $current_stock).");
                }

                // Вставляем в заказ
                $stmt_item->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $item['name'], $item['brand_name'] ?? '']);
                
                // Обновляем склад (явное число)
                $new_stock = $current_stock - $item['quantity'];
                $stmt_stock->execute([$new_stock, $item['product_id']]);
            }

            // --- ШАГ Г: ЧИСТКА КОРЗИНЫ ---
            $pdo->prepare("DELETE FROM CartItems WHERE user_id = ?")->execute([$user_id]);

            // КОММИТ (ВСЕ СОХРАНЯЕМ)
            $pdo->commit();
            
            $_SESSION['cart_count'] = 0;
            $success = "Заказ #$order_id успешно оформлен!";
            $cart_items = []; 

        } catch (Exception $e) {
            // Если ошибка - откатываем всё
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Ошибка при оформлении: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оформление заказа</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'cart'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                <h1>Оформление заказа</h1>

                <?php if ($success): ?>
                    <div class="content-card" style="text-align: center; padding: 40px;">
                        <div style="font-size: 50px; margin-bottom: 20px; color: green;">✔</div>
                        <h2 style="margin-bottom: 15px;">Заказ принят!</h2>
                        <p style="color: #666; margin-bottom: 20px;"><?= $success ?></p>
                        <div style="display: flex; gap: 15px; justify-content: center;">
                            <a href="orders.php" class="btn-primary">Мои заказы</a>
                            <a href="catalog.php" class="btn-small" style="padding: 12px 20px; font-size: 14px;">В каталог</a>
                        </div>
                    </div>
                <?php else: ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert error"><?= implode('<br>', $errors) ?></div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 1fr 350px; gap: 30px;">
                        <div class="content-card">
                            <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Данные доставки</h3>
                            
                            <form method="post">
                                <div class="form-group">
                                    <label>Адрес доставки *</label>
                                    <textarea name="shipping_address" rows="3" required placeholder="Город, улица, дом, индекс"><?= htmlspecialchars($_POST['shipping_address'] ?? $user_info['address'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Способ оплаты</label>
                                    <select name="payment_method" style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="balance">Баланс (Доступно: <?= formatPrice($user_info['balance']) ?>)</option>
                                        <option value="card">Картой при получении</option>
                                        <option value="cash">Наличными</option>
                                    </select>
                                </div>
                                
                                <div class="alert info">
                                    <small>Списание произойдет сразу после нажатия кнопки.</small>
                                </div>

                                <button type="submit" name="place_order" class="btn-primary" style="width: 100%; padding: 15px; font-size: 1.1em;">
                                    Оплатить <?= formatPrice($total_amount) ?>
                                </button>
                            </form>
                        </div>

                        <div class="content-card" style="height: fit-content;">
                            <h3 style="margin-top: 0;">Ваш заказ</h3>
                            <?php foreach ($cart_items as $item): ?>
                                <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 10px 0;">
                                    <div>
                                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                        <small><?= $item['quantity'] ?> x <?= formatPrice($item['price']) ?></small>
                                    </div>
                                    <div><?= formatPrice($item['price'] * $item['quantity']) ?></div>
                                </div>
                            <?php endforeach; ?>
                            <div style="margin-top: 15px; text-align: right; font-weight: bold; font-size: 1.2em; color: #0156FF;">
                                Итого: <?= formatPrice($total_amount) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
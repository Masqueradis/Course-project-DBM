<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';
require_once __DIR__ . '/config/paths.php';

redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Обработка действий с корзиной
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $product_id = intval($_POST['product_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'add':
                $quantity = intval($_POST['quantity'] ?? 1);
                
                // Проверяем наличие товара
                $stmt = $pdo->prepare("SELECT stock, price, name FROM Products WHERE product_id = ? AND is_active = 1");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    $errors[] = "Товар не найден";
                } elseif ($product['stock'] < $quantity) {
                    $errors[] = "Недостаточно товара на складе";
                } else {
                    // Проверяем, есть ли уже товар в корзине
                    $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM CartItems WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $new_quantity = $existing['quantity'] + $quantity;
                        if ($new_quantity > $product['stock']) {
                            $errors[] = "Нельзя добавить больше товара, чем есть на складе";
                        } else {
                            $stmt = $pdo->prepare("UPDATE CartItems SET quantity = ?, updated_at = NOW() WHERE cart_item_id = ?");
                            $stmt->execute([$new_quantity, $existing['cart_item_id']]);
                            $success = "Товар обновлен в корзине";
                        }
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO CartItems (user_id, product_id, quantity) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $product_id, $quantity]);
                        $success = "Товар добавлен в корзину";
                    }
                }
                break;
                
            case 'update':
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if ($quantity <= 0) {
                    $stmt = $pdo->prepare("DELETE FROM CartItems WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$user_id, $product_id]);
                    $success = "Товар удален из корзины";
                } else {
                    // Проверяем наличие
                    $stmt = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ? AND is_active = 1");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    if ($product && $quantity <= $product['stock']) {
                        $stmt = $pdo->prepare("UPDATE CartItems SET quantity = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$quantity, $user_id, $product_id]);
                        $success = "Количество обновлено";
                    } else {
                        $errors[] = "Недостаточно товара на складе";
                    }
                }
                break;
                
            case 'remove':
                $stmt = $pdo->prepare("DELETE FROM CartItems WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $success = "Товар удален из корзины";
                break;
                
            case 'clear':
                $stmt = $pdo->prepare("DELETE FROM CartItems WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $success = "Корзина очищена";
                break;
        }
    }
}

// Получение содержимого корзины
$stmt = $pdo->prepare("
    SELECT ci.*, p.name, p.model, p.price, p.stock, p.image_url, b.name as brand_name 
    FROM CartItems ci 
    JOIN Products p ON ci.product_id = p.product_id 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE ci.user_id = ? AND p.is_active = 1
    ORDER BY ci.added_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчет общего количества и суммы
$total_quantity = 0;
$total_amount = 0;

foreach ($cart_items as $item) {
    $total_quantity += $item['quantity'];
    $total_amount += $item['quantity'] * $item['price'];
}

// Обновляем счетчик в сессии
$_SESSION['cart_count'] = $total_quantity;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина</title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css.css">
</head>
<body>
    <?php require_once __DIR__ . '/blocks/header.php'; ?>

    <div class="account-container">
        <div class="sidebar">
            <h3>Личный кабинет</h3>
            <ul>
                <li><a href="<?= ACCOUNT_URL ?>">Профиль</a></li>
                <li><a href="<?= BALANCE_URL ?>">Баланс</a></li>
                <li><a href="<?= ORDERS_URL ?>">Мои заказы</a></li>
                <li><a href="<?= CART_URL ?>" class="active">Корзина</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="<?= ADMIN_URL ?>">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Корзина</h1>

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

            <?php if (empty($cart_items)): ?>
                <div class="alert info">
                    <h3>Корзина пуста</h3>
                    <p>Добавьте товары из <a href="/catalog.php">каталога</a></p>
                </div>
            <?php else: ?>
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 80px; height: 80px; object-fit: contain;">
                                <?php else: ?>
                                    <div style="width: 80px; height: 80px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #ccc; border-radius: 4px; font-size: 12px;">
                                        Нет фото
                                    </div>
                                <?php endif; ?>
                                
                                <div class="cart-item-info">
                                    <div style="font-weight: bold;"><?= htmlspecialchars($item['name']) ?></div>
                                    <div style="color: #666; font-size: 14px;"><?= htmlspecialchars($item['brand_name'] ?? '') ?> <?= htmlspecialchars($item['model']) ?></div>
                                    <div style="color: #0156FF; font-weight: bold; margin-top: 5px;">
                                        <?= number_format($item['price'], 2) ?> руб. × <?= $item['quantity'] ?> = 
                                        <?= number_format($item['price'] * $item['quantity'], 2) ?> руб.
                                    </div>
                                    <div style="color: #28a745; font-size: 12px; margin-top: 5px;">
                                        В наличии: <?= $item['stock'] ?> шт.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="cart-item-actions">
                                <form method="post" class="quantity-controls">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    <button type="submit" name="action" value="update" class="quantity-btn" onclick="this.form.querySelector('input[name=quantity]').value = <?= $item['quantity'] - 1 ?>">-</button>
                                    <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" class="quantity-input" onchange="this.form.submit()">
                                    <button type="submit" name="action" value="update" class="quantity-btn" onclick="this.form.querySelector('input[name=quantity]').value = <?= $item['quantity'] + 1 ?>">+</button>
                                </form>
                                
                                <form method="post">
                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                    <button type="submit" name="action" value="remove" class="btn-small btn-danger" onclick="return confirm('Удалить товар из корзины?')">Удалить</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3>Итого</h3>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span>Товаров:</span>
                        <span><?= $total_quantity ?> шт.</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2em; font-weight: bold;">
                        <span>Общая сумма:</span>
                        <span><?= number_format($total_amount, 2) ?> руб.</span>
                    </div>
                    
                     <div style="display: flex; gap: 15px;">
                    <form method="post">
                        <button type="submit" name="action" value="clear" class="btn-danger" onclick="return confirm('Очистить всю корзину?')">Очистить корзину</button>
                    </form>
                    <a href="<?= CHECKOUT_URL ?>" class="btn-primary">Оформить заказ</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/blocks/footer.php'; ?>
</body>
</html>
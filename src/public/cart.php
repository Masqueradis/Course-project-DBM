<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// --- ЛОГИКА КОРЗИНЫ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? '/catalog.php';

    try {
        switch ($_POST['action']) {
            case 'add':
                $quantity = intval($_POST['quantity'] ?? 1);
                if ($quantity < 1) $quantity = 1;

                // 1. Проверяем реальный сток товара
                $stmt = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ? AND is_active = 1");
                $stmt->execute([$product_id]);
                $product_stock = $stmt->fetchColumn();

                if ($product_stock === false) {
                    // Товар не найден
                    break;
                }

                // 2. Проверяем, есть ли УЖЕ этот товар в корзине
                $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM CartItems WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_item) {
                    // ОБНОВЛЕНИЕ: Складываем старое кол-во + новое
                    $new_qty = $existing_item['quantity'] + $quantity;
                    
                    // Не даем добавить больше, чем есть на складе
                    if ($new_qty > $product_stock) {
                        $new_qty = $product_stock;
                    }

                    $pdo->prepare("UPDATE CartItems SET quantity = ? WHERE cart_item_id = ?")
                        ->execute([$new_qty, $existing_item['cart_item_id']]);
                } else {
                    // ВСТАВКА: Если товара в корзине нет
                    if ($quantity > $product_stock) {
                        $quantity = $product_stock;
                    }
                    $pdo->prepare("INSERT INTO CartItems (user_id, product_id, quantity) VALUES (?, ?, ?)")
                        ->execute([$user_id, $product_id, $quantity]);
                }
                
                // Перенаправляем назад
                header("Location: " . $redirect_url);
                exit;

            case 'update':
                $quantity = intval($_POST['quantity']);
                if ($quantity > 0) {
                    $stmt = $pdo->prepare("SELECT stock FROM Products WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    $stock = $stmt->fetchColumn();
                    
                    if ($quantity <= $stock) {
                        $pdo->prepare("UPDATE CartItems SET quantity = ? WHERE user_id = ? AND product_id = ?")->execute([$quantity, $user_id, $product_id]);
                    }
                }
                break;

            case 'remove':
                $pdo->prepare("DELETE FROM CartItems WHERE user_id = ? AND product_id = ?")->execute([$user_id, $product_id]);
                break;

            case 'clear':
                $pdo->prepare("DELETE FROM CartItems WHERE user_id = ?")->execute([$user_id]);
                break;
        }
    } catch (Exception $e) {
        // Логирование ошибок
    }
}

// --- ВЫВОД КОРЗИНЫ ---
$stmt = $pdo->prepare("
    SELECT ci.*, p.name, p.model, p.price, p.stock, p.image_url, b.name as brand_name 
    FROM CartItems ci 
    JOIN Products p ON ci.product_id = p.product_id 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE ci.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_amount = 0;
$total_qty = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
    $total_qty += $item['quantity'];
}
$_SESSION['cart_count'] = $total_qty;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Корзина</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'cart'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                <h1>Корзина покупок</h1>

                <?php if (empty($cart_items)): ?>
                    <div class="content-card" style="text-align: center; padding: 60px 20px;">
                        <div style="font-size: 60px; margin-bottom: 20px;">🛒</div>
                        <h3>Ваша корзина пуста</h3>
                        <p style="color: #777; margin-bottom: 30px;">Посмотрите наш каталог, там много интересного!</p>
                        <a href="/catalog.php" class="btn-primary">Перейти в каталог</a>
                    </div>
                <?php else: ?>
                    <div class="content-card">
                        <div class="data-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>Цена</th>
                                        <th>Кол-во</th>
                                        <th>Итого</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr>
                                            <td style="display: flex; gap: 20px; align-items: center;">
                                                <?php if($item['image_url']): ?>
                                                    <img src="<?= $item['image_url'] ?>" class="img-thumbnail" style="width: 70px; height: 70px;">
                                                <?php else: ?>
                                                    <div style="width:70px; height:70px; background:#f5f5f5; border-radius:4px; display:flex; align-items:center; justify-content:center; font-size:10px; color:#999;">Нет фото</div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: bold; font-size: 15px;"><?= htmlspecialchars($item['name']) ?></div>
                                                    <small style="color: #777;"><?= htmlspecialchars($item['brand_name'] . ' ' . $item['model']) ?></small>
                                                </div>
                                            </td>
                                            <td><?= formatPrice($item['price']) ?></td>
                                            <td>
                                                <form method="post" class="quantity-wrapper"> <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                                    
                                                    <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>" class="quantity-btn">-</button>
                                                    
                                                    <input type="text" value="<?= $item['quantity'] ?>" readonly class="quantity-input-styled">
                                                    
                                                    <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>" class="quantity-btn">+</button>
                                                </form>
                                            </td>
                                            <td style="font-weight: bold; color: #0156FF; font-size: 16px;">
                                                <?= formatPrice($item['price'] * $item['quantity']) ?>
                                            </td>
                                            <td>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                                    <button type="submit" class="btn-small btn-danger" title="Удалить">✕</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                            <form method="post">
                                <button type="submit" name="action" value="clear" class="btn-small btn-danger" onclick="return confirm('Очистить корзину?')">Очистить корзину</button>
                            </form>
                            
                            <div style="text-align: right;">
                                <div style="font-size: 1.1em; margin-bottom: 5px; color: #666;">Итого к оплате:</div>
                                <div style="font-size: 2em; font-weight: bold; color: #0156FF; margin-bottom: 20px;"><?= formatPrice($total_amount) ?></div>
                                <a href="checkout.php" class="btn-primary" style="font-size: 1.1em; padding: 12px 30px;">Оформить заказ</a>
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
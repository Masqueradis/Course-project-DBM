<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Получаем ID товара
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. ПОЛУЧЕНИЕ ДАННЫХ О ТОВАРЕ
$stmt = $pdo->prepare("
    SELECT p.*, b.name as brand_name 
    FROM Products p 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE p.product_id = ? AND p.is_active = 1
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: catalog.php");
    exit;
}

// 2. ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ (ЧТОБЫ НЕ БЫЛО ОШИБОК UNDEFINED)
$can_comment = false;
$existing_review = null;
$review_success = '';
$review_error = '';
$cart_msg = '';

// Если пользователь авторизован, загружаем его статус и отзыв
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];

    // А) Проверяем, не заблокирован ли пользователь
    $stmt_u = $pdo->prepare("SELECT is_active FROM Users WHERE user_id = ?");
    $stmt_u->execute([$user_id]);
    $can_comment = (bool)$stmt_u->fetchColumn(); // Приводим к boolean (true/false)

    // Б) Проверяем, есть ли уже отзыв
    $stmt_check = $pdo->prepare("SELECT * FROM Reviews WHERE user_id = ? AND product_id = ?");
    $stmt_check->execute([$user_id, $product_id]);
    $existing_review = $stmt_check->fetch(PDO::FETCH_ASSOC);
}

// 3. ПОЛУЧЕНИЕ СПЕЦИФИЧЕСКИХ ХАРАКТЕРИСТИК
$table_map = [
    'processor'    => 'Processors',
    'videocard'    => 'Videocards',
    'motherboard'  => 'Motherboards',
    'ram'          => 'RAM',
    'ssd'          => 'SSDs',
    'hdd'          => 'HDDs',
    'powersupply'  => 'PowerSupplies',
    'cooling'      => 'Cooling',
    'computercase' => 'ComputerCases'
];

$specs = [];
$type = strtolower($product['product_type']);

if (array_key_exists($type, $table_map)) {
    $table_name = $table_map[$type];
    $stmt_specs = $pdo->prepare("SELECT * FROM $table_name WHERE product_id = ?");
    $stmt_specs->execute([$product_id]);
    $specs_data = $stmt_specs->fetch(PDO::FETCH_ASSOC);
    if ($specs_data) {
        unset($specs_data['id'], $specs_data['product_id'], $specs_data['brand_id']);
        $specs = $specs_data;
    }
}

// 4. ОБРАБОТКА ДОБАВЛЕНИЯ В КОРЗИНУ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    $qty = intval($_POST['quantity'] ?? 1);
    if ($qty > 0 && $qty <= $product['stock']) {
        addToCart($_SESSION['user_id'], $product_id, $qty);
        $cart_msg = "Товар добавлен в корзину!";
    } else {
        $cart_msg = "Ошибка: недопустимое количество.";
    }
}

// 5. ОБРАБОТКА ОТПРАВКИ/РЕДАКТИРОВАНИЯ ОТЗЫВА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (isLoggedIn()) {
        if (!$can_comment) {
            $review_error = "Ваш аккаунт заблокирован.";
        } else {
            $rating = intval($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');

            if ($rating < 1 || $rating > 5) {
                $review_error = "Пожалуйста, поставьте оценку.";
            } elseif (empty($comment)) {
                $review_error = "Текст отзыва не может быть пустым.";
            } else {
                try {
                    if ($existing_review) {
                        // UPDATE
                        $stmt_update = $pdo->prepare("
                            UPDATE Reviews 
                            SET rating = ?, comment = ?, is_approved = 0, created_at = NOW() 
                            WHERE review_id = ?
                        ");
                        $stmt_update->execute([$rating, $comment, $existing_review['review_id']]);
                        $review_success = "Ваш отзыв обновлен и отправлен на повторную модерацию.";
                        
                        // Обновляем данные в памяти для формы
                        $existing_review['rating'] = $rating;
                        $existing_review['comment'] = $comment;
                    } else {
                        // INSERT
                        $checkBuy = $pdo->prepare("
                            SELECT COUNT(*) FROM OrderItems oi 
                            JOIN Orders o ON oi.order_id = o.order_id 
                            WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'completed'
                        ");
                        $checkBuy->execute([$_SESSION['user_id'], $product_id]);
                        $is_verified = $checkBuy->fetchColumn() > 0 ? 1 : 0;

                        $stmt_insert = $pdo->prepare("
                            INSERT INTO Reviews (user_id, product_id, rating, comment, is_approved, is_verified_purchase, created_at) 
                            VALUES (?, ?, ?, ?, 0, ?, NOW())
                        ");
                        $stmt_insert->execute([$_SESSION['user_id'], $product_id, $rating, $comment, $is_verified]);
                        $review_success = "Ваш отзыв успешно добавлен и ожидает проверки.";
                        
                        // Создаем псевдо-массив, чтобы форма переключилась в режим редактирования
                        $existing_review = ['rating' => $rating, 'comment' => $comment];
                    }
                } catch (PDOException $e) {
                    $review_error = "Ошибка сохранения: " . $e->getMessage();
                }
            }
        }
    } else {
        header("Location: login.php");
        exit;
    }
}

// 6. ПОЛУЧЕНИЕ СПИСКА ОТЗЫВОВ (Только одобренные)
$stmt_reviews = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name, u.avatar_url 
    FROM Reviews r 
    JOIN Users u ON r.user_id = u.user_id 
    WHERE r.product_id = ? AND r.is_approved = 1 
    ORDER BY r.created_at DESC
");
$stmt_reviews->execute([$product_id]);
$reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

// Средний рейтинг
$avg_rating = 0;
if (count($reviews) > 0) {
    $sum = 0;
    foreach ($reviews as $r) $sum += $r['rating'];
    $avg_rating = round($sum / count($reviews), 1);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> - Computer Shop</title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        .product-container { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px; }
        .product-image img { width: 100%; border-radius: 8px; border: 1px solid #eee; }
        .product-info h1 { margin-top: 0; font-size: 28px; }
        .price-tag { font-size: 28px; color: #0156FF; font-weight: bold; margin: 20px 0; }
        .sku { color: #666; font-size: 14px; }
        .stock-status { font-weight: bold; margin-bottom: 20px; }
        .in-stock { color: #28a745; }
        .out-of-stock { color: #dc3545; }
        .specs-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .specs-table th, .specs-table td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
        .specs-table th { width: 40%; color: #666; font-weight: normal; }
        .review-item { border-bottom: 1px solid #eee; padding: 20px 0; }
        .review-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .review-author { font-weight: bold; }
        .review-date { font-size: 12px; color: #999; }
        .verified-badge { background: #e6fffa; color: #00b894; padding: 2px 6px; font-size: 11px; border-radius: 4px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div style="padding: 20px 0; color: #666; font-size: 14px;">
            <a href="catalog.php" style="color: #666;">Каталог</a> > 
            <?= ucfirst($product['product_type']) ?> > 
            <?= htmlspecialchars($product['name']) ?>
        </div>

        <?php if ($cart_msg): ?>
            <div class="alert success"><?= $cart_msg ?></div>
        <?php endif; ?>

        <div class="product-container">
            <div class="product-image">
                <?php if ($product['image_url']): ?>
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                <?php else: ?>
                    <div style="width: 100%; height: 400px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; color: #ccc;">Нет фото</div>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <div class="sku">Артикул: <?= htmlspecialchars($product['sku']) ?></div>
                <div class="sku">Бренд: <?= htmlspecialchars($product['brand_name']) ?></div>
                <div class="price-tag"><?= formatPrice($product['price']) ?></div>
                <div class="stock-status <?= $product['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                    <?= $product['stock'] > 0 ? "В наличии ({$product['stock']} шт.)" : "Нет в наличии" ?>
                </div>
                <p><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>
                <?php if ($product['stock'] > 0): ?>
                    <form method="post" style="display: flex; gap: 10px; align-items: center;">
                        <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" style="width: 60px; padding: 10px;">
                        <button type="submit" name="add_to_cart" class="btn-primary" style="padding: 10px 30px;">В корзину</button>
                    </form>
                <?php else: ?>
                    <button class="btn-primary" disabled style="background: #ccc; cursor: not-allowed;">В корзину</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <h3 class="section-title">Характеристики</h3>
            <table class="specs-table">
                <tbody>
                    <tr><th>Производитель</th><td><?= htmlspecialchars($product['brand_name']) ?></td></tr>
                    <tr><th>Модель</th><td><?= htmlspecialchars($product['model']) ?></td></tr>
                    <?php foreach ($specs as $key => $value): ?>
                        <tr><th><?= ucfirst(str_replace('_', ' ', $key)) ?></th><td><?= htmlspecialchars($value) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="content-card" style="margin-top: 30px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h3 class="section-title">Отзывы (<?= count($reviews) ?>)</h3>
                <?php if($avg_rating > 0): ?>
                    <div style="font-size: 18px; color: #ffc107;">Средняя оценка: <b><?= $avg_rating ?></b> / 5</div>
                <?php endif; ?>
            </div>

            <?php if ($review_success): ?><div class="alert success">✅ <?= $review_success ?></div><?php endif; ?>
            <?php if ($review_error): ?><div class="alert error">❌ <?= $review_error ?></div><?php endif; ?>

            <?php if (isLoggedIn()): ?>
                <?php if ($can_comment): ?>
                    <form method="post" class="review-form" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #eee;">
                        <h4 style="margin-top: 0;">
                            <?= $existing_review ? 'Редактировать ваш отзыв' : 'Оставить отзыв' ?>
                        </h4>
                        
                        <?php if($existing_review && !$review_success): ?>
                             <div style="font-size: 13px; color: #666; margin-bottom: 15px;">Вы уже оставляли отзыв. Вы можете изменить его ниже.</div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Ваша оценка</label>
                            <div class="star-rating">
                                <?php $current_rate = $existing_review['rating'] ?? 0; ?>
                                <?php for($i=5; $i>=1; $i--): ?>
                                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $current_rate == $i ? 'checked' : '' ?> required>
                                    <label for="star<?= $i ?>" title="<?= $i ?> звезд">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Комментарий</label>
                            <textarea name="comment" rows="3" required placeholder="Поделитесь впечатлениями..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"><?= htmlspecialchars($existing_review['comment'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" name="submit_review" class="btn-primary">
                            <?= $existing_review ? 'Обновить отзыв' : 'Отправить отзыв' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert error">🚫 Ваш аккаунт ограничен. Вы не можете оставлять отзывы.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert info"><a href="login.php">Войдите</a>, чтобы оставить отзыв.</div>
            <?php endif; ?>

            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                    <p style="color: #777; text-align: center; padding: 20px;">Отзывов пока нет.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <?php if($rev['avatar_url']): ?>
                                    <img src="<?= htmlspecialchars($rev['avatar_url']) ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 30px; height: 30px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px;"><?= strtoupper(substr($rev['first_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <span class="review-author"><?= htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']) ?></span>
                                    <?php if($rev['is_verified_purchase']): ?>
                                        <span class="verified-badge">✔ Купил товар</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="color: #ffc107; margin-bottom: 5px;"><?= str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']) ?></div>
                            <div class="review-text"><?= nl2br(htmlspecialchars($rev['comment'])) ?></div>
                            <div class="review-date"><?= date('d.m.Y H:i', strtotime($rev['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (!isset($_GET['id'])) {
    header("Location: /catalog.php");
    exit;
}

$product_id = intval($_GET['id']);
$user_info = isLoggedIn() ? getUserInfo() : null;

// 1. Получение данных о товаре
$stmt = $pdo->prepare("SELECT p.*, b.name as brand_name FROM Products p LEFT JOIN Brands b ON p.brand_id = b.brand_id WHERE p.product_id = ? AND p.is_active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='container-wrap' style='padding:50px;'><h2>Товар не найден</h2><a href='/catalog.php'>Вернуться в каталог</a></div>";
    exit;
}

// 2. Проверяем, оставлял ли текущий пользователь отзыв
$existing_review = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM Reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $existing_review = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. Обработка формы отзыва
$review_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }

    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($rating < 1 || $rating > 5) {
        $review_message = "<div class='alert error'>Пожалуйста, поставьте оценку.</div>";
    } elseif (empty($comment)) {
        $review_message = "<div class='alert error'>Напишите комментарий.</div>";
    } else {
        try {
            // Проверка на покупку (Verified Purchase)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM OrderItems oi 
                JOIN Orders o ON oi.order_id = o.order_id 
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'completed'
            ");
            $stmt->execute([$user_id, $product_id]);
            $is_verified = $stmt->fetchColumn() > 0 ? 1 : 0;

            // ON DUPLICATE KEY UPDATE: Если отзыв уже есть, обновляем его
            $stmt = $pdo->prepare("
                INSERT INTO Reviews (user_id, product_id, rating, comment, is_verified_purchase, is_approved, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE 
                    rating = VALUES(rating), 
                    comment = VALUES(comment), 
                    is_approved = 0, -- Отправляем на повторную модерацию при изменении
                    created_at = NOW()
            ");
            $stmt->execute([$user_id, $product_id, $rating, $comment, $is_verified]);
            
            // Обновляем страницу, чтобы показать сообщение
            header("Location: product.php?id=$product_id&review_success=1");
            exit;

        } catch (Exception $e) {
            $review_message = "<div class='alert error'>Ошибка: " . $e->getMessage() . "</div>";
        }
    }
}

if (isset($_GET['review_success'])) {
    $review_message = "<div class='alert success'>Ваш отзыв успешно сохранен и отправлен на модерацию.</div>";
    // Обновим данные отзыва, чтобы отобразить новые значения в форме
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM Reviews WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $existing_review = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// 4. Характеристики (Карта полей)
$specs_map = [
    'processor' => ['table' => 'Processors', 'title' => 'Процессор', 'fields' => ['socket' => ['Сокет', ''], 'cores' => ['Ядра', ''], 'threads' => ['Потоки', ''], 'base_frequency' => ['Базовая частота', ' ГГц'], 'max_frequency' => ['Макс. частота', ' ГГц'], 'cache' => ['Кэш', ' МБ'], 'tdp' => ['TDP', ' Вт']]],
    'videocard' => ['table' => 'Videocards', 'title' => 'Видеокарта', 'fields' => ['memory_size' => ['Память', ' ГБ'], 'memory_type' => ['Тип памяти', ''], 'gpu_frequency' => ['Частота GPU', ' ГГц'], 'tdp' => ['TDP', ' Вт']]],
    'motherboard' => ['table' => 'Motherboards', 'title' => 'Материнская плата', 'fields' => ['socket' => ['Сокет', ''], 'chipset' => ['Чипсет', ''], 'form_factor' => ['Форм-фактор', ''], 'memory_slots' => ['Слоты памяти', ''], 'max_memory' => ['Макс. память', ' ГБ']]],
    'ram' => ['table' => 'RAM', 'title' => 'ОЗУ', 'fields' => ['capacity' => ['Объем', ' ГБ'], 'type' => ['Тип', ''], 'frequency' => ['Частота', ' МГц'], 'latency' => ['Тайминги', '']]],
    'ssd' => ['table' => 'SSDs', 'title' => 'SSD', 'fields' => ['capacity' => ['Объем', ' ГБ'], 'interface' => ['Интерфейс', ''], 'read_speed' => ['Чтение', ' МБ/с'], 'write_speed' => ['Запись', ' МБ/с']]],
    'hdd' => ['table' => 'HDDs', 'title' => 'HDD', 'fields' => ['capacity' => ['Объем', ' ГБ'], 'rpm' => ['Скорость', ' об/мин'], 'form_factor' => ['Форм-фактор', '']]],
    'powersupply' => ['table' => 'PowerSupplies', 'title' => 'Блок питания', 'fields' => ['power' => ['Мощность', ' Вт'], 'efficiency' => ['Сертификат', ''], 'modular' => ['Модульный', '']]],
    'cooling' => ['table' => 'Cooling', 'title' => 'Охлаждение', 'fields' => ['type' => ['Тип', ''], 'fan_size' => ['Вентилятор', ' мм'], 'max_tdp' => ['Макс. TDP', ' Вт']]],
    'case' => ['table' => 'ComputerCases', 'title' => 'Корпус', 'fields' => ['form_factor' => ['Форм-фактор', ''], 'color' => ['Цвет', '']]]
];

$specs_data = null;
$specs_config = null;
if (isset($specs_map[$product['product_type']])) {
    $specs_config = $specs_map[$product['product_type']];
    $table = $specs_config['table'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $specs_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 5. Получение списка отзывов
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name 
    FROM Reviews r 
    JOIN Users u ON r.user_id = u.user_id 
    WHERE r.product_id = ? AND r.is_approved = 1 
    ORDER BY r.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> | Магазин</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
<?php require_once __DIR__ . '/header.php'; ?>

<div class="container-wrap" style="margin-top: 40px; margin-bottom: 40px;">
    
    <div class="product-main">
        <div class="product-gallery">
            <?php if (!empty($product['image_url'])): ?>
                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <div class="product-image-placeholder-large">
                    <span>Нет изображения</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <div class="product-brand"><?= htmlspecialchars($product['brand_name'] ?? 'Бренд не указан') ?></div>
            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
            <div style="color: #777; margin-bottom: 15px; font-size: 14px;">
                Артикул: <?= htmlspecialchars($product['sku']) ?> | Модель: <?= htmlspecialchars($product['model']) ?>
            </div>
            
            <div class="stock-status <?= $product['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                <?= $product['stock'] > 0 ? "В наличии ({$product['stock']} шт.)" : "Нет в наличии" ?>
            </div>

            <div class="product-price-large"><?= formatPrice($product['price']) ?></div>

            <?php if ($product['stock'] > 0): ?>
                <form action="cart.php" method="POST" style="display: flex; gap: 10px; max-width: 300px;">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock'] ?>" style="width: 80px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    
                    <?php if (isLoggedIn()): ?>
                        <button type="submit" class="btn-primary" style="flex: 1; font-size: 16px;">В корзину</button>
                    <?php else: ?>
                        <a href="login.php" class="btn-primary" style="flex: 1; text-align: center; background-color: #ccc;">Войти для покупки</a>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <?php endif; ?>
        </div>
    </div>

    <div class="content-card">
        <h2 class="section-title">Подробная информация</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            <div>
                <h3 style="margin-bottom: 15px; color: #555;">Характеристики</h3>
                <table class="specs-table">
                    <tbody>
                        <tr><th>Бренд</th><td><?= htmlspecialchars($product['brand_name'] ?? '-') ?></td></tr>
                        <tr><th>Модель</th><td><?= htmlspecialchars($product['model']) ?></td></tr>
                        
                        <?php if ($specs_data && $specs_config): ?>
                            <?php foreach ($specs_config['fields'] as $key => $info): ?>
                                <?php if (isset($specs_data[$key]) && $specs_data[$key] !== '' && $specs_data[$key] !== null): ?>
                                    <tr>
                                        <th><?= htmlspecialchars($info[0]) ?></th>
                                        <td>
                                            <?php 
                                            $val = $specs_data[$key];
                                            // Обработка булевых значений (модульный БП, wifi и т.д.)
                                            if ($val === '1' || $val === 1) echo 'Да';
                                            elseif ($val === '0' || $val === 0) echo 'Нет';
                                            else echo htmlspecialchars($val) . htmlspecialchars($info[1]); 
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <h3 style="margin-bottom: 15px; color: #555;">Описание</h3>
                <div style="line-height: 1.8; color: #444;">
                    <?= nl2br(htmlspecialchars($product['description'] ?? 'Описание отсутствует.')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card" style="margin-top: 30px;">
        <h3 class="section-title">Отзывы (<?= count($reviews) ?>)</h3>

        <?php if (isLoggedIn()): ?>
            <?php 
                // Получаем свежий статус пользователя из БД (на случай, если его только что забанили)
                $currentUserCheck = $pdo->prepare("SELECT is_active FROM Users WHERE user_id = ?");
                $currentUserCheck->execute([$_SESSION['user_id']]);
                $isActive = $currentUserCheck->fetchColumn();
            ?>

            <?php if ($isActive): ?>
                <form method="post" class="review-form" style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h4 style="margin-top: 0;">Оставить отзыв</h4>
                    <div class="form-group">
                        <label>Ваша оценка</label>
                        <div class="star-rating">
                            <?php for($i=5; $i>=1; $i--): ?>
                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                <label for="star<?= $i ?>" title="<?= $i ?> звезд">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Комментарий</label>
                        <textarea name="comment" rows="3" required placeholder="Поделитесь впечатлениями о товаре"></textarea>
                    </div>
                    <button type="submit" name="submit_review" class="btn-primary">Отправить отзыв</button>
                </form>
            <?php else: ?>
                <div class="alert error" style="margin-bottom: 30px;">
                    🚫 <strong>Ваш аккаунт ограничен.</strong><br>
                    Вы не можете оставлять комментарии и отзывы. Пожалуйста, свяжитесь с поддержкой.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert info" style="margin-bottom: 30px;">
                <a href="login.php">Войдите</a>, чтобы оставить отзыв.
            </div>
        <?php endif; ?>

        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <p style="color: #777;">Отзывов пока нет. Будьте первым!</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author"><?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?></span>
                            <div class="review-stars">
                                <?= str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']) ?>
                            </div>
                        </div>
                        <div class="review-date"><?= date('d.m.Y', strtotime($review['created_at'])) ?></div>
                        <div class="review-text"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
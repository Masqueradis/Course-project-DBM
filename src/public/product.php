<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';

if (!isset($_GET['id'])) {
    header("Location: /catalog.php");
    exit;
}

$product_id = intval($_GET['id']);
$user_info = isLoggedIn() ? getUserInfo() : null;

// Получение информации о товаре
$stmt = $pdo->prepare("CALL GetProductDetails(?)");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor(); // Важно: закрываем курсор после хранимой процедуры

if (!$product) {
    header("Location: /catalog.php");
    exit;
}

// Получение отзывов
$stmt = $pdo->prepare("CALL GetProductReviews(?)");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor(); // Закрываем курсор

// Обработка добавления отзыва
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    if (!isLoggedIn()) {
        $errors[] = "Для добавления отзыва необходимо войти в систему";
    } else {
        $rating = intval($_POST['rating']);
        $title = trim($_POST['title']);
        $comment = trim($_POST['comment']);
        
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Оценка должна быть от 1 до 5";
        }
        
        if (empty($title)) {
            $errors[] = "Заголовок отзыва обязателен";
        }
        
        if (empty($errors)) {
            // Проверяем, не оставлял ли пользователь уже отзыв
            $stmt = $pdo->prepare("SELECT review_id FROM Reviews WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            
            if ($stmt->fetch()) {
                $errors[] = "Вы уже оставляли отзыв на этот товар";
            } else {
                $stmt = $pdo->prepare("INSERT INTO Reviews (user_id, product_id, rating, title, comment) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$_SESSION['user_id'], $product_id, $rating, $title, $comment])) {
                    $success = "Отзыв успешно добавлен";
                    // Обновляем список отзывов
                    $stmt = $pdo->prepare("CALL GetProductReviews(?)");
                    $stmt->execute([$product_id]);
                    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                } else {
                    $errors[] = "Ошибка при добавлении отзыва";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?></title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        .product-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .product-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .product-info h1 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .product-price {
            font-size: 2em;
            font-weight: bold;
            color: #0156FF;
            margin-bottom: 20px;
        }
        
        .product-meta {
            margin-bottom: 20px;
        }
        
        .product-meta div {
            margin-bottom: 10px;
            color: #666;
        }
        
        .reviews-section {
            margin-top: 50px;
        }
        
        .review-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .review-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .review-author {
            font-weight: bold;
        }
        
        .review-rating {
            color: #ffc107;
        }
        
        .review-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .rating-stars {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .rating-stars input {
            display: none;
        }
        
        .rating-stars label {
            cursor: pointer;
            font-size: 24px;
            color: #ddd;
        }
        
        .rating-stars input:checked ~ label,
        .rating-stars label:hover,
        .rating-stars label:hover ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/blocks/header.php'; ?>

    <div class="product-container">
        <!-- Хлебные крошки -->
        <div style="margin-bottom: 20px;">
            <a href="/catalog.php">Каталог</a> &gt; 
            <span><?= htmlspecialchars($product['name']) ?></span>
        </div>

        <div class="product-details">
            <div>
                <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-fixed" style="margin: 0 auto; display: block;">
                <?php else: ?>
                    <div class="product-image-placeholder" style="margin: 0 auto;">
                        Изображение отсутствует
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="product-info">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <div class="product-price"><?= number_format($product['price'], 2) ?> руб.</div>
                
                <div class="product-meta">
                    <div><strong>Модель:</strong> <?= htmlspecialchars($product['model']) ?></div>
                    <div><strong>Бренд:</strong> <?= htmlspecialchars($product['brand_name'] ?? 'Не указан') ?></div>
                    <div><strong>Тип:</strong> 
                        <?php 
                            $type_names = [
                                'processor' => 'Процессор',
                                'videocard' => 'Видеокарта', 
                                'motherboard' => 'Материнская плата',
                                'ram' => 'Оперативная память',
                                'ssd' => 'SSD',
                                'hdd' => 'HDD',
                                'powersupply' => 'Блок питания',
                                'cooling' => 'Охлаждение',
                                'case' => 'Корпус'
                            ];
                            echo $type_names[$product['product_type']] ?? $product['product_type'];
                        ?>
                    </div>
                    <div><strong>Наличие:</strong> 
                        <span style="color: <?= $product['stock'] > 0 ? '#28a745' : '#dc3545' ?>;">
                            <?= $product['stock'] > 0 ? "В наличии ({$product['stock']} шт.)" : 'Нет в наличии' ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($product['description'])): ?>
                    <div class="product-description">
                        <h3>Описание</h3>
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($product['stock'] > 0 && isLoggedIn()): ?>
                    <div style="margin-top: 30px;">
                        <form method="post" action="/cart.php" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" name="action" value="add" class="btn-primary">Добавить в корзину</button>
                        </form>
                    </div>
                <?php elseif (!isLoggedIn()): ?>
                    <div style="margin-top: 30px;">
                        <a href="/login.php" class="btn-primary">Войдите чтобы купить</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Секция отзывов -->
        <div class="reviews-section">
            <h2>Отзывы о товаре</h2>
            
            <!-- Форма добавления отзыва -->
            <?php if (isLoggedIn()): ?>
                <div class="review-form">
                    <h3>Оставить отзыв</h3>
                    
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
                    
                    <form method="post">
                        <div class="form-group">
                            <label>Оценка *</label>
                            <div class="rating-stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                    <label for="star<?= $i ?>">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Заголовок отзыва *</label>
                            <input type="text" name="title" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Комментарий</label>
                            <textarea name="comment" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                        </div>
                        
                        <button type="submit" name="add_review" class="btn-primary">Оставить отзыв</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert info">
                    <p>Для добавления отзыва <a href="/login.php">войдите в систему</a></p>
                </div>
            <?php endif; ?>
            
            <!-- Список отзывов -->
            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                    <div class="alert info">
                        <p>Пока нет отзывов о этом товаре. Будьте первым!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="review-author">
                                    <?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?>
                                </div>
                                <div class="review-rating">
                                    <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($review['title'])): ?>
                                <div class="review-title"><?= htmlspecialchars($review['title']) ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($review['comment'])): ?>
                                <div class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 10px; color: #666; font-size: 12px;">
                                <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/blocks/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';

// Получаем случайные товары
$stmt = $pdo->query("
    SELECT p.*, b.name as brand_name 
    FROM Products p 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE p.is_active = 1 
    ORDER BY RAND() 
    LIMIT 8
");
$random_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_info = isLoggedIn() ? getUserInfo() : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once 'blocks/header.php'; ?>
    
    <main style="padding: 40px 5%; min-height: 60vh;">
        <h1>Добро пожаловать в магазин компьютерных комплектующих</h1>
        <p>Лучшие компоненты для вашего ПК по доступным ценам</p>
        
        <?php if (!isLoggedIn()): ?>
            <div style="text-align: center; margin-top: 50px;">
                <h2>Начните покупки прямо сейчас!</h2>
                <div style="margin-top: 20px;">
                    <a href="/auth/register.php" class="btn-primary">Зарегистрироваться</a>
                    <a href="/auth/login.php" class="login-btn">Войти</a>
                </div>
            </div> 
        <?php else: ?>
            <div style="text-align: center; margin-top: 50px;">
                <h2>Добро пожаловать, <?= htmlspecialchars($user_info['first_name'] ?? 'Пользователь') ?>!</h2>
                <p>Ваш баланс: <?= number_format($user_info['balance'] ?? 0, 2) ?> руб.</p>
                <div style="margin-top: 20px;">
                    <a href="/account/" class="btn-primary">Перейти в личный кабинет</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Случайные товары -->
        <div style="margin: 50px 0;">
            <h2 style="text-align: center;">Рекомендуемые товары</h2>
            
            <?php if (!empty($random_products)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin: 30px 0;">
                    <?php foreach ($random_products as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-fixed">
                            <?php else: ?>
                                <div class="product-image-placeholder">Изображение отсутствует</div>
                            <?php endif; ?>
                            
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-brand"><?= htmlspecialchars($product['brand_name'] ?? 'Бренд не указан') ?></div>
                            <div class="product-price"><?= number_format($product['price'], 2) ?> руб.</div>
                            <div class="product-actions">
                                <a href="/product.php?id=<?= $product['product_id'] ?>" class="btn-primary">Подробнее</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center;">
                    <a href="/catalog.php" class="btn-primary">Весь каталог</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php require_once 'blocks/footer.php'; ?>
</body>
</html>
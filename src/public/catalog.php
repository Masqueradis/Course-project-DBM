<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// --- ЛОГИКА ФИЛЬТРАЦИИ ---
$product_type = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

$where_conditions = ["p.is_active = 1"];
$params = [];

if ($product_type !== 'all') {
    $where_conditions[] = "p.product_type = ?";
    $params[] = $product_type;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.model LIKE ? OR b.name LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

if (is_numeric($min_price)) {
    $where_conditions[] = "p.price >= ?";
    $params[] = $min_price;
}
if (is_numeric($max_price)) {
    $where_conditions[] = "p.price <= ?";
    $params[] = $max_price;
}

$order_by = "p.created_at DESC";
switch ($sort) {
    case 'price_asc': $order_by = "p.price ASC"; break;
    case 'price_desc': $order_by = "p.price DESC"; break;
    case 'name_asc': $order_by = "p.name ASC"; break;
    case 'rating': $order_by = "avg_rating DESC"; break;
}

// Запрос с подзапросами характеристик для ВСЕХ типов
$sql = "
    SELECT 
        p.*, 
        b.name as brand_name,
        COALESCE((SELECT AVG(rating) FROM Reviews r WHERE r.product_id = p.product_id AND r.is_approved = 1), 0) as avg_rating,
        (SELECT COUNT(*) FROM Reviews r WHERE r.product_id = p.product_id AND r.is_approved = 1) as review_count,
        CASE 
            WHEN p.product_type = 'processor' THEN (SELECT CONCAT(socket, ' | ', cores, ' ядер | ', base_frequency, ' ГГц') FROM Processors WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'videocard' THEN (SELECT CONCAT(memory_size, ' ГБ ', memory_type) FROM Videocards WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'motherboard' THEN (SELECT CONCAT(socket, ' | ', chipset, ' | ', form_factor) FROM Motherboards WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'ram' THEN (SELECT CONCAT(capacity, ' ГБ ', type, ' ', frequency, ' МГц') FROM RAM WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'ssd' THEN (SELECT CONCAT(capacity, ' ГБ | ', interface) FROM SSDs WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'hdd' THEN (SELECT CONCAT(capacity, ' ГБ | ', rpm, ' об/мин') FROM HDDs WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'powersupply' THEN (SELECT CONCAT(power, ' Вт | ', efficiency) FROM PowerSupplies WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'cooling' THEN (SELECT CONCAT(type, ' | Вент: ', fan_size, 'мм | TDP: ', max_tdp, 'Вт') FROM Cooling WHERE product_id = p.product_id LIMIT 1)
            WHEN p.product_type = 'case' THEN (SELECT CONCAT(form_factor, ' | Цвет: ', color) FROM ComputerCases WHERE product_id = p.product_id LIMIT 1)
            ELSE ''
        END as specs
    FROM Products p 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY $order_by
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Каталог товаров</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap" style="margin-top: 40px; margin-bottom: 40px;">
        
        <div class="catalog-header">
            <h1>Каталог комплектующих</h1>
            <form method="get" class="search-form">
                <input type="hidden" name="type" value="<?= htmlspecialchars($product_type) ?>">
                <input type="text" name="search" placeholder="Поиск товаров..." value="<?= htmlspecialchars($search) ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                <button type="submit" class="btn-primary">Найти</button>
            </form>
        </div>

        <div class="categories">
            <a href="?type=all" class="category-btn <?= $product_type === 'all' ? 'active' : '' ?>">Все товары</a>
            <a href="?type=processor" class="category-btn <?= $product_type === 'processor' ? 'active' : '' ?>">Процессоры</a>
            <a href="?type=videocard" class="category-btn <?= $product_type === 'videocard' ? 'active' : '' ?>">Видеокарты</a>
            <a href="?type=motherboard" class="category-btn <?= $product_type === 'motherboard' ? 'active' : '' ?>">Материнские платы</a>
            <a href="?type=ram" class="category-btn <?= $product_type === 'ram' ? 'active' : '' ?>">Оперативная память</a>
            <a href="?type=ssd" class="category-btn <?= $product_type === 'ssd' ? 'active' : '' ?>">SSD Накопители</a>
            <a href="?type=hdd" class="category-btn <?= $product_type === 'hdd' ? 'active' : '' ?>">Жесткие диски</a>
            <a href="?type=powersupply" class="category-btn <?= $product_type === 'powersupply' ? 'active' : '' ?>">Блоки питания</a>
            <a href="?type=cooling" class="category-btn <?= $product_type === 'cooling' ? 'active' : '' ?>">Системы охлаждения</a>
            <a href="?type=case" class="category-btn <?= $product_type === 'case' ? 'active' : '' ?>">Корпуса</a>
        </div>

        <div class="sorting-filters">
            <div style="display: flex; gap: 10px; align-items: center;">
                <label>Сортировка:</label>
                <select class="sort-select" onchange="updateParam('sort', this.value)" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Сначала новые</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Сначала дешевые</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Сначала дорогие</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>По рейтингу</option>
                </select>
            </div>
            
            <div class="price-range">
                <input type="number" id="min_price" placeholder="Цена от" value="<?= htmlspecialchars($min_price) ?>">
                <input type="number" id="max_price" placeholder="до" value="<?= htmlspecialchars($max_price) ?>">
                <button onclick="applyPrice()" class="btn-small">OK</button>
            </div>
            
            <?php if ($search || $min_price || $max_price || $product_type !== 'all'): ?>
                <a href="catalog.php" class="btn-small btn-danger" style="display: flex; align-items: center;">Сбросить фильтры</a>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="alert info" style="text-align: center; padding: 40px;">
                <h3>Товары не найдены</h3>
                <p>Попробуйте изменить категорию или параметры поиска.</p>
                <a href="catalog.php" class="btn-primary" style="margin-top: 15px;">Показать все товары</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <a href="product.php?id=<?= $product['product_id'] ?>" style="text-decoration: none; color: inherit;">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-fixed">
                            <?php else: ?>
                                <div class="product-image-placeholder">Нет фото</div>
                            <?php endif; ?>
                            
                            <div class="product-brand"><?= htmlspecialchars($product['brand_name'] ?? '') ?></div>
                            <div class="product-name" title="<?= htmlspecialchars($product['name']) ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </div>
                            
                            <div style="margin-bottom: 10px; font-size: 13px; color: #ffc107;">
                                <?= str_repeat('★', round($product['avg_rating'])) ?><?= str_repeat('☆', 5 - round($product['avg_rating'])) ?>
                                <span style="color: #ccc; font-size: 12px;">(<?= $product['review_count'] ?>)</span>
                            </div>

                            <div class="product-specs">
                                <?= htmlspecialchars($product['specs'] ?? '') ?>
                            </div>
                            
                            <div class="product-price"><?= formatPrice($product['price']) ?></div>
                        </a>
                        
                        <div class="product-actions" style="margin-top: auto;">
                            <?php if ($product['stock'] > 0): ?>
                                <form method="post" action="cart.php" style="width: 100%;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    
                                    <?php if (isLoggedIn()): ?>
                                        <button type="submit" class="btn-primary" style="width: 100%;">В корзину</button>
                                    <?php else: ?>
                                        <a href="login.php" class="btn-primary" style="display:block; text-align:center; background-color:#ccc; width: 100%;">Войти</a>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <button class="btn-primary" disabled style="width: 100%; background: #eee; color: #999; cursor: not-allowed;">Нет в наличии</button>
                            <?php endif; ?>
                            
                            <?php if (isAdmin()): ?>
                                <a href="editproduct.php?id=<?= $product['product_id'] ?>" class="btn-small" style="background: #ffc107; color: #000; width: 40px; text-align: center; display: flex; align-items: center; justify-content: center; margin-left: 5px;">✎</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateParam(key, value) {
            const url = new URL(window.location.href);
            if (value) url.searchParams.set(key, value);
            else url.searchParams.delete(key);
            window.location.href = url.toString();
        }
        
        function applyPrice() {
            const min = document.getElementById('min_price').value;
            const max = document.getElementById('max_price').value;
            const url = new URL(window.location.href);
            if (min) url.searchParams.set('min_price', min); else url.searchParams.delete('min_price');
            if (max) url.searchParams.set('max_price', max); else url.searchParams.delete('max_price');
            window.location.href = url.toString();
        }
    </script>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
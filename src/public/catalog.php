<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth/functions.php';

$product_type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Построение SQL запроса с фильтрами и сортировкой
$where_conditions = ["p.is_active = 1"];
$params = [];

if ($product_type !== 'all') {
    $where_conditions[] = "p.product_type = ?";
    $params[] = $product_type;
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.model LIKE ? OR p.description LIKE ? OR b.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($min_price) && is_numeric($min_price)) {
    $where_conditions[] = "p.price >= ?";
    $params[] = floatval($min_price);
}

if (!empty($max_price) && is_numeric($max_price)) {
    $where_conditions[] = "p.price <= ?";
    $params[] = floatval($max_price);
}

$where_sql = implode(" AND ", $where_conditions);

// Определение сортировки
$order_by = "p.created_at DESC";
switch ($sort) {
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
    case 'name_asc':
        $order_by = "p.name ASC";
        break;
    case 'name_desc':
        $order_by = "p.name DESC";
        break;
    case 'rating':
        $order_by = "avg_rating DESC";
        break;
    case 'newest':
    default:
        $order_by = "p.created_at DESC";
        break;
}

// Если сортировка по рейтингу, нужен JOIN с отзывами
if ($sort === 'rating') {
    $sql = "
        SELECT p.*, b.name as brand_name, 
               COALESCE(AVG(r.rating), 0) as avg_rating,
               COUNT(r.review_id) as review_count
        FROM Products p 
        LEFT JOIN Brands b ON p.brand_id = b.brand_id 
        LEFT JOIN Reviews r ON p.product_id = r.product_id AND r.is_approved = 1
        WHERE $where_sql
        GROUP BY p.product_id
        ORDER BY $order_by
    ";
} else {
    $sql = "
        SELECT p.*, b.name as brand_name 
        FROM Products p 
        LEFT JOIN Brands b ON p.brand_id = b.brand_id 
        WHERE $where_sql
        ORDER BY $order_by
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_info = isLoggedIn() ? getUserInfo() : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог товаров</title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        /* Стили из предыдущей версии остаются */
        .catalog-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .catalog-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
        .search-form { display: flex; gap: 10px; }
        .search-form input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-width: 300px; }
        .categories { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .category-btn { padding: 10px 20px; border: 1px solid #0156FF; border-radius: 4px; background: white; color: #0156FF; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .category-btn:hover, .category-btn.active { background: #0156FF; color: white; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin-bottom: 50px; }
        .product-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .product-card:hover { transform: translateY(-5px); }
        .product-image { width: 100%; height: 200px; object-fit: contain; margin-bottom: 15px; background: #f8f9fa; border-radius: 4px; }
        .product-name { font-weight: bold; margin-bottom: 10px; color: #333; }
        .product-brand { color: #666; margin-bottom: 10px; font-size: 14px; }
        .product-price { font-size: 1.5em; font-weight: bold; color: #0156FF; margin-bottom: 15px; }
        .product-stock { color: #28a745; margin-bottom: 15px; }
        .product-actions { display: flex; gap: 10px; }
        .no-products { text-align: center; padding: 40px; color: #666; }
        .product-rating { color: #ffc107; margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/blocks/header.php'; ?>

    <div class="catalog-container">
        <div class="catalog-header">
            <h1>Каталог товаров</h1>
            <form method="get" class="search-form">
                <input type="hidden" name="type" value="<?= htmlspecialchars($product_type) ?>">
                <input type="text" name="search" placeholder="Поиск товаров..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary">Найти</button>
            </form>
        </div>

        <div class="categories">
            <a href="?type=all&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'all' ? 'active' : '' ?>">Все товары</a>
            <a href="?type=processor&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'processor' ? 'active' : '' ?>">Процессоры</a>
            <a href="?type=videocard&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'videocard' ? 'active' : '' ?>">Видеокарты</a>
            <a href="?type=motherboard&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'motherboard' ? 'active' : '' ?>">Материнские платы</a>
            <a href="?type=ram&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'ram' ? 'active' : '' ?>">Оперативная память</a>
            <a href="?type=ssd&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'ssd' ? 'active' : '' ?>">SSD</a>
            <a href="?type=hdd&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'hdd' ? 'active' : '' ?>">HDD</a>
            <a href="?type=powersupply&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
               class="category-btn <?= $product_type === 'powersupply' ? 'active' : '' ?>">Блоки питания</a>
        </div>

        <!-- Фильтры и сортировка -->
        <div class="sorting-filters">
            <div>
                <label>Сортировка:</label>
                <select class="sort-select" onchange="updateSort(this.value)">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>По новизне</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Цена по возрастанию</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Цена по убыванию</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Название А-Я</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Название Я-А</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>По рейтингу</option>
                </select>
            </div>
            
            <div class="price-range">
                <label>Цена от:</label>
                <input type="number" name="min_price" placeholder="0" value="<?= htmlspecialchars($min_price) ?>" onchange="updatePriceFilter()">
                <label>до:</label>
                <input type="number" name="max_price" placeholder="999999" value="<?= htmlspecialchars($max_price) ?>" onchange="updatePriceFilter()">
            </div>
            
            <?php if (!empty($search) || !empty($min_price) || !empty($max_price) || $sort !== 'newest'): ?>
                <a href="?type=<?= $product_type ?>" class="btn-small btn-danger">Сбросить фильтры</a>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="no-products">
                <h3>Товары не найдены</h3>
                <p>Попробуйте изменить критерии поиска</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-fixed">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    Изображение отсутствует
                                </div>
                            <?php endif; ?>
                        
                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="product-brand"><?= htmlspecialchars($product['brand_name'] ?? 'Бренд не указан') ?></div>
                        
                        <?php if (isset($product['avg_rating']) && $product['avg_rating'] > 0): ?>
                            <div class="product-rating">
                                <?= str_repeat('★', round($product['avg_rating'])) ?><?= str_repeat('☆', 5 - round($product['avg_rating'])) ?>
                                (<?= $product['review_count'] ?? 0 ?>)
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-model" style="color: #666; margin-bottom: 10px; font-size: 14px;">
                            Модель: <?= htmlspecialchars($product['model']) ?>
                        </div>
                        <div class="product-price"><?= number_format($product['price'], 2) ?> руб.</div>
                        <div class="product-stock">
                            <?= $product['stock'] > 0 ? "В наличии: {$product['stock']} шт." : 'Нет в наличии' ?>
                        </div>
                        <div class="product-actions">
                            <a href="product.php?id=<?= $product['product_id'] ?>" class="btn-primary" style="flex: 1; text-align: center;">Подробнее</a>
                            <?php if ($product['stock'] > 0 && isLoggedIn()): ?>
                                <form method="post" action="cart.php" style="margin: 0;">
                                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" name="action" value="add" class="btn-small" style="background: #28a745; color: white;">В корзину</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateSort(sortValue) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            window.location.href = url.toString();
        }
        
        function updatePriceFilter() {
            const minPrice = document.querySelector('input[name="min_price"]').value;
            const maxPrice = document.querySelector('input[name="max_price"]').value;
            const url = new URL(window.location.href);
            
            if (minPrice) url.searchParams.set('min_price', minPrice);
            else url.searchParams.delete('min_price');
            
            if (maxPrice) url.searchParams.set('max_price', maxPrice);
            else url.searchParams.delete('max_price');
            
            window.location.href = url.toString();
        }
    </script>

    <?php require_once __DIR__ . '/blocks/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

// --- 1. ПАРАМЕТРЫ СОРТИРОВКИ ---
// Белый список разрешенных колонок (защита от SQL инъекций)
$allowed_sort = ['product_id', 'name', 'price', 'stock', 'is_active', 'product_type'];

// Получаем параметры из URL или ставим дефолтные
$sort = $_GET['sort'] ?? 'product_id';
$order = strtoupper($_GET['order'] ?? 'DESC');

// Валидация
if (!in_array($sort, $allowed_sort)) {
    $sort = 'product_id';
}
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// --- 2. ПОИСК ---
$search = trim($_GET['search'] ?? '');
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE ? OR sku LIKE ? OR model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Собираем SQL
$sql = "SELECT p.*, b.name as brand_name 
        FROM Products p 
        LEFT JOIN Brands b ON p.brand_id = b.brand_id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// Добавляем сортировку
$sql .= " ORDER BY $sort $order";

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. ФУНКЦИЯ ДЛЯ ГЕНЕРАЦИИ ССЫЛОК СОРТИРОВКИ ---
function sortLink($column, $label, $current_sort, $current_order, $search_term) {
    // Если мы уже сортируем по этой колонке, меняем направление
    $new_order = ($current_sort === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    
    // Иконка стрелочки
    $arrow = '';
    if ($current_sort === $column) {
        $arrow = ($current_order === 'ASC') ? ' ▲' : ' ▼';
    }
    
    // Сохраняем поиск в ссылке
    $search_param = $search_term ? "&search=" . urlencode($search_term) : "";
    
    // Генерируем ссылку
    $url = "?sort=$column&order=$new_order$search_param";
    
    // Возвращаем HTML
    // Класс sortable-header добавим в CSS
    return "<a href='$url' class='sortable-header" . ($current_sort === $column ? " active" : "") . "'>
                $label <span class='sort-arrow'>$arrow</span>
            </a>";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление товарами</title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        /* Локальные стили для стрелочек */
        .sortable-header {
            color: #444;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .sortable-header:hover {
            color: #0156FF;
        }
        .sortable-header.active {
            color: #0156FF;
            font-weight: bold;
        }
        .sort-arrow {
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'products'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1>Товары</h1>
                    <a href="addproduct.php" class="btn-primary">+ Добавить товар</a>
                </div>

                <div class="content-card" style="padding: 20px; margin-bottom: 20px;">
                    <form method="get" class="search-form" style="max-width: 100%;">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                        
                        <input type="text" name="search" placeholder="Поиск по названию, артикулу или модели..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
                        <button type="submit" class="btn-primary">Найти</button>
                        <?php if($search): ?>
                            <a href="admin_products.php" class="btn-small" style="background: #ccc;">Сброс</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="content-card">
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?= sortLink('product_id', 'ID', $sort, $order, $search) ?></th>
                                    <th style="width: 80px;">Фото</th>
                                    <th><?= sortLink('name', 'Название', $sort, $order, $search) ?></th>
                                    <th><?= sortLink('product_type', 'Категория', $sort, $order, $search) ?></th>
                                    <th><?= sortLink('price', 'Цена', $sort, $order, $search) ?></th>
                                    <th><?= sortLink('stock', 'Склад', $sort, $order, $search) ?></th>
                                    <th><?= sortLink('is_active', 'Статус', $sort, $order, $search) ?></th>
                                    <th style="text-align: right;">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">Товары не найдены</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): ?>
                                        <tr>
                                            <td>#<?= $p['product_id'] ?></td>
                                            <td>
                                                <?php if($p['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" class="img-thumbnail">
                                                <?php else: ?>
                                                    <div style="width:40px; height:40px; background:#eee; border-radius:4px;"></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                                <small style="color:#888;"><?= htmlspecialchars($p['sku']) ?></small>
                                            </td>
                                            <td><?= ucfirst($p['product_type']) ?></td>
                                            <td><?= formatPrice($p['price']) ?></td>
                                            <td>
                                                <?php if($p['stock'] <= 5): ?>
                                                    <span style="color: red; font-weight: bold;"><?= $p['stock'] ?></span>
                                                <?php else: ?>
                                                    <?= $p['stock'] ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($p['is_active']): ?>
                                                    <span style="color: green;">Активен</span>
                                                <?php else: ?>
                                                    <span style="color: red;">Скрыт</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="editproduct.php?id=<?= $p['product_id'] ?>" class="btn-small" style="background-color: #ffc107; color: #000; margin-bottom=5px;">✏️</a>
                                                <a href="delete_product.php?id=<?= $p['product_id'] ?>" class="btn-small btn-danger" onclick="return confirm('Удалить товар?')">🗑️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
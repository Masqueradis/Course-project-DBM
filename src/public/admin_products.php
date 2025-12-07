<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Базовый запрос
$sql = "SELECT p.*, b.name as brand_name 
        FROM Products p 
        LEFT JOIN Brands b ON p.brand_id = b.brand_id 
        WHERE 1=1";
$params = [];

// Фильтры
if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $sql .= " AND p.product_type = ?";
    $params[] = $type_filter;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Товары</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'products'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                    <h1>Товары</h1>
                    <a href="addproducts.php" class="btn-primary">+ Добавить</a>
                </div>

                <div class="content-card">
                    <form method="get" class="form-row">
                        <div class="form-group"><input type="text" name="search" placeholder="Поиск..." value="<?= htmlspecialchars($search) ?>"></div>
                        <div class="form-group"><button type="submit" class="btn-primary">Найти</button></div>
                    </form>

                    <div class="data-table">
                        <table>
                            <thead>
                                <tr><th>Фото</th><th>Название</th><th>Цена</th><th>Склад</th><th>Действия</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <?php if($p['image_url']): ?>
                                                <img src="<?= $p['image_url'] ?>" class="img-thumbnail">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= formatPrice($p['price']) ?></td>
                                        <td><?= $p['stock'] ?></td>
                                        <td><a href="editproduct.php?id=<?= $p['product_id'] ?>" class="btn-small">Ред.</a></td>
                                    </tr>
                                <?php endforeach; ?>
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
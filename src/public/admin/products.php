<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';

// Обработка загрузки изображения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        $errors[] = "Неверный ID товара";
    } elseif ($_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Разрешены только файлы JPG, PNG, GIF и WebP";
        } elseif ($file['size'] > 5 * 1024 * 1024) { 
            $errors[] = "Файл слишком большой. Максимальный размер 5MB";
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . $product_id . '_' . time() . '.' . $extension;
            $upload_dir = '/var/www/html/public/uploads/products/';
            $upload_path = $upload_dir . $filename;
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $image_url = '/uploads/products/' . $filename;
                $stmt = $pdo->prepare("UPDATE Products SET image_url = ? WHERE product_id = ?");
                if ($stmt->execute([$image_url, $product_id])) {
                    $success = "Изображение товара успешно обновлено!";
                } else {
                    $errors[] = "Ошибка при сохранении в базу данных";
                }
            } else {
                $errors[] = "Ошибка при загрузке файла";
            }
        }
    } elseif ($_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Ошибка загрузки файла";
    }
}

// Обработка других действий
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    
    switch ($_POST['action']) {
        case 'toggle_active':
            $stmt = $pdo->prepare("UPDATE Products SET is_active = NOT is_active WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $success = "Статус товара изменен";
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("UPDATE Products SET is_active = 0 WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $success = "Товар деактивирован";
            break;
            
        case 'add_product':
            $product_type = trim($_POST['product_type']);
            $name = trim($_POST['name']);
            $model = trim($_POST['model']);
            $sku = trim($_POST['sku']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $description = trim($_POST['description']);
            $brand_id = intval($_POST['brand_id'] ?? 0);
            
            if (empty($name) || empty($model) || empty($sku) || $price <= 0) {
                $errors[] = "Заполните все обязательные поля";
            } else {
                // Проверка уникальности SKU
                $stmt = $pdo->prepare("SELECT product_id FROM Products WHERE sku = ?");
                $stmt->execute([$sku]);
                if ($stmt->fetch()) {
                    $errors[] = "Товар с таким SKU уже существует";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO Products (product_type, name, model, sku, price, stock, description, brand_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$product_type, $name, $model, $sku, $price, $stock, $description, $brand_id])) {
                        $success = "Товар успешно добавлен";
                    } else {
                        $errors[] = "Ошибка при добавлении товара";
                    }
                }
            }
            break;
    }
}

// Получение списка товаров
$stmt = $pdo->query("
    SELECT p.*, b.name as brand_name 
    FROM Products p 
    LEFT JOIN Brands b ON p.brand_id = b.brand_id 
    ORDER BY p.created_at DESC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение брендов
$brands_stmt = $pdo->query("SELECT brand_id, name FROM Brands ORDER BY name");
$brands = $brands_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/../blocks/header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Админ панель</h3>
            <ul>
                <li><a href="index.php">Дашборд</a></li>
                <li><a href="users.php">Пользователи</a></li>
                <li><a href="products.php" class="active">Товары</a></li>
                <li><a href="orders.php">Заказы</a></li>
                <li><a href="reviews.php">Отзывы</a></li>
                <li><a href="/account/index.php">Личный кабинет</a></li>
            </ul>
        </div>

        <div class="admin-main">
            <h1>Управление товарами</h1>

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

            <!-- Форма добавления товара -->
            <div class="add-product-form" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h3>Добавить новый товар</h3>
                <form method="post">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Тип товара *</label>
                            <select name="product_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="processor">Процессор</option>
                                <option value="videocard">Видеокарта</option>
                                <option value="motherboard">Материнская плата</option>
                                <option value="ram">Оперативная память</option>
                                <option value="ssd">SSD</option>
                                <option value="hdd">HDD</option>
                                <option value="powersupply">Блок питания</option>
                                <option value="cooling">Охлаждение</option>
                                <option value="case">Корпус</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Бренд</label>
                            <select name="brand_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">-- Выберите бренд --</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['brand_id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Название *</label>
                            <input type="text" name="name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Модель *</label>
                            <input type="text" name="model" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label>SKU *</label>
                            <input type="text" name="sku" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Цена *</label>
                            <input type="number" name="price" step="0.01" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        
                        <div class="form-group">
                            <label>Количество на складе</label>
                            <input type="number" name="stock" min="0" value="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Описание</label>
                        <textarea name="description" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>
                    
                    <button type="submit" name="action" value="add_product" class="btn-primary">Добавить товар</button>
                </form>
            </div>

            <!-- Таблица товаров -->
            <div class="products-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Изображение</th>
                            <th>Тип</th>
                            <th>Название</th>
                            <th>Модель</th>
                            <th>Бренд</th>
                            <th>Цена</th>
                            <th>Склад</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['product_id'] ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image-fixed">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            Нет изображения
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $type_names = [
                                            'processor' => 'Процессор',
                                            'videocard' => 'Видеокарта', 
                                            'motherboard' => 'Материнская плата',
                                            'ram' => 'Память',
                                            'ssd' => 'SSD',
                                            'hdd' => 'HDD',
                                            'powersupply' => 'БП',
                                            'cooling' => 'Охлаждение',
                                            'case' => 'Корпус'
                                        ];
                                        echo $type_names[$product['product_type']] ?? $product['product_type'];
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['model']) ?></td>
                                <td><?= htmlspecialchars($product['brand_name'] ?? 'Не указан') ?></td>
                                <td><?= number_format($product['price'], 2) ?> руб.</td>
                                <td><?= $product['stock'] ?></td>
                                <td>
                                    <span class="<?= $product['is_active'] ? 'success' : 'error' ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?= $product['is_active'] ? 'Активен' : 'Неактивен' ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-direction: column;">
                                        <!-- Форма загрузки изображения -->
                                        <form method="post" enctype="multipart/form-data" class="image-upload-form">
                                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                            <div class="form-group">
                                                <label>Загрузить изображение:</label>
                                                <input type="file" name="product_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                                <small>JPG, PNG, GIF, WebP до 5MB</small>
                                            </div>
                                            <button type="submit" class="btn-small">Загрузить</button>
                                        </form>
                                        
                                        <div style="display: flex; gap: 5px;">
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                                <button type="submit" name="action" value="toggle_active" class="btn-small <?= $product['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                                    <?= $product['is_active'] ? 'Деактивировать' : 'Активировать' ?>
                                                </button>
                                            </form>
                                            <a href="product_edit.php?id=<?= $product['product_id'] ?>" class="btn-small" style="background: #ffc107; color: black;">Редактировать</a>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                                <button type="submit" name="action" value="delete" class="btn-small btn-danger" onclick="return confirm('Вы уверены?')">Удалить</button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../blocks/footer.php'; ?>
</body>
</html>
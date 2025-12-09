<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

redirectIfNotAdmin();

if (!isset($_GET['id'])) {
    header("Location: admin_products.php");
    exit;
}

$product_id = intval($_GET['id']);
$errors = [];
$success = '';

$specs_config = [
    'processor' => ['table' => 'Processors', 'title' => 'Процессор', 'fields' => ['socket' => ['label' => 'Сокет', 'type' => 'text', 'required' => true], 'cores' => ['label' => 'Ядра', 'type' => 'number', 'required' => true], 'threads' => ['label' => 'Потоки', 'type' => 'number', 'required' => true], 'base_frequency' => ['label' => 'Частота', 'type' => 'number', 'step' => 0.01, 'required' => true], 'max_frequency' => ['label' => 'Макс. частота', 'type' => 'number', 'step' => 0.01, 'required' => false], 'cache' => ['label' => 'Кэш', 'type' => 'number', 'required' => true], 'tdp' => ['label' => 'TDP', 'type' => 'number', 'required' => true], 'includes_cooler' => ['label' => 'Кулер', 'type' => 'checkbox', 'required' => false]]],
    'videocard' => ['table' => 'Videocards', 'title' => 'Видеокарта', 'fields' => ['memory_size' => ['label' => 'Память', 'type' => 'number', 'required' => true], 'memory_type' => ['label' => 'Тип', 'type' => 'text', 'required' => true], 'gpu_frequency' => ['label' => 'Частота GPU', 'type' => 'number', 'step' => 0.01, 'required' => true], 'tdp' => ['label' => 'TDP', 'type' => 'number', 'required' => true]]],
    'motherboard' => ['table' => 'Motherboards', 'title' => 'Мат. плата', 'fields' => ['socket' => ['label' => 'Сокет', 'type' => 'text', 'required' => true], 'chipset' => ['label' => 'Чипсет', 'type' => 'text', 'required' => true], 'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true]]],
    'ram' => ['table' => 'RAM', 'title' => 'ОЗУ', 'fields' => ['capacity' => ['label' => 'Объем', 'type' => 'number', 'required' => true], 'type' => ['label' => 'Тип', 'type' => 'text', 'required' => true], 'frequency' => ['label' => 'Частота', 'type' => 'number', 'step' => 0.01, 'required' => true]]],
    'ssd' => ['table' => 'SSDs', 'title' => 'SSD', 'fields' => ['capacity' => ['label' => 'Объем', 'type' => 'number', 'required' => true], 'interface' => ['label' => 'Интерфейс', 'type' => 'text', 'required' => true], 'read_speed' => ['label' => 'Чтение', 'type' => 'number', 'required' => true], 'write_speed' => ['label' => 'Запись', 'type' => 'number', 'required' => true]]],
    'hdd' => ['table' => 'HDDs', 'title' => 'HDD', 'fields' => ['capacity' => ['label' => 'Объем', 'type' => 'number', 'required' => true], 'rpm' => ['label' => 'Скорость', 'type' => 'number', 'required' => true]]],
    'powersupply' => ['table' => 'PowerSupplies', 'title' => 'БП', 'fields' => ['power' => ['label' => 'Мощность', 'type' => 'number', 'required' => true], 'efficiency' => ['label' => 'Сертификат', 'type' => 'text', 'required' => true]]],
    'cooling' => ['table' => 'Cooling', 'title' => 'Охлаждение', 'fields' => ['type' => ['label' => 'Тип', 'type' => 'text', 'required' => true], 'max_tdp' => ['label' => 'TDP', 'type' => 'number', 'required' => false]]],
    'case' => ['table' => 'ComputerCases', 'title' => 'Корпус', 'fields' => ['form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true], 'color' => ['label' => 'Цвет', 'type' => 'text', 'required' => false]]]
];

$stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) die("Товар не найден");

$current_type = $product['product_type'];
$specs_data = [];

if (isset($specs_config[$current_type])) {
    $table = $specs_config[$current_type]['table'];
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $specs_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$brands = $pdo->query("SELECT * FROM Brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $brand_id = !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE Products SET name=?, model=?, sku=?, price=?, stock=?, description=?, brand_id=?, is_active=?, updated_at=NOW() WHERE product_id=?");
        $stmt->execute([$name, $model, $sku, $price, $stock, $description, $brand_id, $is_active, $product_id]);

        if (isset($specs_config[$current_type])) {
            $conf = $specs_config[$current_type];
            $updates = []; $values = [];
            $posted = $_POST['specs'][$current_type] ?? [];
            foreach ($conf['fields'] as $fname => $fconf) {
                $updates[] = "$fname = ?";
                $val = $posted[$fname] ?? null;
                if ($fconf['type'] === 'checkbox') $val = isset($posted[$fname]) ? 1 : 0;
                $values[] = $val;
            }
            $values[] = $product_id;
            $sql = "UPDATE {$conf['table']} SET " . implode(', ', $updates) . " WHERE product_id = ?";
            $pdo->prepare($sql)->execute($values);
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . $product_id . '_' . time() . '.' . $ext;
            $path = __DIR__ . '/uploads/products/' . $filename;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
                $pdo->prepare("UPDATE Products SET image_url = ? WHERE product_id = ?")->execute(['/uploads/products/'.$filename, $product_id]);
                $product['image_url'] = '/uploads/products/'.$filename;
            }
        }

        $pdo->commit();
        $success = "Товар успешно обновлен!";
        $stmt = $pdo->prepare("SELECT * FROM Products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование товара</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'products'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="content-area">
                <div style="margin-bottom: 20px;">
                    <a href="admin_products.php" class="btn-small" style="background:#6c757d; text-decoration: none;">&larr; Назад к списку</a>
                </div>

                <h1>Редактирование: <?= htmlspecialchars($product['name']) ?></h1>

                <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
                <?php if ($errors): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>

                <div class="content-card">
                    <form method="post" enctype="multipart/form-data">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Тип товара</label>
                                <input type="text" value="<?= ucfirst($product['product_type']) ?>" disabled style="background: #f0f0f0;">
                            </div>
                            <div class="form-group">
                                <label>Статус</label>
                                <div style="margin-top: 10px;">
                                    <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?> style="width: auto;">
                                    Активен
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label>Название</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Модель</label>
                                <input type="text" name="model" value="<?= htmlspecialchars($product['model'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Артикул (SKU)</label>
                                <input type="text" name="sku" value="<?= htmlspecialchars($product['sku']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Цена</label>
                                <input type="number" name="price" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Склад</label>
                                <input type="number" name="stock" value="<?= htmlspecialchars($product['stock']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Бренд</label>
                            <select name="brand_id">
                                <option value="">-- Не выбран --</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['brand_id'] ?>" <?= $product['brand_id'] == $brand['brand_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Фотография</label>
                            
                            <div style="margin-bottom: 15px; text-align: center; background: #f9f9f9; padding: 10px; border-radius: 8px; border: 1px dashed #ddd;">
                                <img id="preview-img" 
                                     src="<?= $product['image_url'] ? $product['image_url'] : '' ?>" 
                                     alt="Предпросмотр" 
                                     style="max-width: 100%; max-height: 250px; border-radius: 4px; display: <?= $product['image_url'] ? 'inline-block' : 'none' ?>;">
                                     
                                <div id="no-image-text" style="color: #999; font-size: 14px; padding: 20px; display: <?= $product['image_url'] ? 'none' : 'block' ?>;">
                                    Нет изображения
                                </div>
                            </div>

                            <div class="file-upload-wrapper">
                                <label for="img-edit" class="btn-file-custom" id="file-label">
                                    <?= $product['image_url'] ? 'Заменить фото' : 'Загрузить фото' ?>
                                </label>
                                <input type="file" name="image" id="img-edit" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Описание</label>
                            <textarea name="description" rows="4"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>

                        <?php if (isset($specs_config[$current_type])): ?>
                            <div class="specs-section active" style="display: block;">
                                <h4>Характеристики</h4>
                                <div class="form-row" style="flex-wrap: wrap;">
                                    <?php foreach ($specs_config[$current_type]['fields'] as $fname => $fconf): ?>
                                        <div class="form-group" style="min-width: 200px;">
                                            <label><?= $fconf['label'] ?></label>
                                            <?php 
                                                $val = $specs_data[$fname] ?? '';
                                                $name = "specs[$current_type][$fname]";
                                            ?>
                                            <?php if ($fconf['type'] === 'checkbox'): ?>
                                                <div style="margin-top: 10px;">
                                                    <input type="checkbox" name="<?= $name ?>" value="1" <?= $val ? 'checked' : '' ?> style="width: auto;">
                                                </div>
                                            <?php else: ?>
                                                <input type="<?= $fconf['type'] ?>" name="<?= $name ?>" value="<?= htmlspecialchars($val) ?>" <?php if(isset($fconf['step'])) echo "step='{$fconf['step']}'"; ?>>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn-primary">Сохранить изменения</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            var preview = document.getElementById('preview-img');
            var noImageText = document.getElementById('no-image-text');
            var label = document.getElementById('file-label');

            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    // Устанавливаем ссылку на загруженную картинку (base64)
                    preview.src = e.target.result;
                    preview.style.display = 'inline-block';
                    
                    // Скрываем текст "Нет изображения"
                    noImageText.style.display = 'none';
                    
                    // Меняем текст кнопки
                    label.textContent = 'Выбран файл: ' + input.files[0].name;
                    label.style.backgroundColor = '#e3efff'; // Легкая подсветка
                    label.style.borderColor = '#0156FF';
                }

                // Читаем файл как URL данных
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
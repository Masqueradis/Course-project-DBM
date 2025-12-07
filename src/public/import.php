<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';
$import_types = [
    'products' => 'Товары (Products)',
    'users' => 'Пользователи (Users)',
    'orders' => 'Заказы (Orders)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $import_type = $_POST['import_type'];
    $file = $_FILES['import_file'];
    
    // Валидация файла
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error";
    } elseif (!in_array($file['type'], ['text/csv', 'application/vnd.ms-excel', 'text/plain'])) {
        $errors[] = "Only CSV files are allowed";
    } else {
        $temp_file = $file['tmp_name'];
        
        if (($handle = fopen($temp_file, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            $row_count = 0;
            $imported = 0;
            $failed = 0;
            
            try {
                $pdo->beginTransaction();
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_count++;
                    $row_data = array_combine($headers, $data);
                    
                    switch ($import_type) {
                        case 'products':
                            $result = importProduct($row_data);
                            break;
                        case 'users':
                            $result = importUser($row_data);
                            break;
                        case 'orders':
                            $result = importOrder($row_data);
                            break;
                        default:
                            $result = false;
                    }
                    
                    if ($result) {
                        $imported++;
                    } else {
                        $failed++;
                    }
                }
                
                $pdo->commit();
                $success = "Imported $imported records, failed: $failed";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Import failed: " . $e->getMessage();
            }
            
            fclose($handle);
        }
    }
}

function importProduct($data) {
    global $pdo;
    
    try {
        // Проверяем, существует ли продукт
        $stmt = $pdo->prepare("SELECT product_id FROM Products WHERE sku = ?");
        $stmt->execute([$data['sku'] ?? '']);
        
        if ($stmt->fetch()) {
            // Обновляем существующий
            $sql = "UPDATE Products SET 
                    name = ?, model = ?, price = ?, stock = ?, 
                    description = ?, brand_id = ?, updated_at = NOW()
                    WHERE sku = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['model'],
                $data['price'],
                $data['stock'] ?? 0,
                $data['description'] ?? '',
                $data['brand_id'] ?? null,
                $data['sku']
            ]);
        } else {
            // Добавляем новый
            $sql = "INSERT INTO Products (product_type, name, model, sku, price, stock, description, brand_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['product_type'] ?? 'other',
                $data['name'],
                $data['model'],
                $data['sku'],
                $data['price'],
                $data['stock'] ?? 0,
                $data['description'] ?? '',
                $data['brand_id'] ?? null
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Product import error: " . $e->getMessage());
        return false;
    }
}

// Аналогичные функции для users и orders
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Импорт данных</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container-wrap layout-container">
        <?php $current_page = 'import'; require_once __DIR__ . '/sidebar_admin.php'; ?>
        
        <div class="content-area">
            <h1>Импорт данных</h1>
            
            <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
            <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>
            
            <div class="import-form">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Тип данных</label>
                        <select name="import_type" required>
                            <option value="">-- Выберите тип --</option>
                            <?php foreach ($import_types as $key => $name): ?>
                                <option value="<?= $key ?>"><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>CSV Файл *</label>
                        <input type="file" name="import_file" accept=".csv" required>
                    </div>
                    
                    <div class="alert info">
                        <p><strong>Важно:</strong> CSV файл должен содержать заголовки в первой строке.</p>
                        <p>Поля для товаров: name, model, sku, price, stock, description, product_type, brand_id</p>
                    </div>
                    
                    <button type="submit" class="btn-primary">Загрузить данные</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
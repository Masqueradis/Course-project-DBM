<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$message = '';
$errors = [];

// Маппинг: Тип товара из JSON -> Имя таблицы в БД
// Убедитесь, что ключи совпадают с product_type, а значения - с именами таблиц
$table_map = [
    'processor'    => 'Processors',
    'videocard'    => 'Videocards',
    'motherboard'  => 'Motherboards',
    'ram'          => 'RAM',
    'ssd'          => 'SSDs', // Или SSD, как у вас названа таблица
    'hdd'          => 'HDDs', // Или HDD
    'powersupply'  => 'PowerSupplies',
    'cooling'      => 'Cooling', // Обычно без s
    'computercase' => 'ComputerCases'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
    $file = $_FILES['json_file'];

    // 1. Проверка файла
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Ошибка при загрузке файла.";
    } elseif ($file['type'] !== 'application/json' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'json') {
        $errors[] = "Пожалуйста, загрузите валидный JSON файл.";
    } else {
        // 2. Чтение и парсинг
        $json_content = file_get_contents($file['tmp_name']);
        $products_data = json_decode($json_content, true);

        if ($products_data === null) {
            $errors[] = "Ошибка парсинга JSON. Проверьте синтаксис.";
        } else {
            // 3. Импорт
            $imported_count = 0;
            
            foreach ($products_data as $index => $item) {
                $line_num = $index + 1;
                
                // Валидация обязательных полей
                if (empty($item['name']) || empty($item['type']) || empty($item['sku']) || empty($item['price'])) {
                    $errors[] = "Товар #$line_num пропущен: отсутствуют обязательные поля (name, type, sku, price).";
                    continue;
                }

                $type = strtolower($item['type']);
                if (!array_key_exists($type, $table_map)) {
                    $errors[] = "Товар #$line_num пропущен: неизвестный тип товара '$type'.";
                    continue;
                }

                try {
                    $pdo->beginTransaction();

                    // А) РАБОТА С БРЕНДОМ
                    $brand_name = trim($item['brand'] ?? 'Unknown');
                    $stmt = $pdo->prepare("SELECT brand_id FROM Brands WHERE name = ?");
                    $stmt->execute([$brand_name]);
                    $brand_id = $stmt->fetchColumn();

                    if (!$brand_id) {
                        $stmt = $pdo->prepare("INSERT INTO Brands (name) VALUES (?)");
                        $stmt->execute([$brand_name]);
                        $brand_id = $pdo->lastInsertId();
                    }

                    // Б) ВСТАВКА В PRODUCTS (Базовая таблица)
                    // Используем INSERT IGNORE или проверку на SKU, чтобы не дублировать
                    $stmt = $pdo->prepare("SELECT product_id FROM Products WHERE sku = ?");
                    $stmt->execute([$item['sku']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Товар с артикулом {$item['sku']} уже существует.");
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO Products (name, sku, price, stock, product_type, brand_id, description, image_url, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $stmt->execute([
                        $item['name'],
                        $item['sku'],
                        $item['price'],
                        $item['stock'] ?? 0,
                        $type,
                        $brand_id,
                        $item['description'] ?? '',
                        $item['image_url'] ?? ''
                    ]);
                    
                    $product_id = $pdo->lastInsertId();

                    // В) ВСТАВКА В СПЕЦИФИКАЦИИ (Дочерняя таблица)
                    $spec_table = $table_map[$type];
                    $specs = $item['specs'] ?? [];
                    
                    // Добавляем обязательные внешние ключи
                    $specs['product_id'] = $product_id;
                    $specs['brand_id'] = $brand_id; // В ваших дочерних таблицах есть brand_id

                    // Динамическое построение запроса
                    if (!empty($specs)) {
                        $columns = array_keys($specs);
                        $placeholders = array_fill(0, count($columns), '?');
                        $values = array_values($specs);
                        
                        $sql_specs = "INSERT INTO $spec_table (" . implode(', ', $columns) . ") 
                                      VALUES (" . implode(', ', $placeholders) . ")";
                        
                        $stmt_specs = $pdo->prepare($sql_specs);
                        $stmt_specs->execute($values);
                    }

                    $pdo->commit();
                    $imported_count++;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Ошибка на товаре #$line_num ({$item['name']}): " . $e->getMessage();
                }
            }
            
            if ($imported_count > 0) {
                $message = "Успешно импортировано товаров: $imported_count";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Импорт товаров (JSON)</title>
    <link rel="stylesheet" href="/css/css.css">
    <style>
        .code-block {
            background: #2d2d2d;
            color: #ccc;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        .key { color: #d7ba7d; }
        .string { color: #ce9178; }
        .number { color: #b5cea8; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'import'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Массовый импорт товаров</h1>
                <p>Загрузите JSON файл со списком товаров. Система автоматически создаст бренды и заполнит характеристики.</p>

                <?php if ($message): ?>
                    <div class="alert success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <strong>Возникли ошибки:</strong><br>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="content-card">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Выберите JSON файл</label>
                            <div class="file-upload-wrapper">
                                <label for="json-file" class="btn-file-custom">Выбрать файл...</label>
                                <input type="file" name="json_file" id="json-file" accept=".json" required onchange="document.querySelector('.btn-file-custom').textContent = this.files[0].name">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Загрузить и импортировать</button>
                    </form>
                </div>

                <h3>Пример структуры JSON</h3>
                <div class="code-block">
<pre>
[
  {
    <span class="key">"name"</span>: <span class="string">"Intel Core i5-12400F"</span>,
    <span class="key">"sku"</span>: <span class="string">"INT-12400F"</span>,
    <span class="key">"brand"</span>: <span class="string">"Intel"</span>,
    <span class="key">"type"</span>: <span class="string">"processor"</span>,
    <span class="key">"price"</span>: <span class="number">150.00</span>,
    <span class="key">"stock"</span>: <span class="number">25</span>,
    <span class="key">"image_url"</span>: <span class="string">"/uploads/cpu_1.jpg"</span>,
    <span class="key">"description"</span>: <span class="string">"Мощный процессор для игр"</span>,
    <span class="key">"specs"</span>: {
      <span class="key">"socket"</span>: <span class="string">"LGA1700"</span>,
      <span class="key">"cores"</span>: <span class="number">6</span>,
      <span class="key">"threads"</span>: <span class="number">12</span>,
      <span class="key">"frequency"</span>: <span class="number">2.5</span>,
      <span class="key">"tdp"</span>: <span class="number">65</span>
    }
  },
  {
    <span class="key">"name"</span>: <span class="string">"NVIDIA RTX 4060"</span>,
    <span class="key">"sku"</span>: <span class="string">"NV-4060-8G"</span>,
    <span class="key">"brand"</span>: <span class="string">"ASUS"</span>,
    <span class="key">"type"</span>: <span class="string">"videocard"</span>,
    <span class="key">"price"</span>: <span class="number">320.00</span>,
    <span class="key">"stock"</span>: <span class="number">10</span>,
    <span class="key">"specs"</span>: {
      <span class="key">"memory_size"</span>: <span class="number">8</span>,
      <span class="key">"memory_type"</span>: <span class="string">"GDDR6"</span>,
      <span class="key">"gpu_freq"</span>: <span class="number">2460</span>
    }
  }
]
</pre>
                </div>

            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
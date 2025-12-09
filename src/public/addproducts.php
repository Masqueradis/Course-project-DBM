<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';

$specs_config = [
    'processor' => [
        'table' => 'Processors', 'title' => 'Процессор',
        'fields' => [
            'socket' => ['label' => 'Сокет', 'type' => 'text', 'required' => true],
            'cores' => ['label' => 'Количество ядер', 'type' => 'number', 'required' => true],
            'threads' => ['label' => 'Количество потоков', 'type' => 'number', 'required' => true],
            'base_frequency' => ['label' => 'Базовая частота (ГГц)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'max_frequency' => ['label' => 'Макс. частота (ГГц)', 'type' => 'number', 'step' => '0.01', 'required' => false],
            'cache' => ['label' => 'Кэш (МБ)', 'type' => 'number', 'required' => true],
            'tdp' => ['label' => 'TDP (Вт)', 'type' => 'number', 'required' => true],
            'includes_cooler' => ['label' => 'Кулер в комплекте', 'type' => 'checkbox', 'required' => false]
        ]
    ],
    'videocard' => [
        'table' => 'Videocards', 'title' => 'Видеокарта',
        'fields' => [
            'memory_size' => ['label' => 'Объем памяти (ГБ)', 'type' => 'number', 'required' => true],
            'memory_type' => ['label' => 'Тип памяти', 'type' => 'text', 'required' => true],
            'gpu_frequency' => ['label' => 'Частота GPU (ГГц)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'memory_frequency' => ['label' => 'Частота памяти (ГГц)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'tdp' => ['label' => 'TDP (Вт)', 'type' => 'number', 'required' => true],
            'display_ports' => ['label' => 'Кол-во DisplayPort', 'type' => 'number', 'required' => false],
            'hdmi_ports' => ['label' => 'Кол-во HDMI', 'type' => 'number', 'required' => false]
        ]
    ],
    'motherboard' => [
        'table' => 'Motherboards', 'title' => 'Материнская плата',
        'fields' => [
            'socket' => ['label' => 'Сокет', 'type' => 'text', 'required' => true],
            'chipset' => ['label' => 'Чипсет', 'type' => 'text', 'required' => true],
            'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true],
            'memory_slots' => ['label' => 'Слоты памяти', 'type' => 'number', 'required' => true],
            'memory_type' => ['label' => 'Тип памяти', 'type' => 'text', 'required' => true],
            'max_memory' => ['label' => 'Макс. память (ГБ)', 'type' => 'number', 'required' => true],
            'wifi_included' => ['label' => 'Встроенный Wi-Fi', 'type' => 'checkbox', 'required' => false]
        ]
    ],
    'ram' => [
        'table' => 'RAM', 'title' => 'Оперативная память',
        'fields' => [
            'capacity' => ['label' => 'Объем (ГБ)', 'type' => 'number', 'required' => true],
            'type' => ['label' => 'Тип (DDR4/5)', 'type' => 'text', 'required' => true],
            'frequency' => ['label' => 'Частота (МГц)', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'latency' => ['label' => 'Тайминги (CL)', 'type' => 'number', 'required' => true],
            'voltage' => ['label' => 'Напряжение (В)', 'type' => 'number', 'step' => '0.01', 'required' => false],
            'modules_count' => ['label' => 'Кол-во модулей', 'type' => 'number', 'required' => false]
        ]
    ],
    'ssd' => [
        'table' => 'SSDs', 'title' => 'SSD Накопитель',
        'fields' => [
            'capacity' => ['label' => 'Объем (ГБ)', 'type' => 'number', 'required' => true],
            'interface' => ['label' => 'Интерфейс', 'type' => 'text', 'required' => true],
            'read_speed' => ['label' => 'Скорость чтения (МБ/с)', 'type' => 'number', 'required' => true],
            'write_speed' => ['label' => 'Скорость записи (МБ/с)', 'type' => 'number', 'required' => true],
            'tbw' => ['label' => 'Ресурс (TBW)', 'type' => 'number', 'required' => false],
            'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true]
        ]
    ],
    'hdd' => [
        'table' => 'HDDs', 'title' => 'HDD Диск',
        'fields' => [
            'capacity' => ['label' => 'Объем (ГБ)', 'type' => 'number', 'required' => true],
            'rpm' => ['label' => 'Скорость (об/мин)', 'type' => 'number', 'required' => true],
            'interface' => ['label' => 'Интерфейс', 'type' => 'text', 'required' => true],
            'cache' => ['label' => 'Кэш (МБ)', 'type' => 'number', 'required' => false],
            'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true]
        ]
    ],
    'powersupply' => [
        'table' => 'PowerSupplies', 'title' => 'Блок питания',
        'fields' => [
            'power' => ['label' => 'Мощность (Вт)', 'type' => 'number', 'required' => true],
            'efficiency' => ['label' => 'Сертификат (80+)', 'type' => 'text', 'required' => true],
            'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true],
            'modular' => ['label' => 'Модульный', 'type' => 'checkbox', 'required' => false],
            'sata_connectors' => ['label' => 'Разъемы SATA', 'type' => 'number', 'required' => false],
            'pcie_connectors' => ['label' => 'Разъемы PCIe', 'type' => 'number', 'required' => false]
        ]
    ],
    'cooling' => [
        'table' => 'Cooling', 'title' => 'Охлаждение',
        'fields' => [
            'type' => ['label' => 'Тип', 'type' => 'text', 'required' => true],
            'noise_level' => ['label' => 'Шум (дБ)', 'type' => 'number', 'required' => false],
            'fan_size' => ['label' => 'Размер вентилятора (мм)', 'type' => 'number', 'required' => false],
            'max_tdp' => ['label' => 'Макс. TDP (Вт)', 'type' => 'number', 'required' => false],
            'compatibility' => ['label' => 'Совместимость', 'type' => 'text', 'required' => false]
        ]
    ],
    'case' => [
        'table' => 'ComputerCases', 'title' => 'Корпус',
        'fields' => [
            'form_factor' => ['label' => 'Форм-фактор', 'type' => 'text', 'required' => true],
            'color' => ['label' => 'Цвет', 'type' => 'text', 'required' => false],
            'tempered_glass' => ['label' => 'Закаленное стекло', 'type' => 'checkbox', 'required' => false],
            'included_fans' => ['label' => 'Вентиляторы в комплекте', 'type' => 'number', 'required' => false],
            'max_gpu_length' => ['label' => 'Макс. длина GPU (мм)', 'type' => 'number', 'required' => false],
            'max_cooler_height' => ['label' => 'Макс. высота кулера (мм)', 'type' => 'number', 'required' => false]
        ]
    ]
];

$brands = $pdo->query("SELECT brand_id, name FROM Brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

function handleImageUpload($product_id) {
    global $pdo;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array(mime_content_type($file['tmp_name']), $allowed)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'prod_' . $product_id . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/products/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $db_path = '/uploads/products/' . $filename;
                $pdo->prepare("UPDATE Products SET image_url = ? WHERE product_id = ?")->execute([$db_path, $product_id]);
                return true;
            }
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_type = $_POST['product_type'] ?? '';
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $sku = trim($_POST['sku']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $brand_id = !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : null;
    $description = trim($_POST['description']);

    if (empty($name) || empty($sku) || $price <= 0 || empty($product_type)) {
        $errors[] = "Пожалуйста, заполните все обязательные общие поля.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO Products (product_type, name, model, sku, price, stock, description, brand_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$product_type, $name, $model, $sku, $price, $stock, $description, $brand_id]);
            $product_id = $pdo->lastInsertId();

            if (isset($specs_config[$product_type])) {
                $config = $specs_config[$product_type];
                $cols = ['product_id', 'brand_id'];
                $vals = [$product_id, $brand_id];
                $qs = ['?', '?'];
                $posted = $_POST['specs'][$product_type] ?? [];

                foreach ($config['fields'] as $key => $conf) {
                    $cols[] = $key;
                    $qs[] = '?';
                    $v = $posted[$key] ?? null;
                    if ($conf['type'] === 'checkbox') $v = isset($posted[$key]) ? 1 : 0;
                    elseif ($conf['type'] === 'number' && $v === '') $v = null;
                    $vals[] = $v;
                }
                $sql = "INSERT INTO {$config['table']} (" . implode(',', $cols) . ") VALUES (" . implode(',', $qs) . ")";
                $pdo->prepare($sql)->execute($vals);
            }

            handleImageUpload($product_id);
            $pdo->commit();
            $success = "Товар успешно добавлен!";
            $_POST = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить товар</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'products'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Добавить новый товар</h1>

                <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
                <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>

                <div class="content-card">
                    <form method="post" enctype="multipart/form-data" id="productForm">
                        <h3>Общая информация</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Тип товара *</label>
                                <select name="product_type" id="typeSelector" required onchange="toggleSpecs()">
                                    <option value="">-- Выберите тип --</option>
                                    <?php foreach ($specs_config as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= (($_POST['product_type']??'') === $k)?'selected':'' ?>><?= $v['title'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Бренд</label>
                                <select name="brand_id">
                                    <option value="">-- Не выбран --</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?= $b['brand_id'] ?>" <?= (($_POST['brand_id']??0) == $b['brand_id'])?'selected':'' ?>><?= $b['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:2;">
                                <label>Название *</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($_POST['name']??'') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Модель</label>
                                <input type="text" name="model" value="<?= htmlspecialchars($_POST['model']??'') ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>SKU *</label>
                                <input type="text" name="sku" value="<?= htmlspecialchars($_POST['sku']??'') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Цена *</label>
                                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($_POST['price']??'') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Склад</label>
                                <input type="number" name="stock" value="<?= htmlspecialchars($_POST['stock']??0) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Фото</label>
                            <div class="file-upload-wrapper">
                                <label for="prod-img" class="btn-file-custom">Выберите файл</label>
                                <input type="file" name="image" id="prod-img" accept="image/*">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Описание</label>
                            <textarea name="description" rows="4"><?= htmlspecialchars($_POST['description']??'') ?></textarea>
                        </div>

                        <div id="specsContainer">
                            <?php foreach ($specs_config as $type => $conf): ?>
                                <div id="specs-<?= $type ?>" class="specs-section">
                                    <h4><?= $conf['title'] ?></h4>
                                    <div class="form-row">
                                        <?php foreach ($conf['fields'] as $fname => $fconf): ?>
                                            <div class="form-group">
                                                <label><?= $fconf['label'] ?> <?= $fconf['required']?'*':'' ?></label>
                                                <?php 
                                                    $val = $_POST['specs'][$type][$fname] ?? ''; 
                                                    $iname = "specs[$type][$fname]";
                                                ?>
                                                <?php if($fconf['type'] === 'checkbox'): ?>
                                                    <div style="margin-top:10px;">
                                                        <input type="checkbox" name="<?= $iname ?>" value="1" <?= $val?'checked':'' ?> style="width:auto;"> Да
                                                    </div>
                                                <?php else: ?>
                                                    <input type="<?= $fconf['type'] ?>" name="<?= $iname ?>" value="<?= htmlspecialchars($val) ?>" <?php if(isset($fconf['step'])) echo "step='{$fconf['step']}'"; ?>>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit" class="btn-primary">Добавить товар</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
    <script>
        function toggleSpecs() {
            document.querySelectorAll('.specs-section').forEach(s => s.classList.remove('active'));
            let type = document.getElementById('typeSelector').value;
            if(type) {
                let el = document.getElementById('specs-'+type);
                if(el) el.classList.add('active');
            }
        }
        document.addEventListener('DOMContentLoaded', toggleSpecs);
    </script>
</body>
</html>
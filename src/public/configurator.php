<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Получаем текущие выборы из URL
$sel_cpu_id = $_GET['cpu'] ?? null;
$sel_mobo_id = $_GET['mobo'] ?? null;
$sel_ram_id = $_GET['ram'] ?? null;
$sel_gpu_id = $_GET['gpu'] ?? null;

// Переменные для хранения характеристик выбранных деталей (для фильтрации)
$cpu_socket = null;
$mobo_socket = null;
$mobo_ram_type = null;

// --- 1. ЗАГРУЗКА ДАННЫХ ---

// А. Получаем список всех Процессоров
$cpus = $pdo->query("
    SELECT p.product_id, p.name, p.price, p.image_url, s.socket, s.base_frequency 
    FROM Products p 
    JOIN Processors s ON p.product_id = s.product_id 
    WHERE p.is_active = 1 AND p.stock > 0
")->fetchAll(PDO::FETCH_ASSOC);

// Если процессор выбран, получаем его сокет
if ($sel_cpu_id) {
    foreach ($cpus as $c) {
        if ($c['product_id'] == $sel_cpu_id) {
            $cpu_socket = $c['socket'];
            break;
        }
    }
}

// Б. Получаем Материнские платы (Фильтр по сокету CPU)
$mobo_sql = "
    SELECT p.product_id, p.name, p.price, p.image_url, s.socket, s.memory_type 
    FROM Products p 
    JOIN Motherboards s ON p.product_id = s.product_id 
    WHERE p.is_active = 1 AND p.stock > 0
";
$mobo_params = [];

if ($cpu_socket) {
    $mobo_sql .= " AND s.socket = ?";
    $mobo_params[] = $cpu_socket;
}

$stmt = $pdo->prepare($mobo_sql);
$stmt->execute($mobo_params);
$mobos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Если плата выбрана, получаем её тип памяти
if ($sel_mobo_id) {
    foreach ($mobos as $m) {
        if ($m['product_id'] == $sel_mobo_id) {
            $mobo_ram_type = $m['memory_type'];
            break;
        }
    }
}

// В. Получаем ОЗУ (Фильтр по типу памяти материнки)
$ram_sql = "
    SELECT p.product_id, p.name, p.price, p.image_url, s.type, s.capacity, s.frequency 
    FROM Products p 
    JOIN RAM s ON p.product_id = s.product_id 
    WHERE p.is_active = 1 AND p.stock > 0
";
$ram_params = [];

if ($mobo_ram_type) {
    $ram_sql .= " AND s.type = ?";
    $ram_params[] = $mobo_ram_type;
}

$stmt = $pdo->prepare($ram_sql);
$stmt->execute($ram_params);
$rams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Г. Получаем Видеокарты (Без жесткой фильтрации пока)
$gpus = $pdo->query("
    SELECT p.product_id, p.name, p.price, p.image_url, s.memory_size 
    FROM Products p 
    JOIN Videocards s ON p.product_id = s.product_id 
    WHERE p.is_active = 1 AND p.stock > 0
")->fetchAll(PDO::FETCH_ASSOC);


// --- 2. ОБРАБОТКА ДОБАВЛЕНИЯ В КОРЗИНУ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_build'])) {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $items_to_add = [];
    if ($sel_cpu_id) $items_to_add[] = $sel_cpu_id;
    if ($sel_mobo_id) $items_to_add[] = $sel_mobo_id;
    if ($sel_ram_id) $items_to_add[] = $sel_ram_id;
    if ($sel_gpu_id) $items_to_add[] = $sel_gpu_id;

    if (!empty($items_to_add)) {
        try {
            $pdo->beginTransaction();
            foreach ($items_to_add as $pid) {
                // Простая логика: если есть - +1, если нет - вставка
                $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM CartItems WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $pid]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $pdo->prepare("UPDATE CartItems SET quantity = quantity + 1 WHERE cart_item_id = ?")->execute([$existing['cart_item_id']]);
                } else {
                    $pdo->prepare("INSERT INTO CartItems (user_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$user_id, $pid]);
                }
            }
            $pdo->commit();
            
            // Обновляем счетчик сессии (грубо, но работает)
            $_SESSION['cart_count'] = ($_SESSION['cart_count'] ?? 0) + count($items_to_add);
            
            header("Location: /cart.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ошибка при добавлении: " . $e->getMessage();
        }
    }
}

// Подсчет итоговой суммы
$total_price = 0;
// Функция-помощник для поиска цены
function getPrice($list, $id) {
    foreach($list as $item) if($item['product_id'] == $id) return $item['price'];
    return 0;
}
if($sel_cpu_id) $total_price += getPrice($cpus, $sel_cpu_id);
if($sel_mobo_id) $total_price += getPrice($mobos, $sel_mobo_id);
if($sel_ram_id) $total_price += getPrice($rams, $sel_ram_id);
if($sel_gpu_id) $total_price += getPrice($gpus, $sel_gpu_id);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Конфигуратор ПК</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container" style="flex-direction: column;"> <div class="content-area" style="width: 100%;">
                <h1>Конфигуратор ПК</h1>
                <p style="color: #666; margin-bottom: 30px;">Система автоматически подберет совместимые комплектующие.</p>

                <form method="get" id="configForm" class="content-card">
                    
                    <div class="config-step">
                        <h3>1. Процессор</h3>
                        <div class="form-group">
                            <select name="cpu" onchange="document.getElementById('configForm').submit()" class="config-select">
                                <option value="">-- Выберите процессор --</option>
                                <?php foreach ($cpus as $c): ?>
                                    <option value="<?= $c['product_id'] ?>" <?= $sel_cpu_id == $c['product_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?> [Socket: <?= $c['socket'] ?>] - <?= formatPrice($c['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="config-step <?= !$sel_cpu_id ? 'disabled-step' : '' ?>">
                        <h3>2. Материнская плата 
                            <?php if($sel_cpu_id && empty($mobos)): ?>
                                <span style="color: red; font-size: 0.8em;">(Нет совместимых плат в наличии)</span>
                            <?php endif; ?>
                        </h3>
                        <div class="form-group">
                            <select name="mobo" onchange="document.getElementById('configForm').submit()" class="config-select" <?= !$sel_cpu_id ? 'disabled' : '' ?>>
                                <option value="">-- Выберите плату --</option>
                                <?php foreach ($mobos as $m): ?>
                                    <option value="<?= $m['product_id'] ?>" <?= $sel_mobo_id == $m['product_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['name']) ?> [<?= $m['socket'] ?>, <?= $m['memory_type'] ?>] - <?= formatPrice($m['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if(!$sel_cpu_id): ?>
                            <small class="hint">Сначала выберите процессор</small>
                        <?php endif; ?>
                    </div>

                    <div class="config-step <?= !$sel_mobo_id ? 'disabled-step' : '' ?>">
                        <h3>3. Оперативная память</h3>
                        <div class="form-group">
                            <select name="ram" onchange="document.getElementById('configForm').submit()" class="config-select" <?= !$sel_mobo_id ? 'disabled' : '' ?>>
                                <option value="">-- Выберите память --</option>
                                <?php foreach ($rams as $r): ?>
                                    <option value="<?= $r['product_id'] ?>" <?= $sel_ram_id == $r['product_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?> [<?= $r['type'] ?>, <?= $r['frequency'] ?>MHz] - <?= formatPrice($r['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if(!$sel_mobo_id): ?>
                            <small class="hint">Сначала выберите материнскую плату</small>
                        <?php endif; ?>
                    </div>

                    <div class="config-step">
                        <h3>4. Видеокарта (Опционально)</h3>
                        <div class="form-group">
                            <select name="gpu" onchange="document.getElementById('configForm').submit()" class="config-select">
                                <option value="">-- Не выбрано --</option>
                                <?php foreach ($gpus as $g): ?>
                                    <option value="<?= $g['product_id'] ?>" <?= $sel_gpu_id == $g['product_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['name']) ?> - <?= formatPrice($g['price']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                </form>

                <div class="content-card summary-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2>Итого: <span style="color: #0156FF;"><?= formatPrice($total_price) ?></span></h2>
                            <small>Выбрано компонентов: <?= ($sel_cpu_id?1:0)+($sel_mobo_id?1:0)+($sel_ram_id?1:0)+($sel_gpu_id?1:0) ?></small>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="add_build" value="1">
                            <?php if ($total_price > 0): ?>
                                <button type="submit" class="btn-primary" style="font-size: 1.2em; padding: 15px 30px;">
                                    В корзину
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-primary" disabled style="background: #ccc;">Выберите товары</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';
$export_types = [
    'orders' => 'Заказы (Orders)',
    'products' => 'Товары (Products)', 
    'users' => 'Пользователи (Users)',
    'reviews' => 'Отзывы (Reviews)',
    'transactions' => 'Транзакции (Transactions)'
];

// Логика экспорта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $export_type = $_POST['export_type'];
    $format = $_POST['format'] ?? 'csv';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    // --- ИСПРАВЛЕНИЕ: Определяем точное имя колонки даты для каждого типа ---
    // Это решает ошибку "Column created_at is ambiguous"
    $date_column = 'created_at'; // По умолчанию
    
    switch ($export_type) {
        case 'orders':       $date_column = 'o.created_at'; break; // o = Orders
        case 'products':     $date_column = 'p.created_at'; break; // p = Products
        case 'users':        $date_column = 'Users.created_at'; break; // Таблица Users
        case 'reviews':      $date_column = 'r.created_at'; break; // r = Reviews
        case 'transactions': $date_column = 't.created_at'; break; // t = BalanceTransactions
    }
    
    $where = [];
    $params = [];
    
    // Фильтр по дате (теперь используем конкретную колонку $date_column)
    if ($start_date && $end_date) {
        $where[] = "DATE($date_column) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif ($start_date) {
        $where[] = "DATE($date_column) >= ?";
        $params[] = $start_date;
    } elseif ($end_date) {
        $where[] = "DATE($date_column) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // SQL запросы
    switch ($export_type) {
        case 'orders':
            // Используем алиас 'o' для Orders
            $query = "SELECT o.order_id, o.user_id, CONCAT(u.first_name, ' ', u.last_name) as customer, u.email, o.total_amount, o.status, o.payment_method, o.created_at FROM Orders o JOIN Users u ON o.user_id = u.user_id $where_sql ORDER BY o.created_at DESC";
            $filename = "orders_" . date('Y-m-d') . ".$format";
            break;
            
        case 'products':
            // Используем алиас 'p' для Products
            $query = "SELECT p.product_id, p.product_type, p.name, p.model, p.sku, p.price, p.stock, b.name as brand_name FROM Products p LEFT JOIN Brands b ON p.brand_id = b.brand_id $where_sql ORDER BY p.created_at DESC";
            $filename = "products_" . date('Y-m-d') . ".$format";
            break;
            
        case 'users':
            // Без алиаса, но имя таблицы указано в $date_column
            $query = "SELECT user_id, email, first_name, last_name, role, balance, is_active, created_at FROM Users $where_sql ORDER BY created_at DESC";
            $filename = "users_" . date('Y-m-d') . ".$format";
            break;

        case 'reviews':
            // Используем алиас 'r' для Reviews
            $query = "SELECT r.review_id, r.rating, r.comment, p.name as product_name, r.created_at FROM Reviews r JOIN Products p ON r.product_id = p.product_id $where_sql ORDER BY r.created_at DESC";
            $filename = "reviews_" . date('Y-m-d') . ".$format";
            break;
            
        case 'transactions':
            // Используем алиас 't' для BalanceTransactions
            $query = "SELECT t.*, u.email FROM BalanceTransactions t JOIN Users u ON t.user_id = u.user_id $where_sql ORDER BY t.created_at DESC";
            $filename = "transactions_" . date('Y-m-d') . ".$format";
            break;
            
        default:
            $errors[] = "Неверный тип экспорта";
            break;
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($data)) {
                $errors[] = "Нет данных за выбранный период";
            } else {
                // Очистка буфера вывода перед скачиванием
                if (ob_get_level()) ob_end_clean();
                
                if ($format === 'csv') {
                    exportToCSV($data, $filename);
                } elseif ($format === 'json') {
                    exportToJSON($data, $filename);
                }
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка БД: " . $e->getMessage();
        }
    }
}

// Вспомогательные функции
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputs($output, "\xEF\xBB\xBF"); // BOM для Excel
        fputcsv($output, array_keys($data[0]), ";");
        foreach ($data as $row) {
            fputcsv($output, $row, ";");
        }
    }
    fclose($output);
}

function exportToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Экспорт данных</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    
    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'export'; require_once __DIR__ . '/sidebar_admin.php'; ?>
            
            <div class="admin-main">
                <h1>Экспорт данных</h1>
                
                <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
                <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>
                
                <div class="content-card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Что экспортировать *</label>
                                <select name="export_type" required>
                                    <option value="">-- Выберите сущность --</option>
                                    <?php foreach ($export_types as $key => $name): ?>
                                        <option value="<?= $key ?>" <?= ($_POST['export_type'] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= $name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Формат файла *</label>
                                <select name="format" required>
                                    <option value="csv" <?= ($_POST['format'] ?? 'csv') === 'csv' ? 'selected' : '' ?>>CSV (Excel)</option>
                                    <option value="json" <?= ($_POST['format'] ?? '') === 'json' ? 'selected' : '' ?>>JSON</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Начальная дата</label>
                                <input type="date" name="start_date" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Конечная дата</label>
                                <input type="date" name="end_date" value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn-primary">Скачать файл</button>
                        </div>
                    </form>
                </div>
                
                <div class="content-card">
                    <h3>Сводка базы данных</h3>
                    <div class="stats-grid">
                        <?php
                        $stats = [
                            'Заказы' => $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn(),
                            'Товары' => $pdo->query("SELECT COUNT(*) FROM Products")->fetchColumn(),
                            'Пользователи' => $pdo->query("SELECT COUNT(*) FROM Users")->fetchColumn(),
                            'Отзывы' => $pdo->query("SELECT COUNT(*) FROM Reviews")->fetchColumn(),
                        ];
                        
                        foreach ($stats as $label => $value): ?>
                        <div class="stat-card" style="padding: 15px;">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;"><?= $label ?></div>
                            <div style="font-size: 24px; font-weight: bold; color: #0156FF; margin-top: 5px;"><?= number_format($value) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
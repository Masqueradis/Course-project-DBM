<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

$kpi = [
    'revenue' => $pdo->query("SELECT SUM(total_amount) FROM Orders WHERE status != 'cancelled'")->fetchColumn() ?: 0,
    'orders_count' => $pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn(),
    'avg_check' => 0
];
if ($kpi['orders_count'] > 0) {
    $kpi['avg_check'] = $kpi['revenue'] / $kpi['orders_count'];
}

$stmt = $pdo->query("
    SELECT DATE(created_at) as sale_date, SUM(total_amount) as total 
    FROM Orders 
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY sale_date ASC
");
$raw_sales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$dates = []; $sales_values = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('d.m', strtotime($d));
    $sales_values[] = $raw_sales[$d] ?? 0;
}

$stmt = $pdo->query("SELECT status, COUNT(*) FROM Orders GROUP BY status");
$raw_statuses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$status_map = ['pending' => 'Ожидают', 'confirmed' => 'Подтверждены', 'completed' => 'Выполнены', 'cancelled' => 'Отменены'];
$status_labels = []; $status_data = [];
foreach($raw_statuses as $k => $v) {
    $status_labels[] = $status_map[$k] ?? $k;
    $status_data[] = $v;
}

$stmt = $pdo->query("
    SELECT product_name, SUM(quantity) as qty 
    FROM OrderItems 
    GROUP BY product_id, product_name 
    ORDER BY qty DESC 
    LIMIT 5
");
$top_products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$top_names = array_keys($top_products);
$top_qty = array_values($top_products);

$low_stock_items = $pdo->query("
    SELECT product_id, name, stock, price 
    FROM Products 
    WHERE stock <= 5 AND is_active = 1 
    ORDER BY stock ASC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$reviews_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
    FROM Reviews
")->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT rating, COUNT(*) FROM Reviews GROUP BY rating ORDER BY rating DESC");
$raw_ratings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$stars_labels = ['5 звезд', '4 звезды', '3 звезды', '2 звезды', '1 звезда'];
$stars_data = [
    $raw_ratings[5] ?? 0,
    $raw_ratings[4] ?? 0,
    $raw_ratings[3] ?? 0,
    $raw_ratings[2] ?? 0,
    $raw_ratings[1] ?? 0
];

$users_count = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'user'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Аналитика продаж</title>
    <link rel="stylesheet" href="/css/css.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 30px;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: background 0.2s;
            user-select: none;
        }
        .section-header:hover {
            background: #f8f9fa;
        }
        .section-header h2 { margin: 0; color: #333; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .toggle-icon { font-size: 14px; color: #999; transition: transform 0.3s; }
        .section-header.collapsed .toggle-icon { transform: rotate(-90deg); }
        
        .section-content {
            overflow: hidden;
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
            opacity: 1;
            max-height: 2000px;
        }
        .section-content.hidden {
            max-height: 0;
            opacity: 0;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'analytics'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h1 style="margin-bottom: 10px;">Аналитическая панель</h1>
                    <button onclick="toggleAll()" class="btn-small" style="background: #eee; color: #333;">Свернуть/Развернуть всё</button>
                </div>
                <p style="color: #666; margin-bottom: 20px;">Кликните на заголовок секции, чтобы скрыть её.</p>

                <div class="section-header" onclick="toggleSection('finance-content', this)">
                    <h2>Финансы и Заказы</h2>
                    <span class="toggle-icon">▼</span>
                </div>

                <div id="finance-content" class="section-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Общая выручка</div>
                            <div class="stat-number" style="color: #28a745;"><?= formatPrice($kpi['revenue']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Средний чек</div>
                            <div class="stat-number"><?= formatPrice($kpi['avg_check']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Всего заказов</div>
                            <div class="stat-number" style="color: #0156FF;"><?= $kpi['orders_count'] ?></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                        <div class="content-card">
                            <h3 style="margin-top: 0;">Динамика выручки (30 дней)</h3>
                            <div style="height: 300px;">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                        <div class="content-card">
                            <h3 style="margin-top: 0;">Статусы заказов</h3>
                            <div style="height: 250px; display: flex; justify-content: center; align-items: center;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="section-header" onclick="toggleSection('products-content', this)">
                    <h2>Товары и Склад</h2>
                    <span class="toggle-icon">▼</span>
                </div>

                <div id="products-content" class="section-content">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                        <div class="content-card">
                            <h3 style="margin-top: 0;">Топ-5 самых популярных</h3>
                            <div style="height: 250px;">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>

                        <div class="content-card">
                            <h3 style="margin-top: 0; color: #dc3545;">⚠️ Заканчиваются (менее 5 шт)</h3>
                            <?php if (!empty($low_stock_items)): ?>
                                <div class="data-table">
                                    <table style="font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th>Товар</th>
                                                <th>Остаток</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_items as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td style="color: red; font-weight: bold;"><?= $item['stock'] ?></td>
                                                    <td style="text-align: right;"><a href="editproduct.php?id=<?= $item['product_id'] ?>" style="color: #0156FF;">Пополнить</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: green;">Все товары в наличии ✔</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>


                <div class="section-header" onclick="toggleSection('reviews-content', this)">
                    <h2>Отзывы и Клиенты</h2>
                    <span class="toggle-icon">▼</span>
                </div>

                <div id="reviews-content" class="section-content">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Всего клиентов</div>
                            <div class="stat-number" style="color: #6610f2;"><?= $users_count ?></div>
                        </div>
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Средняя оценка</div>
                            <div class="stat-number" style="color: #ffc107;">
                                <?= number_format((float)$reviews_stats['avg_rating'], 1) ?> 
                                <span style="font-size: 0.5em; color: #999;">/ 5</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Всего отзывов</div>
                            <div class="stat-number"><?= (int)$reviews_stats['total'] ?></div>
                        </div>
                        <div class="stat-card" style="<?= $reviews_stats['pending'] > 0 ? 'border: 1px solid #ffc107;' : '' ?>">
                            <div style="font-size: 13px; color: #666; text-transform: uppercase;">Ждут модерации</div>
                            <div class="stat-number" style="color: <?= $reviews_stats['pending'] > 0 ? '#ffc107' : '#28a745' ?>;">
                                <?= (int)$reviews_stats['pending'] ?>
                            </div>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3 style="margin-top: 0;">Распределение оценок</h3>
                        <div style="height: 200px;">
                            <canvas id="reviewsChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function toggleSection(contentId, headerElement) {
            const content = document.getElementById(contentId);
            content.classList.toggle('hidden');
            headerElement.classList.toggle('collapsed');
        }

        function toggleAll() {
            const contents = document.querySelectorAll('.section-content');
            const headers = document.querySelectorAll('.section-header');
            
            const isFirstOpen = !contents[0].classList.contains('hidden');
            
            contents.forEach(el => isFirstOpen ? el.classList.add('hidden') : el.classList.remove('hidden'));
            headers.forEach(el => isFirstOpen ? el.classList.add('collapsed') : el.classList.remove('collapsed'));
        }

        new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Выручка ($)',
                    data: <?= json_encode($sales_values) ?>,
                    borderColor: '#0156FF',
                    backgroundColor: 'rgba(1, 86, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });

        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels) ?>,
                datasets: [{
                    data: <?= json_encode($status_data) ?>,
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });

        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($top_names) ?>,
                datasets: [{
                    label: 'Продано (шт)',
                    data: <?= json_encode($top_qty) ?>,
                    backgroundColor: '#17a2b8',
                    borderRadius: 4
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
        });

        new Chart(document.getElementById('reviewsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($stars_labels) ?>,
                datasets: [{
                    label: 'Оценок',
                    data: <?= json_encode($stars_data) ?>,
                    backgroundColor: ['#28a745', '#90ee90', '#ffc107', '#fd7e14', '#dc3545'],
                    borderRadius: 4,
                    barThickness: 30
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                plugins: { legend: { display: false } }
            }
        });
    </script>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
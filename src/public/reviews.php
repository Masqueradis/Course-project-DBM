<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

// Поиск
$search = trim($_GET['search'] ?? '');
$params = [];
$where = "WHERE 1=1";

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR u.email LIKE ? OR r.comment LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Обработка действий (оставляем как было)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $review_id = intval($_POST['review_id']);
    if ($_POST['action'] === 'toggle_approve') {
        $pdo->prepare("UPDATE Reviews SET is_approved = NOT is_approved WHERE review_id = ?")->execute([$review_id]);
    } elseif ($_POST['action'] === 'delete_review') {
        $pdo->prepare("DELETE FROM Reviews WHERE review_id = ?")->execute([$review_id]);
    }
}

// Запрос с учетом поиска
$sql = "
    SELECT r.*, u.first_name, u.last_name, u.email, p.name as product_name
    FROM Reviews r
    JOIN Users u ON r.user_id = u.user_id
    JOIN Products p ON r.product_id = p.product_id
    $where
    ORDER BY r.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление отзывами</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'reviews'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Отзывы пользователей</h1>

                <div class="content-card">
                    <form method="get" class="form-row" style="align-items: flex-end; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <label>Поиск отзыва</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Название товара, email пользователя или текст отзыва...">
                        </div>
                        <div class="form-group" style="flex: 0;">
                            <button type="submit" class="btn-primary">Найти</button>
                        </div>
                        <?php if($search): ?>
                            <div class="form-group" style="flex: 0;">
                                <a href="reviews.php" class="btn-small btn-danger" style="display:block; padding: 11px 15px;">Сброс</a>
                            </div>
                        <?php endif; ?>
                    </form>

                    <div class="data-table">
                        <?php if (empty($reviews)): ?>
                            <p style="text-align: center; color: #777;">Отзывов не найдено.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th width="20%">Пользователь</th>
                                        <th width="25%">Товар</th>
                                        <th width="10%">Оценка</th>
                                        <th>Комментарий</th>
                                        <th width="10%">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $r): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?><br>
                                                <small><?= htmlspecialchars($r['email']) ?></small>
                                            </td>
                                            <td>
                                                <a href="product.php?id=<?= $r['product_id'] ?>" target="_blank" style="color: #0156FF; text-decoration: underline;">
                                                    <?= htmlspecialchars(mb_strimwidth($r['product_name'], 0, 40, '...')) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span style="color: #ffc107; font-weight: bold;"><?= str_repeat('★', $r['rating']) ?></span>
                                            </td>
                                            <td>
                                                <?= nl2br(htmlspecialchars($r['comment'])) ?><br>
                                                <small style="color: #999;"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></small>
                                                <?php if (!$r['is_approved']): ?>
                                                    <br><span style="color: red; font-size: 11px; font-weight: bold;">(На модерации)</span>
                                                <?php endif; ?>
                                                <?php if ($r['is_verified_purchase']): ?>
                                                    <br><span style="color: green; font-size: 11px;">✓ Купил товар</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px; flex-direction: column;">
                                                    <form method="post">
                                                        <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                                        <button type="submit" name="action" value="toggle_approve" class="btn-small <?= $r['is_approved'] ? 'btn-danger' : 'btn-success' ?>" style="width: 100%;">
                                                            <?= $r['is_approved'] ? 'Скрыть' : 'Одобрить' ?>
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                                        <button type="submit" name="action" value="delete_review" class="btn-small btn-danger" style="width: 100%;" onclick="return confirm('Удалить этот отзыв навсегда?')">Удалить</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
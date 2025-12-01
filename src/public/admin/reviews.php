<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';
redirectIfNotAdmin();

$errors = [];
$success = '';

// Обработка действий с отзывами
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $review_id = intval($_POST['review_id'] ?? 0);
        
        switch ($_POST['action']) {
            case 'delete_review':
                $stmt = $pdo->prepare("DELETE FROM Reviews WHERE review_id = ?");
                if ($stmt->execute([$review_id])) {
                    $success = "Отзыв удален";
                } else {
                    $errors[] = "Ошибка при удалении отзыва";
                }
                break;
                
            case 'toggle_approve':
                $stmt = $pdo->prepare("UPDATE Reviews SET is_approved = NOT is_approved WHERE review_id = ?");
                $stmt->execute([$review_id]);
                $success = "Статус отзыва изменен";
                break;
                
            case 'block_user_and_delete':
                $user_id = intval($_POST['user_id']);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Блокируем пользователя
                    $stmt = $pdo->prepare("UPDATE Users SET is_active = 0 WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Удаляем отзыв
                    $stmt = $pdo->prepare("DELETE FROM Reviews WHERE review_id = ?");
                    $stmt->execute([$review_id]);
                    
                    $pdo->commit();
                    $success = "Пользователь заблокирован и отзыв удален";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = "Ошибка: " . $e->getMessage();
                }
                break;
        }
    }
}

// Получение всех отзывов
$stmt = $pdo->query("
    SELECT r.*, 
           u.first_name, 
           u.last_name, 
           u.email,
           u.is_active as user_active,
           p.name as product_name,
           p.product_id
    FROM Reviews r
    JOIN Users u ON r.user_id = u.user_id
    JOIN Products p ON r.product_id = p.product_id
    ORDER BY r.created_at DESC
");
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление отзывами</title>
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
                <li><a href="products.php">Товары</a></li>
                <li><a href="orders.php">Заказы</a></li>
                <li><a href="reviews.php" class="active">Отзывы</a></li>
                <li><a href="/account/index.php">Личный кабинет</a></li>
            </ul>
        </div>

        <div class="admin-main">
            <h1>Управление отзывами</h1>

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

            <div class="reviews-admin-table">
                <?php if (empty($reviews)): ?>
                    <div class="alert info" style="margin: 20px;">
                        <p>Отзывов пока нет</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-admin-item">
                            <div class="review-meta">
                                <div>
                                    <strong><?= htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) ?></strong>
                                    (<?= htmlspecialchars($review['email']) ?>)
                                    <?php if (!$review['user_active']): ?>
                                        <span class="error" style="padding: 2px 6px; border-radius: 4px; font-size: 12px;">Заблокирован</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?= date('d.m.Y H:i', strtotime($review['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 10px;">
                                <strong>Товар:</strong> 
                                <a href="/product.php?id=<?= $review['product_id'] ?>" target="_blank">
                                    <?= htmlspecialchars($review['product_name']) ?>
                                </a>
                            </div>
                            
                            <div class="review-content">
                                <div style="color: #ffc107; margin-bottom: 5px;">
                                    <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                                </div>
                                
                                <?php if (!empty($review['title'])): ?>
                                    <div style="font-weight: bold; margin-bottom: 5px;"><?= htmlspecialchars($review['title']) ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($review['comment'])): ?>
                                    <div style="margin-bottom: 10px;"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
                                <?php endif; ?>
                                
                                <div style="font-size: 12px; color: #666;">
                                    Статус: <?= $review['is_approved'] ? 'Одобрен' : 'На модерации' ?>
                                    <?= $review['is_verified_purchase'] ? ' • Подтвержденная покупка' : '' ?>
                                </div>
                            </div>
                            
                            <div class="review-actions">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="review_id" value="<?= $review['review_id'] ?>">
                                    <button type="submit" name="action" value="toggle_approve" class="btn-small <?= $review['is_approved'] ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $review['is_approved'] ? 'Снять одобрение' : 'Одобрить' ?>
                                    </button>
                                </form>
                                
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="review_id" value="<?= $review['review_id'] ?>">
                                    <button type="submit" name="action" value="delete_review" class="btn-small btn-danger" onclick="return confirm('Удалить этот отзыв?')">Удалить</button>
                                </form>
                                
                                <?php if ($review['user_active']): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="review_id" value="<?= $review['review_id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $review['user_id'] ?>">
                                        <button type="submit" name="action" value="block_user_and_delete" class="btn-small btn-danger" onclick="return confirm('Заблокировать пользователя и удалить отзыв?')">Блокировать пользователя</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../blocks/footer.php'; ?>
</body>
</html>
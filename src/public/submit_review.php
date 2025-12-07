<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if ($rating < 1 || $rating > 5) {
        die("Некорректный рейтинг");
    }

    try {
        // Проверяем, покупал ли пользователь этот товар (для метки "Проверенная покупка")
        // Это опционально, но полезно
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM OrderItems oi 
            JOIN Orders o ON oi.order_id = o.order_id 
            WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$user_id, $product_id]);
        $is_verified = $stmt->fetchColumn() > 0 ? 1 : 0;

        // Вставляем отзыв
        // ON DUPLICATE KEY UPDATE позволяет обновить отзыв, если пользователь уже писал
        $stmt = $pdo->prepare("
            INSERT INTO Reviews (user_id, product_id, rating, comment, is_verified_purchase, is_approved, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), is_approved = 0
        ");
        
        $stmt->execute([$user_id, $product_id, $rating, $comment, $is_verified]);

        // Перенаправляем обратно на товар с сообщением
        header("Location: product.php?id=$product_id&review_submitted=1");
        exit;

    } catch (PDOException $e) {
        die("Ошибка при добавлении отзыва: " . $e->getMessage());
    }
} else {
    header("Location: /");
}
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Получаем URL предыдущей страницы, чтобы вернуть пользователя туда же
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // 1. Сохраняем в БД (игнорируем дубликаты благодаря INSERT IGNORE)
            // Если такой email есть, просто ничего не делаем в БД, но письмо отправим (или скажем, что уже подписан)
            
            // Проверка на дубликат
            $stmt = $pdo->prepare("SELECT id FROM Subscribers WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                // Уже подписан
                header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "subscribe_msg=exists");
                exit;
            } else {
                // Добавляем
                $stmt = $pdo->prepare("INSERT INTO Subscribers (email) VALUES (?)");
                $stmt->execute([$email]);
                
                // 2. Отправляем письмо
                $db = new Database();
                $db->sendSubscriptionEmail($email);
                
                header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "subscribe_msg=success");
                exit;
            }

        } catch (Exception $e) {
            header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "subscribe_msg=error");
            exit;
        }
    } else {
        header("Location: $redirect_url" . (strpos($redirect_url, '?') ? '&' : '?') . "subscribe_msg=invalid");
        exit;
    }
} else {
    header("Location: /");
    exit;
}
<?php
// === БАЗОВЫЕ НАСТРОЙКИ (Вручную) ===
date_default_timezone_set('Europe/Moscow');

// === ФУНКЦИИ АВТОРИЗАЦИИ ===

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
}

function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header("Location: /");
        exit;
    }
}

function getUserInfo() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $stmt = $pdo->prepare("SELECT user_id, email, first_name, last_name, balance, avatar_url, is_verified, role, address, phone, created_at FROM Users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// === ФУНКЦИЯ ЦЕНЫ ===
function formatPrice($price) {
    // Настраиваем формат вручную здесь
    // 0 знаков после запятой, точка, пробел как разделитель тысяч
    return number_format((float)$price, 0, '.', ' ') . ' $';
}
?>
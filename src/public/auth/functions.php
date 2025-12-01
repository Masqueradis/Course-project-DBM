<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
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
        $stmt = $pdo->prepare("SELECT user_id, email, first_name, last_name, balance, avatar_url, is_verified, role, created_at FROM Users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

function isVerified() {
    $user = getUserInfo();
    return $user && $user['is_verified'];
}
?>
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user = getUserInfo();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/../blocks/header.php'; ?>

    <div class="account-container">
        <div class="sidebar">
            <h3>Личный кабинет</h3>
            <ul>
                <li><a href="/../index.php">Профиль</a></li>
                <li><a href="balance.php">Баланс</a></li>
                <li><a href="orders.php" class="active">Мои заказы</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="admin.php">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Мои заказы</h1>
            <p>Здесь будут отображаться ваши заказы...</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../blocks/footer.php'; ?>
</body>
</html>
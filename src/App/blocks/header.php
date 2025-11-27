<?php
// Подключаем конфигурацию БД
require_once __DIR__ . '/../config/database.php';

// Функции аутентификации
require_once __DIR__ . '/../auth/functions.php';

$user_info = isLoggedIn() ? getUserInfo() : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Магазин компьютерных комплектующих</title>
    
    <!-- Добавьте эти строки для favicon -->
    <link rel="icon" type="image/x-icon" href="/image/bottomheaderlogo.png">
    <link rel="shortcut icon" href="/image/bottomheaderlogo.png">
    <link rel="apple-touch-icon" href="/image/bottomheaderlogo.png">
    
    <link rel="stylesheet" href="/css/css.css">
    <link rel="stylesheet" href="/css/account.css">
</head>
<body>
<header> 
    <div class="topheader">
        <div>Пн-Пт: 9:00 - 17:30</div>
        <div>Улица Пушкина, дом Колотушкина 32</div>
        <div>Контакты: +375 29 32223 17 74</div>
    </div>

    <div class="bottomheader">
        <a href="/"><img src="/image/bottomheaderlogo.png" alt="Logo" style="height: 40px; width: auto;"></a>
        <div>Процессоры</div>
        <div>Видеокарты</div>
        <div>Блоки питания</div>
        <div>Материнские платы</div>
        <div>Накопители</div>
        <div>Оперативная память</div>
        <div class="userheaderinfo">
            <img src="/image/gg_search.png" alt="Search" style="width: 24px; height: 24px;">
            <img src="/image/jam_shopping-cart.png" alt="Cart" style="width: 24px; height: 24px;">
            
            <?php if (isLoggedIn()): ?>
                <div class="user-dropdown">
                    <div class="user-avatar">
                        <?php if (!empty($user_info['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user_info['avatar_url']) ?>" alt="Аватар" class="avatar-small">
                        <?php else: ?>
                            <div class="avatar-placeholder-small">
                                <?= strtoupper(substr($user_info['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?= htmlspecialchars($user_info['first_name'] ?? 'Пользователь') ?></span>
                    <div class="dropdown-content">
                        <a href="/account.php">Личный кабинет</a>
                        <a href="/balance.php">Баланс: <?= number_format($user_info['balance'] ?? 0, 2) ?> руб.</a>
                        <?php if (isAdmin()): ?>
                            <a href="/admin.php">Админ панель</a>
                        <?php endif; ?>
                        <a href="/logout.php">Выйти</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-links">
                    <a href="/login.php" class="login-btn">Войти</a>
                    <a href="/register.php" class="register-btn">Регистрация</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (!isset($user_info)) {
    $user_info = isLoggedIn() ? getUserInfo() : null;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('SITE_NAME') ? SITE_NAME : 'Магазин' ?></title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>

<header> 
    <div class="topheader">
        <div class="container-wrap topheader-inner">
            <div>Пн-Пт: 9:00 - 17:30</div>
            <div>Улица Пушкина, дом Колотушкина 32</div>
            <div>Контакты: +375 29 32223 17 74</div>
        </div>
    </div>

    <div class="bottomheader">
        <div class="container-wrap bottomheader-inner">
            <a href="/"><img src="/image/bottomheaderlogo.png" alt="Logo" style="height: 40px;"></a>
            
            <div class="nav-links">
                <a href="/configurator.php" style="color: #0156FF;"><div>Конфигуратор</div></a>
                <a href="/catalog.php?type=processor"><div>Процессоры</div></a>
                <a href="/catalog.php?type=videocard"><div>Видеокарты</div></a>
                <a href="/catalog.php?type=motherboard"><div>Материнские платы</div></a>
                <a href="/catalog.php?type=ram"><div>Оперативная память</div></a>
                <a href="/catalog.php?type=ssd"><div>SSD Накопители</div></a>
            </div>
            
            <div class="userheaderinfo">
                <a href="/catalog.php"><img src="/image/gg_search.png" alt="Поиск" style="width: 24px;"></a>
                
                <div class="cart-icon">
                    <a href="/cart.php">
                        <img src="/image/jam_shopping-cart.png" alt="Корзина" style="width: 24px;">
                        <?php if (isLoggedIn() && isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                            <span class="cart-badge"><?= $_SESSION['cart_count'] ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <?php if (isLoggedIn() && $user_info): ?>
                    <div class="user-dropdown" onclick="window.location.href='/account.php'">
                        <?php if (!empty($user_info['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user_info['avatar_url']) ?>" class="avatar-small">
                        <?php else: ?>
                            <div class="avatar-placeholder-small">
                                <?= strtoupper(substr($user_info['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <span style="font-weight: 500; font-size: 14px;">
                            <?= htmlspecialchars($user_info['first_name']) ?>
                        </span>

                        <div class="dropdown-content" onclick="event.stopPropagation()">
                            <div style="padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #eee; font-weight: bold; font-size: 12px; color: #666;">
                                Баланс: <?= formatPrice($user_info['balance']) ?>
                            </div>
                            <a href="account.php">Личный кабинет</a>
                            <a href="orders.php">Мои заказы</a>
                            <?php if (isAdmin()): ?>
                                <a href="admin.php" style="color: #d63384;">Админ панель</a>
                            <?php endif; ?>
                            <a href="logout.php">Выйти</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="header-auth">
                        <a href="login.php" class="auth-btn-login">Войти</a>
                        <a href="register.php" class="auth-btn-register">Регистрация</a>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
<div class="main-wrapper">
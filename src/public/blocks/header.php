<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';

$user_info = isLoggedIn() ? getUserInfo() : null;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Магазин компьютерных комплектующих</title>
    
    <link rel="icon" type="image/x-icon" href="/image/bottomheaderlogo.png">
    <link rel="shortcut icon" href="/image/bottomheaderlogo.png">
    <link rel="apple-touch-icon" href="/image/bottomheaderlogo.png">
    
    <link rel="stylesheet" href="/css/css.css">
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
        
        <!-- Категории -->
        <a href="/catalog.php?type=processor" style="text-decoration: none; color: inherit;"><div>Процессоры</div></a>
        <a href="/catalog.php?type=videocard" style="text-decoration: none; color: inherit;"><div>Видеокарты</div></a>
        <a href="/catalog.php?type=powersupply" style="text-decoration: none; color: inherit;"><div>Блоки питания</div></a>
        <a href="/catalog.php?type=motherboard" style="text-decoration: none; color: inherit;"><div>Материнские платы</div></a>
        <a href="/catalog.php?type=ssd" style="text-decoration: none; color: inherit;"><div>Накопители</div></a>
        <a href="/catalog.php?type=ram" style="text-decoration: none; color: inherit;"><div>Оперативная память</div></a>
        
        <div class="userheaderinfo">
            <a href="/catalog.php" style="text-decoration: none; color: inherit;">
                <img src="/image/gg_search.png" alt="Search" style="width: 24px; height: 24px;">
            </a>
            
            <div class="cart-icon">
                <a href="/cart.php" style="text-decoration: none; color: inherit;">
                    <img src="/image/jam_shopping-cart.png" alt="Cart" style="width: 24px; height: 24px;">
                    <?php if (isLoggedIn() && isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                        <span class="cart-badge"><?= $_SESSION['cart_count'] ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
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
                        <a href="/account/">Личный кабинет</a>
                        <a href="/account/balance.php">Баланс: <?= number_format($user_info['balance'] ?? 0, 2) ?> руб.</a>
                        <a href="/cart.php">Корзина</a>
                        <a href="/account/orders.php">Мои заказы</a>
                        <?php if (isAdmin()): ?>
                            <a href="/admin/">Админ панель</a>
                        <?php endif; ?>
                        <a href="/auth/logout.php">Выйти</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="auth-links">
                    <a href="/auth/login.php" class="login-btn">Войти</a>
                    <a href="/auth/register.php" class="register-btn">Регистрация</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
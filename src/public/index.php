<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

$path = ltrim($path, '/');

if ($path === '' || $path === '/') {
    require_once __DIR__ . '/../App/config/database.php';
    require_once __DIR__ . '/../App/auth/functions.php';
    
    $user_info = isLoggedIn() ? getUserInfo() : null;
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Главная страница</title>
        <link rel="stylesheet" href="/css/css.css">
        <link rel="stylesheet" href="/css/auth.css">
    </head>
    <body>
        <div class="wrapper">
            <?php require_once __DIR__ . '/../App/blocks/header.php'; ?>
            
            <main style="padding: 40px 5%; min-height: 60vh;">
                <h1>Добро пожаловать в магазин компьютерных комплектующих</h1>
                <p>Лучшие компоненты для вашего ПК по доступным ценам</p>
                
                <?php if (!isLoggedIn()): ?>
                    <div style="text-align: center; margin-top: 50px;">
                        <h2>Начните покупки прямо сейчас!</h2>
                        <div style="margin-top: 20px;">
                            <a href="/entrypoint/register.php" class="btn-primary" style="display: inline-block; width: auto; padding: 12px 30px; margin: 0 10px;">
                                Зарегистрироваться
                            </a>
                            <a href="/entrypoint/login.php" style="display: inline-block; padding: 12px 30px; margin: 0 10px; color: #0156FF; border: 1px solid #0156FF; border-radius: 4px; text-decoration: none;">
                                Войти
                            </a>
                        </div>
                    </div> 
                <?php else: ?>
                    <div style="text-align: center; margin-top: 50px;">
                        <h2>Добро пожаловать, <?= htmlspecialchars($user_info['first_name'] ?? 'Пользователь') ?>!</h2>
                        <p>Ваш баланс: <?= number_format($user_info['balance'] ?? 0, 2) ?> $.</p>
                        <div style="margin-top: 20px;">
                            <a href="/entrypoint/account.php" class="btn-primary" style="display: inline-block; width: auto; padding: 12px 30px;">
                                Перейти в личный кабинет
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
            
            <?php require_once __DIR__ . '/../App/blocks/footer.php'; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$full_path = __DIR__ . '/' . $path;

if (file_exists($full_path) && is_file($full_path)) {
    if (strpos($path, '.php') !== false) {
        require_once $full_path;
    } else {
        return false;
    }
} else {
    http_response_code(404);
    echo "<h1>404 - Страница не найдена</h1>";
    echo "<p>Запрошенная страница не существует: " . htmlspecialchars($path) . "</p>";
    echo "<p>Полный путь: " . htmlspecialchars($full_path) . "</p>";
}
?>
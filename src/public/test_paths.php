<?php
echo "<h1>Test All Paths</h1>";

$paths = [
    '/' => 'Главная страница',
    '/login.php' => 'Страница входа',
    '/register.php' => 'Страница регистрации',
    '/account.php' => 'Личный кабинет',
    '/admin.php' => 'Админ панель',
    '/logout.php' => 'Выход',
    '/css/css.css' => 'CSS файл',
    '/image/bottomheaderlogo.png' => 'Логотип'
];

echo "<h2>Проверка ссылок:</h2>";
foreach ($paths as $url => $description) {
    echo "<p><a href='$url'>$description</a> - $url</p>";
}

echo "<h2>Проверка файлов на диске:</h2>";
$files = [
    __DIR__ . '/index.php',
    __DIR__ . '/login.php',
    __DIR__ . '/register.php',
    __DIR__ . '/account.php',
    __DIR__ . '/admin.php',
    __DIR__ . '/logout.php',
    __DIR__ . '/css/css.css',
    __DIR__ . '/image/bottomheaderlogo.png'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ " . basename($file) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ " . basename($file) . " - $file</p>";
    }
}
?>
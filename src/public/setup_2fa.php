<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Подключаем библиотеку Google Authenticator
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo();
$gAuth = new GoogleAuthenticator();

$errors = [];
$success = '';

// Генерация секрета, если его нет в сессии (чтобы не менялся при перезагрузке страницы)
if (!isset($_SESSION['temp_2fa_secret'])) {
    $_SESSION['temp_2fa_secret'] = $gAuth->generateSecret();
}
$secret = $_SESSION['temp_2fa_secret'];

// ОТКЛЮЧЕНИЕ 2FA
if (isset($_POST['disable_2fa'])) {
    $pdo->prepare("UPDATE Users SET is_2fa_enabled = 0, two_factor_secret = NULL WHERE user_id = ?")->execute([$user_id]);
    unset($_SESSION['temp_2fa_secret']);
    header("Location: account.php");
    exit;
}

// ВКЛЮЧЕНИЕ 2FA (Подтверждение кода)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    
    // Проверяем код
    if ($gAuth->checkCode($secret, $code)) {
        // Код верный -> Сохраняем секрет в БД и включаем 2FA
        $stmt = $pdo->prepare("UPDATE Users SET two_factor_secret = ?, is_2fa_enabled = 1 WHERE user_id = ?");
        $stmt->execute([$secret, $user_id]);
        
        unset($_SESSION['temp_2fa_secret']); // Очищаем временный секрет
        $success = "Двухфакторная аутентификация успешно включена!";
        // Обновляем инфо пользователя, чтобы скрыть форму настройки
        $user_info['is_2fa_enabled'] = 1;
    } else {
        $errors[] = "Неверный код. Попробуйте еще раз.";
    }
}

// Генерируем ссылку для QR-кода
// Используем Google Charts API для генерации картинки (стандартный метод библиотеки)
$qrUrl = GoogleQrUrl::generate($user_info['email'], $secret, 'ComputerShop');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Настройка 2FA</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'account'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                <h1>Двухфакторная аутентификация (2FA)</h1>

                <?php if ($success): ?>
                    <div class="alert success"><?= $success ?></div>
                    <a href="account.php" class="btn-primary">Вернуться в профиль</a>
                <?php else: ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert error"><?= implode('<br>', $errors) ?></div>
                    <?php endif; ?>

                    <?php if ($user_info['is_2fa_enabled']): ?>
                        <div class="content-card">
                            <h3 style="color: green;">Защита активна</h3>
                            <p>При каждом входе в систему мы будем запрашивать код из вашего приложения.</p>
                            
                            <form method="post" onsubmit="return confirm('Вы уверены, что хотите отключить защиту?');">
                                <button type="submit" name="disable_2fa" class="btn-primary btn-danger">Отключить 2FA</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="content-card">
                            <h3>Шаг 1. Скачайте приложение</h3>
                            <p>Установите <strong>Google Authenticator</strong> (iOS/Android) или <strong>Яндекс.Ключ</strong>.</p>
                            
                            <h3>Шаг 2. Отсканируйте QR-код</h3>
                            <div style="text-align: center; margin: 20px 0;">
                                <img src="<?= $qrUrl ?>" alt="QR Code" style="border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                                <br>
                                <small style="color: #777;">Или введите ключ вручную: <strong><?= $secret ?></strong></small>
                            </div>

                            <h3>Шаг 3. Введите код из приложения</h3>
                            <form method="post" style="max-width: 300px; margin: 0 auto;">
                                <div class="form-group">
                                    <input type="text" name="code" placeholder="000 000" autocomplete="off" required style="text-align: center; font-size: 20px; letter-spacing: 3px;">
                                </div>
                                <button type="submit" class="btn-primary" style="width: 100%;">Подтвердить и включить</button>
                            </form>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
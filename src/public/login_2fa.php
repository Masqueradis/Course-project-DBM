<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

if (session_status() === PHP_SESSION_NONE) session_start();

// Если нет временной сессии - выкидываем на логин
if (!isset($_SESSION['2fa_pending_user_id'])) {
    header("Location: /login.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $errors[] = "Введите код";
    } else {
        $user_id = $_SESSION['2fa_pending_user_id'];
        
        // Получаем секрет из БД
        $stmt = $pdo->prepare("SELECT two_factor_secret, email, role, first_name FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $gAuth = new GoogleAuthenticator();
        
        if ($gAuth->checkCode($user['two_factor_secret'], $code)) {
            // КОД ВЕРНЫЙ! Логиним пользователя
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            
            // Удаляем временную метку
            unset($_SESSION['2fa_pending_user_id']);
            
            header("Location: /account.php");
            exit;
        } else {
            $errors[] = "Неверный код";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Проверка 2FA</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="auth-container">
        <div class="auth-form" style="text-align: center;">
            <div style="font-size: 40px; margin-bottom: 10px;">🔒</div>
            <h2>Проверка безопасности</h2>
            <p style="color: #666; margin-bottom: 20px;">Введите 6-значный код из приложения аутентификатора.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert error"><?= implode('<br>', $errors) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <input type="text" name="code" placeholder="000 000" maxlength="6" autofocus required autocomplete="one-time-code" style="text-align: center; font-size: 24px; letter-spacing: 5px; width: 200px; margin: 0 auto; display: block;">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Войти</button>
            </form>

            <div class="auth-links">
                <a href="/login.php">Отмена</a>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
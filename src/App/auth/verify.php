<?php
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = false;

// Если email не в сессии, редиректим на регистрацию
if (!isset($_SESSION['verification_email'])) {
    header("Location: register.php");
    exit;
}

$email = $_SESSION['verification_email'];

// Повторная отправка кода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("UPDATE Users SET verification_code = ?, verification_code_expires = ? WHERE email = ?");
    if ($stmt->execute([$verification_code, $verification_expires, $email])) {
        $database = new Database();
        if ($database->sendVerificationEmail($email, $verification_code)) {
            $success = "Новый код подтверждения отправлен на вашу почту!";
        } else {
            $errors[] = "Ошибка при отправке кода подтверждения";
        }
    } else {
        $errors[] = "Ошибка при генерации нового кода";
    }
}

// Проверка кода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $errors[] = "Введите код подтверждения";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, verification_code, verification_code_expires FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['verification_code'] === $code) {
                // Проверяем не истек ли срок действия кода
                if (strtotime($user['verification_code_expires']) > time()) {
                    // Активируем аккаунт
                    $stmt = $pdo->prepare("UPDATE Users SET is_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?");
                    if ($stmt->execute([$user['user_id']])) {
                        // Автоматически логиним пользователя
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_role'] = 'user';
                        
                        // Получаем имя пользователя для приветствия
                        $stmt = $pdo->prepare("SELECT first_name FROM Users WHERE user_id = ?");
                        $stmt->execute([$user['user_id']]);
                        $user_data = $stmt->fetch();
                        $_SESSION['first_name'] = $user_data['first_name'];
                        
                        unset($_SESSION['verification_email']);
                        header("Location: /account.php");
                        exit;
                    } else {
                        $errors[] = "Ошибка при активации аккаунта";
                    }
                } else {
                    $errors[] = "Срок действия кода истек. Запросите новый код.";
                }
            } else {
                $errors[] = "Неверный код подтверждения";
            }
        } else {
            $errors[] = "Пользователь не найден";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h2>Подтверждение email</h2>
            
            <div class="alert info">
                <p>На адрес <strong><?= htmlspecialchars($email) ?></strong> отправлен код подтверждения.</p>
                <p>Проверьте вашу почту и введите код ниже.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert success"><?= $success ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>Код подтверждения *</label>
                    <input type="text" name="code" maxlength="6" required placeholder="Введите 6-значный код">
                    <small>Код из письма, отправленного на вашу почту</small>
                </div>

                <button type="submit" class="btn-primary">Подтвердить email</button>
            </form>

            <form method="post" class="resend-form">
                <input type="hidden" name="resend" value="1">
                <p>Не получили код? <button type="submit" class="btn-link">Отправить новый код</button></p>
            </form>

            <div class="auth-links">
                <a href="register.php">Вернуться к регистрации</a>
            </div>
        </div>
    </div>
</body>
</html>
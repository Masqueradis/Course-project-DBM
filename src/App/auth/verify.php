<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = false;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT email, is_verified FROM Users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user && !$user['is_verified']) {
        $_SESSION['verification_email'] = $user['email'];
    }
}

if (!isset($_SESSION['verification_email'])) {
    if (isset($_GET['email'])) {
        $email = $_GET['email'];
        $stmt = $pdo->prepare("SELECT user_id, is_verified FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && !$user['is_verified']) {
            $_SESSION['verification_email'] = $email;
        } else {
            header("Location: /entrypoint/register.php");
            exit;
        }
    } else {
        header("Location: /entrypoint/register.php");
        exit;
    }
}

$email = $_SESSION['verification_email'];

$stmt = $pdo->prepare("SELECT is_verified FROM Users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && $user['is_verified']) {
    if (isset($_SESSION['user_id'])) {
        header("Location: /account.php");
    } else {
        header("Location: /entrypoint/login.php");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("UPDATE Users SET verification_code = ?, verification_code_expires = ? WHERE email = ?");
    if ($stmt->execute([$verification_code, $verification_expires, $email])) {
        require_once __DIR__ . '/../config/database.php';
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
                if (strtotime($user['verification_code_expires']) > time()) {
                    $stmt = $pdo->prepare("UPDATE Users SET is_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?");
                    if ($stmt->execute([$user['user_id']])) {
                        if (!isset($_SESSION['user_id'])) {
                            $_SESSION['user_id'] = $user['user_id'];
                            
                            $stmt = $pdo->prepare("SELECT email, first_name, role FROM Users WHERE user_id = ?");
                            $stmt->execute([$user['user_id']]);
                            $user_data = $stmt->fetch();
                            
                            $_SESSION['user_email'] = $user_data['email'];
                            $_SESSION['user_role'] = $user_data['role'];
                            $_SESSION['first_name'] = $user_data['first_name'];
                        }
                        
                        unset($_SESSION['verification_email']);
                        header("Location: account.php");
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

                <button type="submit" class="btn-primary" href="/entrypoint/account.php">Подтвердить email</button>
            </form>

            <form method="post" class="resend-form">
                <input type="hidden" name="resend" value="1">
                <p>Не получили код? <button type="submit" class="btn-link">Отправить новый код</button></p>
            </form>

            <div class="auth-links">
                <a href="/entrypoint/register.php">Вернуться к регистрации</a> |
                <a href="/entrypoint/login.php">Войти в аккаунт</a>
            </div>
        </div>
    </div>
</body>
</html>
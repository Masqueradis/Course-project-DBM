<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header("Location: /account.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    if (empty($email) || empty($password) || empty($first_name)) {
        $errors[] = "Заполните обязательные поля (*)";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email адрес";
    } elseif (strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают";
    } else {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким email уже существует";
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Генерируем код, но НЕ ОТПРАВЛЯЕМ его сейчас
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        try {
            $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, first_name, last_name, verification_code, verification_code_expires, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            
            if ($stmt->execute([$email, $password_hash, $first_name, $last_name, $verification_code, $verification_expires])) {
                $user_id = $pdo->lastInsertId();

                // ВАЖНО: Мы НЕ отправляем письмо здесь. Только авто-вход.
                
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'user';
                $_SESSION['first_name'] = $first_name;
                
                header("Location: /account.php");
                exit;

            } else {
                $errors[] = "Ошибка при регистрации";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка БД: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="auth-container">
        <div class="auth-form" style="max-width: 500px;">
            <h2>Регистрация</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Имя *</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Фамилия</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Пароль *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Повтор *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Зарегистрироваться</button>
            </form>

            <div class="auth-links">
                Уже есть аккаунт? <a href="/login.php">Войти</a>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
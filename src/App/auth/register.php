<?php
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);

    // Валидация
    if (empty($email) || empty($password) || empty($first_name)) {
        $errors[] = "Все обязательные поля должны быть заполнены";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email адрес";
    }

    if (strlen($password) < 6) {
        $errors[] = "Пароль должен содержать минимум 6 символов";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Пароли не совпадают";
    }

    // Проверка существующего email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким email уже существует";
        }
    }

    // Регистрация
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Генерируем код подтверждения
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, first_name, last_name, verification_code, verification_code_expires) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$email, $password_hash, $first_name, $last_name, $verification_code, $verification_expires])) {
            // Отправляем email с кодом подтверждения
            $database = new Database();
            if ($database->sendVerificationEmail($email, $verification_code)) {
                $success = true;
                $_SESSION['verification_email'] = $email;
                header("Location: verify.php");
                exit;
            } else {
                $errors[] = "Ошибка при отправке кода подтверждения. Попробуйте еще раз.";
            }
        } else {
            $errors[] = "Ошибка при регистрации";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-form">
            <h2>Регистрация</h2>
            
            <?php if ($success): ?>
                <div class="alert success">
                    Регистрация успешна! Перенаправляем на страницу подтверждения...
                </div>
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
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Имя *</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Фамилия</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Подтверждение пароля *</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn-primary">Зарегистрироваться</button>
            </form>

            <div class="auth-links">
                <a href="login.php">Уже есть аккаунт? Войти</a>
            </div>
        </div>
    </div>
</body>
</html>
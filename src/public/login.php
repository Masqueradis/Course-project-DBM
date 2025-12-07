<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (isLoggedIn()) {
    header("Location: /account.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Введите email и пароль";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, email, password_hash, first_name, role FROM Users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                
                header("Location: /account.php");
                exit;
            } else {
                $errors[] = "Неверный логин или пароль";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка сервера";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="auth-container">
        <div class="auth-form">
            <h2>Вход в систему</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">Войти</button>
            </form>

            <div class="auth-links">
                <a href="/register.php">Регистрация</a>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
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
        $errors[] = "Все поля обязательны для заполнения";
    }

    if (empty($errors)) {
        try {
            // ИЗМЕНЕНИЕ: Убрали "AND is_active = 1". Теперь пускаем всех.
            // Добавили выборку is_active, чтобы (при желании) показать уведомление
            $stmt = $pdo->prepare("SELECT user_id, email, password_hash, first_name, role, is_2fa_enabled, is_active FROM Users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (session_status() === PHP_SESSION_NONE) session_start();

                // 2FA Логика
                if ($user['is_2fa_enabled']) {
                    $_SESSION['2fa_pending_user_id'] = $user['user_id'];
                    header("Location: /login_2fa.php");
                    exit;
                } 
                
                // Обычный вход
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                
                // Можно записать статус в сессию, чтобы быстро проверять права на комментарии
                $_SESSION['is_active'] = $user['is_active']; 
                
                header("Location: /account.php");
                exit;
            } else {
                $errors[] = "Неверный email или пароль";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="auth-container">
        <div class="auth-form">
            <h2>Вход</h2>
            <?php if (!empty($errors)): ?>
                <div class="alert error"><?= implode('<br>', $errors) ?></div>
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
                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Войти</button>
            </form>
            <div class="auth-links">
                Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$errors = [];
$success = false;
$email = '';
$user_id = 0;

// 1. Идентификация пользователя
if (isLoggedIn()) {
    $user = getUserInfo();
    if ($user['is_verified']) {
        header("Location: /account.php");
        exit;
    }
    $email = $user['email'];
    $user_id = $user['user_id'];
} elseif (isset($_SESSION['verification_email'])) {
    $email = $_SESSION['verification_email'];
    // Нужно получить ID для обновлений
    $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
    $stmt->execute([$email]);
    $user_id = $stmt->fetchColumn();
} else {
    header("Location: /login.php");
    exit;
}

// 2. Функция отправки кода (Выделена для повторного использования)
function sendNewCode($pdo, $email) {
    global $errors; // Используем глобальную переменную ошибок
    $verification_code = sprintf("%06d", mt_rand(1, 999999));
    $verification_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("UPDATE Users SET verification_code = ?, verification_code_expires = ? WHERE email = ?");
    if ($stmt->execute([$verification_code, $verification_expires, $email])) {
        $database = new Database();
        if ($database->sendVerificationEmail($email, $verification_code)) {
            return true;
        } else {
            $errors[] = "Не удалось отправить письмо.";
        }
    }
    return false;
}

// 3. АВТО-ОТПРАВКА: Если перешли по ссылке из профиля (?send_new=1)
// Или нажали кнопку "Отправить повторно"
if ((isset($_GET['send_new']) && $_GET['send_new'] == 1) || (isset($_POST['resend']))) {
    if (sendNewCode($pdo, $email)) {
        $success = "Код подтверждения отправлен на почту!";
        // Убираем GET параметр из URL, чтобы при F5 не отправлялось снова
        if(isset($_GET['send_new'])) {
            echo "<script>window.history.replaceState({}, document.title, window.location.pathname);</script>";
        }
    }
}

// 4. Обработка введенного кода
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $errors[] = "Введите код";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, verification_code, verification_code_expires FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            if ($user_data['verification_code'] === $code) {
                if (strtotime($user_data['verification_code_expires']) > time()) {
                    $stmt = $pdo->prepare("UPDATE Users SET is_verified = 1, verification_code = NULL, verification_code_expires = NULL WHERE user_id = ?");
                    if ($stmt->execute([$user_data['user_id']])) {
                        
                        if (!isLoggedIn()) {
                            $_SESSION['user_id'] = $user_data['user_id'];
                            $_SESSION['user_email'] = $email;
                        }
                        
                        unset($_SESSION['verification_email']);
                        header("Location: /account.php");
                        exit;
                    }
                } else {
                    $errors[] = "Срок действия кода истек. Запросите новый.";
                }
            } else {
                $errors[] = "Неверный код.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подтверждение Email</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="auth-container">
        <div class="auth-form">
            <h2>Подтверждение</h2>
            
            <div class="alert info" style="font-size: 14px; text-align: center;">
                Ваш Email: <strong><?= htmlspecialchars($email) ?></strong>
                <br>
                <?php if (!$success): ?>
                    <small>Если код не пришел, нажмите кнопку ниже.</small>
                <?php endif; ?>
            </div>

            <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
            <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>

            <form method="post">
                <div class="form-group" style="text-align: center;">
                    <label>Код из письма</label>
                    <input type="text" name="code" maxlength="6" placeholder="123456" style="text-align: center; font-size: 24px; letter-spacing: 5px; width: 200px; margin: 0 auto; display: block;">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px;">Подтвердить</button>
            </form>

            <form method="post" class="resend-form">
                <input type="hidden" name="resend" value="1">
                <button type="submit" class="btn-link">Отправить код повторно</button>
            </form>

            <div class="auth-links">
                <a href="/account.php">Вернуться в кабинет</a>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
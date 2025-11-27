<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user = getUserInfo();
$errors = [];
$success = '';

// Обработка загрузки аватарки - ТОЛЬКО ДЛЯ ПОДТВЕРЖДЕННЫХ ПОЛЬЗОВАТЕЛЕЙ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    if (!$user['is_verified']) {
        $errors[] = "Для загрузки аватарки необходимо подтвердить email адрес";
    } elseif ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Проверяем тип файла
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Разрешены только файлы JPG, PNG и GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = "Файл слишком большой. Максимальный размер 5MB";
        } else {
            // Генерируем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $extension;
            $upload_dir = '/var/www/html/public/uploads/avatars/';
            $upload_path = $upload_dir . $filename;
            
            // Проверяем и создаем директорию если нужно
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Сохраняем путь в базу данных
                $avatar_url = '/uploads/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE Users SET avatar_url = ? WHERE user_id = ?");
                if ($stmt->execute([$avatar_url, $user['user_id']])) {
                    $success = "Аватарка успешно обновлена!";
                    $user = getUserInfo();
                } else {
                    $errors[] = "Ошибка при сохранении в базу данных";
                }
            } else {
                $errors[] = "Ошибка при загрузке файла";
            }
        }
    } elseif ($_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "Ошибка загрузки файла";
    }
}

// Пополнение баланса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = floatval($_POST['amount']);
    
    if ($amount > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?");
            if ($stmt->execute([$amount, $user['user_id']])) {
                $success = "Баланс успешно пополнен на " . $amount . " руб.";
                $user = getUserInfo();
            } else {
                $errors[] = "Ошибка при пополнении баланса";
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных";
        }
    } else {
        $errors[] = "Сумма должна быть положительной";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль пользователя</title>
    <link rel="stylesheet" href="/css/account.css">
</head>
<body>
    <?php require_once __DIR__ . '/../../App/blocks/header.php'; ?>

    <div class="account-container">
        <div class="sidebar">
            <h3>Личный кабинет</h3>
            <ul>
                <li><a href="/account.php" class="active">Профиль</a></li>
                <li><a href="balance.php">Баланс</a></li>
                <li><a href="orders.php">Мои заказы</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="/admin.php">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Профиль пользователя</h1>

            <!-- Блок статуса верификации -->
            <?php if (!$user['is_verified']): ?>
                <div class="alert warning">
                    <h3>⚠️ Email не подтвержден</h3>
                    <p>Для доступа ко всем функциям (включая загрузку аватарки) необходимо подтвердить ваш email адрес.</p>
                    <p>Проверьте вашу почту <?= htmlspecialchars($user['email']) ?> и введите код подтверждения.</p>
                    <a href="/verify.php" class="btn-primary">Подтвердить email</a>
                </div>
            <?php else: ?>
                <div class="alert success">
                    <h3>✅ Email подтвержден</h3>
                    <p>Ваш email адрес успешно подтвержден. Теперь вы можете загружать аватарку.</p>
                </div>
            <?php endif; ?>

            <div class="profile-section">
                <div class="avatar-section">
                    <h3>Аватарка</h3>
                    <div class="avatar-container">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Аватар" class="avatar-image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user['is_verified']): ?>
                        <form method="post" enctype="multipart/form-data" class="avatar-form">
                            <div class="form-group">
                                <label for="avatar">Выберите новую аватарку:</label>
                                <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/gif">
                                <small>Разрешены JPG, PNG, GIF до 5MB</small>
                            </div>
                            <button type="submit" class="btn-primary">Обновить аватарку</button>
                        </form>
                    <?php else: ?>
                        <div class="verification-required">
                            <p>Для загрузки аватарки необходимо подтвердить email</p>
                            <a href="/verify.php" class="btn-primary">Подтвердить email</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h3>Информация о профиле</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Имя:</label>
                            <span><?= htmlspecialchars($user['first_name'] ?? 'Не указано') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Фамилия:</label>
                            <span><?= htmlspecialchars($user['last_name'] ?? 'Не указана') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?= htmlspecialchars($user['email'] ?? 'Не указан') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Статус email:</label>
                            <span>
                                <?php if ($user['is_verified']): ?>
                                    <span style="color: green;">✅ Подтвержден</span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠️ Не подтвержден</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Роль:</label>
                            <span><?= $user['role'] === 'admin' ? 'Администратор' : 'Пользователь' ?></span>
                        </div>
                        <div class="info-item">
                            <label>Дата регистрации:</label>
                            <span><?= date('d.m.Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="balance-card">
                <h3>Баланс</h3>
                <div class="balance-amount"><?= number_format($user['balance'] ?? 0, 2) ?> руб.</div>
                
                <form method="post" class="balance-form">
                    <div class="form-group">
                        <label>Сумма пополнения:</label>
                        <input type="number" name="amount" min="1" step="0.01" required>
                    </div>
                    <button type="submit" class="btn-primary">Пополнить баланс</button>
                </form>

                <?php if (!empty($success)): ?>
                    <div class="alert success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <?php foreach ($errors as $error): ?>
                            <p><?= $error ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../App/blocks/footer.php'; ?>
</body>
</html>
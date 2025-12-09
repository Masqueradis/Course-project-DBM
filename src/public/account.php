<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    header("Location: /login.php");
    exit;
}

$user = getUserInfo();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    if (!$user['is_verified']) {
        $errors[] = "Для загрузки аватарки необходимо подтвердить email адрес";
    } elseif ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array(mime_content_type($file['tmp_name']), $allowed_types)) {
            $errors[] = "Разрешены только файлы JPG, PNG и GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) { 
            $errors[] = "Файл слишком большой. Максимальный размер 5MB";
        } else {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $extension;
            $upload_dir = __DIR__ . '/uploads/avatars/';
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $avatar_url = '/uploads/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE Users SET avatar_url = ? WHERE user_id = ?");
                $stmt->execute([$avatar_url, $user['user_id']]);
                $success = "Аватарка успешно обновлена!";
                $user = getUserInfo();
            } else {
                $errors[] = "Ошибка при загрузке файла";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap layout-container">
        <?php $current_page = 'account'; require_once __DIR__ . '/sidebar_user.php'; ?>

        <div class="content-area">
            <h1>Мой профиль</h1>
                <div style="margin-top: 20px; margin-bottom: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <h3>Безопасность</h3>
            <?php if ($user['is_verified']): ?>
                <div class="verification-badge verified">
                    <div class="verification-icon">✅</div>
                    <div>
                        <strong>Аккаунт подтвержден</strong><br>
                        <small>Вам доступны все функции магазина, включая отзывы и смену аватара.</small>
                    </div>
                </div>
            <?php else: ?>
                <div class="verification-badge unverified">
                    <div class="verification-icon">⚠️</div>
                    <div>
                        <strong>Email не подтвержден</strong><br>
                        <small>Пожалуйста, подтвердите вашу почту <?= htmlspecialchars($user['email']) ?> для доступа ко всем функциям.</small>
                        <div style="margin-top: 5px;">
                            <a href="/verify.php?send_new=1" style="text-decoration: underline; color: #856404;">Отправить код и подтвердить &rarr;</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?> <div class="alert success"><?= $success ?></div> <?php endif; ?>
            <?php if (!empty($errors)): ?> <div class="alert error"><?= implode('<br>', $errors) ?></div> <?php endif; ?>
            
                <?php if ($user['is_2fa_enabled']): ?>
                    <div class="alert success">
                        ✅ Двухфакторная аутентификация включена.
                    </div>
                    <a href="setup_2fa.php" class="btn-small btn-danger">Настроить / Отключить 2FA</a>
                <?php else: ?>
                    <div class="alert info">
                        ⚠️ Защитите свой аккаунт с помощью 2FA.
                    </div>
                    <a href="setup_2fa.php" class="btn-primary">Подключить 2FA</a>
                <?php endif; ?>
            </div>
            <div class="content-card">
                <div class="profile-grid">
                    
                    <div class="avatar-section">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Аватар" class="avatar-large">
                        <?php else: ?>
                            <div class="avatar-placeholder-large">
                                <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($user['is_verified']): ?>
                            <form method="post" enctype="multipart/form-data" style="margin-top: 15px;">
                                <div class="file-upload-wrapper">
                                    <label for="avatar-input" class="btn-file-custom">
                                        Изменить фото
                                    </label>
                                    <input type="file" name="avatar" id="avatar-input" accept="image/*" onchange="this.form.submit()">
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="profile-info">
                        <h3>Личные данные</h3>
                        
                        <div class="info-item">
                            <strong>Имя:</strong>
                            <span><?= htmlspecialchars($user['first_name'] ?? '-') ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Фамилия:</strong>
                            <span><?= htmlspecialchars($user['last_name'] ?? '-') ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong>
                            <span><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Телефон:</strong>
                            <span><?= htmlspecialchars($user['phone'] ?? 'Не указан') ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Баланс:</strong>
                            <span style="color: #0156FF; font-weight: bold;"><?= formatPrice($user['balance']) ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Дата регистрации:</strong>
                            <span><?= date('d.m.Y', strtotime($user['created_at'])) ?></span>
                        </div>

                        <div style="margin-top: 20px;">
                            <a href="balance.php" class="btn-primary">Управление балансом</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
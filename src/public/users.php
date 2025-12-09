<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

// --- ЛОГИКА БЛОКИРОВКИ / РАЗБЛОКИРОВКИ ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = intval($_GET['id']);
    $action = $_GET['action'];

    // 1. Проверяем, кого пытаются заблокировать
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE user_id = ?");
    $stmt->execute([$action_id]);
    $target_role = $stmt->fetchColumn();

    if ($target_role === 'admin') {
        // ЗАЩИТА: Нельзя менять статус админа
        $error = "Нельзя заблокировать администратора!";
    } else {
        if ($action === 'block') {
            $pdo->prepare("UPDATE Users SET is_active = 0 WHERE user_id = ?")->execute([$action_id]);
        } elseif ($action === 'unblock') {
            $pdo->prepare("UPDATE Users SET is_active = 1 WHERE user_id = ?")->execute([$action_id]);
        }
        header("Location: users.php");
        exit;
    }
}

$users = $pdo->query("SELECT * FROM Users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'users'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Пользователи</h1>
                
                <?php if (isset($error)): ?>
                    <div class="alert error"><?= $error ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Имя</th>
                                    <th>Email</th>
                                    <th>Роль</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?= $u['user_id'] ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php if($u['avatar_url']): ?>
                                                    <img src="<?= $u['avatar_url'] ?>" class="avatar-small">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder-small"><?= strtoupper(substr($u['first_name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span style="color: #0156FF; font-weight: bold;">Администратор</span>
                                            <?php else: ?>
                                                Покупатель
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['is_active']): ?>
                                                <span style="color: green;">Активен</span>
                                            <?php else: ?>
                                                <span style="color: red; font-weight: bold;">Заблокирован</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] !== 'admin' && $u['user_id'] !== $_SESSION['user_id']): ?>
                                                <?php if ($u['is_active']): ?>
                                                    <a href="?action=block&id=<?= $u['user_id'] ?>" class="btn-small btn-danger" onclick="return confirm('Запретить пользователю писать комментарии?')">Заблокировать</a>
                                                <?php else: ?>
                                                    <a href="?action=unblock&id=<?= $u['user_id'] ?>" class="btn-small btn-success">Разблокировать</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #ccc; font-size: 12px;">Недоступно</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
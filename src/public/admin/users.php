<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/functions.php';
redirectIfNotAdmin();

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = intval($_POST['user_id']);
        
        switch ($_POST['action']) {
            case 'toggle_active':
                $stmt = $pdo->prepare("UPDATE Users SET is_active = NOT is_active WHERE user_id = ?");
                $stmt->execute([$user_id]);
                break;
                
            case 'change_balance':
                $amount = floatval($_POST['amount']);
                $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?");
                if ($stmt->execute([$amount, $user_id])) {
                    $database = new Database();
                    $database->addTransaction($user_id, $amount, 'deposit', 'Изменение администратором');
                }
                break;
                
            case 'change_role':
                $role = $_POST['role'];
                $stmt = $pdo->prepare("UPDATE Users SET role = ? WHERE user_id = ?");
                $stmt->execute([$role, $user_id]);
                break;

            case 'block_user':
                $stmt = $pdo->prepare("UPDATE Users SET is_active = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                break;

            case 'unblock_user':
                $stmt = $pdo->prepare("UPDATE Users SET is_active = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                break;
        }
    }
}

$stmt = $pdo->query("SELECT user_id, email, first_name, last_name, balance, role, is_active, created_at FROM Users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/../blocks/header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <h3>Админ панель</h3>
            <ul>
                <li><a href="index.php">Дашборд</a></li>
                <li><a href="users.php" class="active">Пользователи</a></li>
                <li><a href="products.php">Товары</a></li>
                <li><a href="orders.php">Заказы</a></li>
                <li><a href="reviews.php">Отзывы</a></li>
                <li><a href="/account/index.php">Личный кабинет</a></li>
            </ul>
        </div>

        <div class="admin-main">
            <h1>Управление пользователями</h1>

            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Имя</th>
                            <th>Баланс</th>
                            <th>Роль</th>
                            <th>Статус</th>
                            <th>Дата регистрации</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= number_format($user['balance'], 2) ?> руб.</td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                        <select name="role" onchange="this.form.submit()">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <input type="hidden" name="action" value="change_role">
                                    </form>
                                </td>
                                <td>
                                    <span class="<?= $user['is_active'] ? 'success' : 'error' ?>" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?= $user['is_active'] ? 'Активен' : 'Заблокирован' ?>
                                    </span>
                                </td>
                                <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php if ($user['is_active']): ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" name="action" value="block_user" class="btn-small btn-danger" onclick="return confirm('Заблокировать пользователя?')">Блокировать</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" name="action" value="unblock_user" class="btn-small btn-success">Разблокировать</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="inline-form balance-form">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="number" name="amount" step="0.01" placeholder="Сумма" required style="width: 100px;">
                                            <button type="submit" name="action" value="change_balance" class="btn-small">Изм. баланс</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../blocks/footer.php'; ?>
</body>
</html>
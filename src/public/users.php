<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

// ... (Весь PHP код обработки действий оставляем без изменений) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = intval($_POST['user_id']);
        switch ($_POST['action']) {
            case 'change_balance':
                $amount = floatval($_POST['amount']);
                // Транзакция для безопасности
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?");
                    $stmt->execute([$amount, $user_id]);
                    
                    $stmt = $pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, 'deposit', 'Изменение администратором')");
                    $stmt->execute([$user_id, $amount]);
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
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
    <title>Пользователи</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'users'; require_once __DIR__ . '/sidebar_admin.php'; ?>

            <div class="admin-main">
                <h1>Управление пользователями</h1>

                <div class="data-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Пользователь</th>
                                <th>Баланс</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Дата</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['user_id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($user['email']) ?><br>
                                        <small><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></small>
                                    </td>
                                    <td><?= formatPrice($user['balance']) ?></td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="action" value="change_role">
                                            <select name="role" onchange="this.form.submit()" style="padding:5px;">
                                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <span style="color: <?= $user['is_active'] ? 'green' : 'red' ?>">
                                            <?= $user['is_active'] ? 'Активен' : 'Заблок.' ?>
                                        </span>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div style="display:flex; gap:5px; flex-direction:column;">
                                            <form method="post">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <?php if ($user['is_active']): ?>
                                                    <button type="submit" name="action" value="block_user" class="btn-small btn-danger" style="width:100%" onclick="return confirm('Блокировать?')">Блок</button>
                                                <?php else: ?>
                                                    <button type="submit" name="action" value="unblock_user" class="btn-small btn-success" style="width:100%">Разблок</button>
                                                <?php endif; ?>
                                            </form>
                                            <form method="post" style="display:flex; gap:5px;">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="change_balance">
                                                <input type="number" name="amount" step="1" placeholder="$" required style="width:60px; padding:5px;">
                                                <button type="submit" class="btn-small">OK</button>
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
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
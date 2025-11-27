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
    <title>Баланс</title>
    <link rel="stylesheet" href="/css/account.css">
</head>
<body>
    <?php require_once __DIR__ . '/../../App/blocks/header.php'; ?>

    <div class="account-container">
        <div class="sidebar">
            <h3>Личный кабинет</h3>
            <ul>
                <li><a href="/account.php">Профиль</a></li> <!-- ИСПРАВЛЕНО: ссылка на account.php -->
                <li><a href="balance.php" class="active">Баланс</a></li>
                <li><a href="orders.php">Мои заказы</a></li>
                <?php if (isAdmin()): ?>
                    <li><a href="/admin.php">Админ панель</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <h1>Управление балансом</h1>

            <div class="balance-card">
                <h3>Текущий баланс</h3>
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

            <div class="transactions">
                <h3>История транзакций</h3>
                <p>Здесь будет отображаться история ваших транзакций...</p>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../App/blocks/footer.php'; ?>
</body>
</html>
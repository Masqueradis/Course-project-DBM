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

// Пополнение баланса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = floatval($_POST['amount']);
    
    if ($amount > 0) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE user_id = ?");
            if ($stmt->execute([$amount, $user['user_id']])) {
                // Запись транзакции
                $stmt = $pdo->prepare("INSERT INTO BalanceTransactions (user_id, amount, type, description) VALUES (?, ?, 'deposit', 'Пополнение баланса')");
                $stmt->execute([$user['user_id'], $amount]);
                
                $pdo->commit();
                $success = "Баланс успешно пополнен на " . formatPrice($amount);
                $user = getUserInfo(); // Обновляем данные
            } else {
                $pdo->rollBack();
                $errors[] = "Ошибка при пополнении баланса";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    } else {
        $errors[] = "Сумма должна быть положительной";
    }
}

// Получение истории транзакций
$stmt = $pdo->prepare("SELECT * FROM BalanceTransactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['user_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Баланс</title>
    <link rel="stylesheet" href="/css/css.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <div class="container-wrap">
        <div class="layout-container">
            <?php $current_page = 'balance'; require_once __DIR__ . '/sidebar_user.php'; ?>

            <div class="content-area">
                <h1>Управление балансом</h1>

                <div class="content-card">
                    <h3>Текущий баланс</h3>
                    <div style="font-size: 2.5em; font-weight: bold; color: #0156FF; margin-bottom: 20px;">
                        <?= formatPrice($user['balance']) ?>
                    </div>
                    
                    <form method="post" style="max-width: 400px;">
                        <div class="form-group">
                            <label>Сумма пополнения:</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="amount" min="1" step="1" required placeholder="1000">
                                <button type="submit" class="btn-primary">Пополнить</button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($success)): ?> <div class="alert success" style="margin-top: 15px;"><?= $success ?></div> <?php endif; ?>
                    <?php if (!empty($errors)): ?> <div class="alert error" style="margin-top: 15px;"><?= implode('<br>', $errors) ?></div> <?php endif; ?>
                </div>

                <div class="content-card">
                    <h3>История операций</h3>
                    <?php if (empty($transactions)): ?>
                        <p style="color: #777;">История пуста</p>
                    <?php else: ?>
                        <div class="data-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Дата</th>
                                        <th>Тип</th>
                                        <th>Сумма</th>
                                        <th>Описание</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $t): ?>
                                        <tr>
                                            <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
                                            <td>
                                                <?php if($t['type'] == 'deposit'): ?>
                                                    <span style="color: green;">Пополнение</span>
                                                <?php elseif($t['type'] == 'purchase'): ?>
                                                    <span style="color: blue;">Покупка</span>
                                                <?php else: ?>
                                                    <?= ucfirst($t['type']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: bold; color: <?= $t['amount'] > 0 ? 'green' : 'red' ?>;">
                                                <?= $t['amount'] > 0 ? '+' : '' ?><?= formatPrice($t['amount']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($t['description']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/footer.php'; ?>
</body>
</html>
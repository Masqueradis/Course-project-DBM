<div class="sidebar">
    <h3>Личный кабинет</h3>
    <ul>
        <li>
            <a href="account.php" class="<?= ($current_page ?? '') === 'account' ? 'active' : '' ?>">Профиль</a>
        </li>
        <li>
            <a href="balance.php" class="<?= ($current_page ?? '') === 'balance' ? 'active' : '' ?>">Баланс</a>
        </li>
        <li>
            <a href="orders.php" class="<?= ($current_page ?? '') === 'orders' ? 'active' : '' ?>">Мои заказы</a>
        </li>
        <li>
            <a href="cart.php" class="<?= ($current_page ?? '') === 'cart' ? 'active' : '' ?>">Корзина</a>
        </li>
        <?php if (isAdmin()): ?>
            <li><a href="admin.php">Админ панель</a></li>
        <?php endif; ?>
    </ul>
</div>
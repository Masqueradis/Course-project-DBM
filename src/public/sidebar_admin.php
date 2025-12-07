<div class="admin-sidebar">
    <h3>Админ панель</h3>
    <ul>
        <li>
            <a href="admin.php" class="<?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>">Дашборд</a>
        </li>
        <li>
            <a href="users.php" class="<?= ($current_page ?? '') === 'users' ? 'active' : '' ?>">Пользователи</a>
        </li>
        <li>
            <a href="admin_products.php" class="<?= ($current_page ?? '') === 'products' ? 'active' : '' ?>">Товары</a>
        </li>
        <li>
            <a href="ordersAdmin.php" class="<?= ($current_page ?? '') === 'orders' ? 'active' : '' ?>">Заказы</a>
        </li>
        <li>
            <a href="reviews.php" class="<?= ($current_page ?? '') === 'reviews' ? 'active' : '' ?>">Отзывы</a>
        </li>
        <li>
            <a href="export.php" class="<?= ($current_page ?? '') === 'export' ? 'active' : '' ?>">Экспорт</a>
        </li>
        <li>
            <a href="import.php" class="<?= ($current_page ?? '') === 'import' ? 'active' : '' ?>">Импорт</a>
        </li>
        <li>
            <a href="account.php">Личный кабинет</a>
        </li>
    </ul>
</div>
<?php
// Базовые пути приложения
define('BASE_URL', '/');
define('ASSETS_URL', BASE_URL . 'css/');
define('IMAGES_URL', BASE_URL . 'image/');
define('UPLOADS_URL', BASE_URL . 'uploads/');

// Пути для перенаправлений
define('CATALOG_URL', BASE_URL . 'catalog.php');
define('PRODUCT_URL', BASE_URL . 'product.php');
define('CART_URL', BASE_URL . 'cart.php');
define('ORDERS_URL', BASE_URL . 'orders.php');
define('CHECKOUT_URL', BASE_URL . 'checkout.php');

// Entrypoint URLs
define('LOGIN_URL', BASE_URL . '/../auth/login.php');
define('REGISTER_URL', BASE_URL . '/../auth/register.php');
define('LOGOUT_URL', BASE_URL . '/../auth/logout.php');
define('ACCOUNT_URL', BASE_URL . '/../account.php');
define('BALANCE_URL', BASE_URL . '/../balance.php');
define('ADMIN_URL', BASE_URL . '/../admin.php');
define('VERIFY_URL', BASE_URL . '/../verify.php');
?>

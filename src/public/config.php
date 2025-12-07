<?php
// src/public/config.php

if (!defined('DECIMAL_PLACES')) {
    define('DECIMAL_PLACES', 2);
    define('DECIMAL_SEPARATOR', '.');
    define('THOUSANDS_SEPARATOR', ',');
        define('CURRENCY_POSITION', 'right'); // 'left' или 'right'
    define('SITE_NAME', 'Магазин компьютерных комплектующих');
    define('DEFAULT_TIMEZONE', 'Europe/Moscow');
}
?>

<?php
require_once __DIR__ . '/database.php';

session_destroy();
header("Location: /");
exit;
?>
<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
redirectIfNotAdmin();

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT image_url FROM Products WHERE product_id = ?");
    $stmt->execute([$id]);
    $img = $stmt->fetchColumn();
    
    if ($img && file_exists(__DIR__ . $img)) {
        unlink(__DIR__ . $img);
    }

    $stmt = $pdo->prepare("DELETE FROM Products WHERE product_id = ?");
    
    if ($stmt->execute([$id])) {
        header("Location: admin_products.php?msg=deleted");
    } else {
        die("Ошибка при удалении");
    }
} else {
    header("Location: admin_products.php");
}
<?php
echo "Testing database connection...\n";

try {
    $pdo = new PDO('mysql:host=mysql;dbname=app', 'user', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful!\n";
    
    // Проверяем существование таблиц
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Found tables: " . implode(', ', $tables) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}

// Проверяем расширения PHP
echo "\nChecking PHP extensions:\n";
$extensions = ['pdo', 'pdo_mysql', 'session'];
foreach ($extensions as $ext) {
    echo extension_loaded($ext) ? "✅ $ext\n" : "❌ $ext\n";
}
?>
<?php
require_once 'database.php';

echo "Testing database connection with root...\n";

try {
    // Проверяем подключение через наш класс
    $database = new Database();
    $pdo = $database->pdo;
    
    echo "✅ Database connection successful!\n";
    
    // Проверяем основные таблицы
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📊 Found tables: " . implode(', ', $tables) . "\n";
    
    // Проверяем таблицу Users
    if (in_array('Users', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Users");
        $result = $stmt->fetch();
        echo "👥 Users count: " . $result['count'] . "\n";
    }
    
    // Проверяем таблицу Products
    if (in_array('Products', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Products");
        $result = $stmt->fetch();
        echo "🛍️ Products count: " . $result['count'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
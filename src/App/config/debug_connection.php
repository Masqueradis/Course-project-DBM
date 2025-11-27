<?php
echo "=== Database Connection Debug ===\n\n";

$hosts = ['mysql', '127.0.0.1', 'localhost'];
$users = [
    ['user' => 'user', 'pass' => 'password'],
    ['user' => 'root', 'pass' => 'root']
];

foreach ($hosts as $host) {
    echo "Trying host: $host\n";
    
    foreach ($users as $user_info) {
        $user = $user_info['user'];
        $pass = $user_info['pass'];
        
        echo "  Testing user: {$user}\n";
        
        try {
            $pdo = new PDO("mysql:host={$host};dbname=app", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "  ✅ SUCCESS: Connected as {$user}@{$host}\n";
            
            // Проверяем таблицы
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "  📊 Tables: " . implode(', ', $tables) . "\n";
            
        } catch (PDOException $e) {
            echo "  ❌ FAILED: {$e->getMessage()}\n";
        }
        echo "\n";
    }
}

// Проверяем сетевую доступность
echo "=== Network Check ===\n";
$output = [];
exec("ping -c 2 mysql", $output);
foreach ($output as $line) {
    echo $line . "\n";
}
?>
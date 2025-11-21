<?php
echo "<h1>Zona Dental Care - System Diagnostic</h1>";
echo "<h3>Checking your setup...</h3>";

// Check if config.php exists
if (file_exists('config.php')) {
    echo "✅ config.php found<br>";
    
    // Test database connection
    try {
        include 'config.php';
        $test = $pdo->query("SELECT 1");
        echo "✅ Database connection successful<br>";
        
        // Check if inventory table exists
        $tables = $pdo->query("SHOW TABLES LIKE 'inventory'")->fetch();
        if ($tables) {
            echo "✅ Inventory table exists<br>";
            
            // Check inventory table structure
            $columns = $pdo->query("DESCRIBE inventory")->fetchAll();
            echo "✅ Inventory table has " . count($columns) . " columns<br>";
            
            // Check for required columns
            $required_columns = ['id', 'item_name', 'quantity', 'min_stock', 'price_usd'];
            $missing_columns = [];
            
            foreach ($required_columns as $col) {
                $exists = $pdo->query("SHOW COLUMNS FROM inventory LIKE '$col'")->fetch();
                if (!$exists) {
                    $missing_columns[] = $col;
                }
            }
            
            if (empty($missing_columns)) {
                echo "✅ All required columns exist<br>";
            } else {
                echo "❌ Missing columns: " . implode(', ', $missing_columns) . "<br>";
            }
            
        } else {
            echo "❌ Inventory table not found<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ config.php not found<br>";
}

// Check if stock-tracking.php exists
if (file_exists('stock-tracking.php')) {
    echo "✅ stock-tracking.php found<br>";
} else {
    echo "❌ stock-tracking.php not found<br>";
}

// Check if sidebar.php exists
if (file_exists('sidebar.php')) {
    echo "✅ sidebar.php found<br>";
} else {
    echo "❌ sidebar.php not found<br>";
}

echo "<hr>";
echo "<h3>Quick Fix Options:</h3>";
echo "<a href='fix_database.php' class='btn btn-primary'>Fix Database Tables</a> ";
echo "<a href='stock-tracking.php' class='btn btn-success'>Go to Stock Tracking</a>";

?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; }
</style>
<?php
session_start();
include 'config.php';

echo "<h1>Fixing Missing Database Columns</h1>";

try {
    // Check if min_stock column exists
    $check_min_stock = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'min_stock'")->fetch();
    if (!$check_min_stock) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN min_stock INT DEFAULT 5 AFTER quantity");
        echo "✅ Added min_stock column<br>";
    } else {
        echo "✅ min_stock column already exists<br>";
    }

    // Check if price_usd column exists
    $check_price_usd = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'price_usd'")->fetch();
    if (!$check_price_usd) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN price_usd DECIMAL(10,2) DEFAULT 0.00 AFTER min_stock");
        echo "✅ Added price_usd column<br>";
    } else {
        echo "✅ price_usd column already exists<br>";
    }

    // Check if supplier column exists
    $check_supplier = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'supplier'")->fetch();
    if (!$check_supplier) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN supplier VARCHAR(255) AFTER price_usd");
        echo "✅ Added supplier column<br>";
    } else {
        echo "✅ supplier column already exists<br>";
    }

    // Check if category column exists
    $check_category = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'category'")->fetch();
    if (!$check_category) {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN category VARCHAR(100) AFTER item_name");
        echo "✅ Added category column<br>";
    } else {
        echo "✅ category column already exists<br>";
    }

    echo "<hr><h3 style='color: green;'>✅ All missing columns have been added!</h3>";
    echo "<a href='stock-tracking.php' class='btn btn-success'>Go to Stock Tracking</a>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p>Try creating the inventory table first:</p>";
    echo "<a href='create_inventory_table.php' class='btn btn-warning'>Create Inventory Table</a>";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; color: white; }
.btn-success { background: #28a745; }
.btn-warning { background: #ffc107; color: black; }
</style>
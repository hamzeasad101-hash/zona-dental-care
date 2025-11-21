<?php
session_start();
include 'config.php';

echo "<h1>Creating Inventory Table</h1>";

try {
    // Drop table if exists (optional - remove if you want to keep existing data)
    // $pdo->exec("DROP TABLE IF EXISTS inventory");
    
    // Create inventory table with all required columns
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            quantity INT DEFAULT 0,
            min_stock INT DEFAULT 5,
            price_usd DECIMAL(10,2) DEFAULT 0.00,
            supplier VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "✅ Inventory table created successfully!<br>";
    
    // Add sample data
    $sample_items = [
        ['Dental Anesthetic', 'Medication', 50, 10, 25.00, 'Dental Supplies Co.'],
        ['Disposable Gloves', 'Consumables', 200, 50, 15.00, 'Medical Supplies Ltd.'],
        ['Dental Syringes', 'Equipment', 30, 5, 8.50, 'Dental Equipment Inc.'],
        ['Tooth Fillings', 'Materials', 100, 20, 45.00, 'Dental Materials Corp.'],
        ['Mouth Mirrors', 'Equipment', 25, 5, 12.00, 'Dental Tools Co.']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, min_stock, price_usd, supplier) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($sample_items as $item) {
        $stmt->execute($item);
    }
    
    echo "✅ Sample data added successfully!<br>";
    echo "<hr><h3 style='color: green;'>✅ Inventory table setup complete!</h3>";
    echo "<a href='stock-tracking.php' class='btn btn-success'>Go to Stock Tracking</a>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; background: #28a745; color: white; }
</style>
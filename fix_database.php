<?php
echo "<h1>Fixing Database Tables</h1>";

try {
    include 'config.php';
    
    // Create inventory table if it doesn't exist
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
    echo "✅ Inventory table created/verified<br>";
    
    // Create stock_log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            adjustment INT NOT NULL,
            notes TEXT,
            adjusted_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Stock log table created/verified<br>";
    
    // Add sample data to inventory if empty
    $count = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    if ($count == 0) {
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
        
        echo "✅ Added sample inventory data<br>";
    }
    
    echo "<hr><h3>✅ Database fix completed successfully!</h3>";
    echo "<a href='stock-tracking.php' class='btn btn-success'>Go to Stock Tracking</a> ";
    echo "<a href='check_setup.php' class='btn btn-info'>Check Setup Again</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; background: #28a745; color: white; }
</style>
[file name]: update_database_balance.php
[file content begin]
<?php
include 'config.php';

echo "<h1>Updating Database for Balance Tracking</h1>";

try {
    // Add balance_amount_usd column to invoices table
    $pdo->exec("ALTER TABLE invoices ADD COLUMN balance_amount_usd DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount_usd");
    echo "✅ Added balance_amount_usd column to invoices table<br>";
    
    // Create payment_transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT,
            amount_paid_usd DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(100),
            transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Created payment_transactions table<br>";
    
    // Update existing invoices to set balance_amount_usd
    $pdo->exec("UPDATE invoices SET balance_amount_usd = total_amount_usd WHERE status = 'pending'");
    $pdo->exec("UPDATE invoices SET balance_amount_usd = 0 WHERE status = 'paid'");
    echo "✅ Updated existing invoice balances<br>";
    
    echo "<hr><h3 style='color: green;'>✅ Database update complete!</h3>";
    echo "<a href='balance_management.php' class='btn btn-success'>Go to Balance Management</a>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 15px; margin: 5px; text-decoration: none; border-radius: 5px; background: #28a745; color: white; }
</style>
[file content end]
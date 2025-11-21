8<?php
include 'config.php';

echo "<h1>Complete Database Setup - Zona Dental Care</h1>";
echo "<h3>Creating all necessary tables...</h3>";

try {
    // 1. Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            full_name VARCHAR(100),
            role ENUM('admin', 'dentist', 'assistant') DEFAULT 'admin',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Users table created<br>";

    // 2. Create patients table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(20),
            date_of_birth DATE,
            address TEXT,
            medical_history TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Patients table created<br>";

    // 3. Create services table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price_usd DECIMAL(10,2) NOT NULL,
            duration INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Services table created<br>";

    // 4. Create appointments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            service_id INT,
            appointment_date DATETIME NOT NULL,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        )
    ");
    echo "✅ Appointments table created<br>";

    // 5. Create inventory table
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
    echo "✅ Inventory table created<br>";

    // 6. Create invoices table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            invoice_date DATE NOT NULL,
            total_amount_usd DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Invoices table created<br>";

    // 7. Create invoice_items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT,
            service_id INT,
            quantity INT DEFAULT 1,
            unit_price_usd DECIMAL(10,2) NOT NULL,
            total_price_usd DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        )
    ");
    echo "✅ Invoice items table created<br>";

    // 8. Create xrays table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS xrays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            xray_name VARCHAR(255) NOT NULL,
            xray_date DATE NOT NULL,
            description TEXT,
            file_path VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        )
    ");
    echo "✅ X-Rays table created<br>";

    // 9. Create stock_log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            adjustment INT NOT NULL COMMENT 'Positive for addition, negative for deduction',
            notes TEXT,
            adjusted_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Stock log table created<br>";

    // Add default admin user
    $check_user = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $check_user->execute();
    $existing_user = $check_user->fetch();

    if (!$existing_user) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', $hashed_password, 'admin@zonadental.com', 'System Administrator', 'admin']);
        echo "✅ Default admin user created (username: admin, password: admin123)<br>";
    }

    // Add sample services
    $services_count = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ($services_count == 0) {
        $sample_services = [
            ['Dental Checkup', 'Routine dental examination and cleaning', 50.00, 30],
            ['Filling', 'Tooth cavity filling', 80.00, 45],
            ['Root Canal', 'Root canal treatment', 300.00, 90],
            ['Tooth Extraction', 'Tooth removal procedure', 120.00, 60],
            ['Teeth Whitening', 'Professional teeth whitening', 200.00, 60]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO services (name, description, price_usd, duration) VALUES (?, ?, ?, ?)");
        foreach ($sample_services as $service) {
            $stmt->execute($service);
        }
        echo "✅ Sample services added<br>";
    }

    // Add sample inventory
    $inventory_count = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    if ($inventory_count == 0) {
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
        echo "✅ Sample inventory added<br>";
    }

    echo "<hr>";
    echo "<h2 style='color: green;'>🎉 Database Setup Complete!</h2>";
    echo "<p>All tables have been created successfully with sample data.</p>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Login Credentials:</h4>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "</div>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='login.php' style='background: #28a745; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Go to Login</a>";
    echo "<a href='dashboard.php' style='background: #007bff; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>Go to Dashboard</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p>Please check your database connection in config.php</p>";
}
?>
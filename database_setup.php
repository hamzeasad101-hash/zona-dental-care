<?php
include 'config.php';

try {
    // Create tables if they don't exist
    $tables = [
        "CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(20),
            date_of_birth DATE,
            address TEXT,
            medical_history TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price_usd DECIMAL(10,2) NOT NULL,
            duration INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            service_id INT,
            appointment_date DATETIME NOT NULL,
            status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            quantity INT DEFAULT 0,
            min_stock INT DEFAULT 5,
            price_usd DECIMAL(10,2),
            supplier VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            invoice_date DATE NOT NULL,
            total_amount_usd DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT,
            service_id INT,
            quantity INT DEFAULT 1,
            unit_price_usd DECIMAL(10,2) NOT NULL,
            total_price_usd DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS xrays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            xray_name VARCHAR(255) NOT NULL,
            xray_date DATE NOT NULL,
            description TEXT,
            file_path VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $table) {
        $pdo->exec($table);
    }
    
    // Insert sample services
    $sample_services = [
        ['Dental Checkup', 'Routine dental examination and cleaning', 50.00, 30],
        ['Filling', 'Tooth cavity filling', 80.00, 45],
        ['Root Canal', 'Root canal treatment', 300.00, 90],
        ['Tooth Extraction', 'Tooth removal procedure', 120.00, 60],
        ['Teeth Whitening', 'Professional teeth whitening', 200.00, 60]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO services (name, description, price_usd, duration) VALUES (?, ?, ?, ?)");
    foreach ($sample_services as $service) {
        $stmt->execute($service);
    }
    
    echo "Database setup completed successfully!";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
?>
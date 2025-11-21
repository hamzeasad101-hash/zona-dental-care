<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Exchange rate (1 USD to Shillings)
$exchange_rate = 10750;

// Get statistics with proper error handling
try {
    $patients_count = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $today_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status = 'scheduled'")->fetchColumn();
    $tomorrow_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status = 'scheduled'")->fetchColumn();
    $monthly_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount_usd), 0) FROM invoices WHERE MONTH(created_at) = MONTH(CURDATE()) AND status = 'paid'")->fetchColumn();
    $low_stock = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= min_stock")->fetchColumn();
    
    // Get upcoming appointments
    $upcoming_appointments = $pdo->query("
        SELECT a.*, p.full_name, p.phone, s.name as service_name 
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN services s ON a.service_id = s.id 
        WHERE DATE(a.appointment_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
        AND a.status = 'scheduled'
        ORDER BY a.appointment_date ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $patients_count = $today_appointments = $tomorrow_appointments = $monthly_revenue = $low_stock = 0;
    $upcoming_appointments = [];
    $error = "Error loading dashboard data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c5aa0;
            --secondary: #6c757d;
            --success: #198754;
            --info: #0dcaf0;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, #1e3a8a 100%);
            min-height: 100vh;
            border-radius: 20px 0 0 20px;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
        }
        
        .sidebar .logo {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar .logo h4 {
            color: white;
            font-weight: 700;
            margin: 0;
            text-align: center;
            font-size: 1.3rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            padding: 15px 20px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            margin: 5px 10px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: var(--info);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%);
            border-left-color: white;
            color: white;
            box-shadow: 0 5px 15px rgba(13, 202, 240, 0.3);
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.patients::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card.today::before { background: linear-gradient(90deg, #48dbfb, #0abde3); }
        .stat-card.tomorrow::before { background: linear-gradient(90deg, #1dd1a1, #10ac84); }
        .stat-card.revenue::before { background: linear-gradient(90deg, #f368e0, #ff9ff3); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .appointment-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 5px solid var(--info);
            transition: all 0.3s ease;
        }
        
        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .currency-display {
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row main-container">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="logo">
                    <h4><i class="fas fa-tooth me-2"></i>Zona Dental</h4>
                </div>
                <nav class="nav flex-column py-3">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="appointments.php">
                        <i class="fas fa-calendar-check me-2"></i>Appointments
                    </a>
                    <a class="nav-link" href="patients.php">
                        <i class="fas fa-users me-2"></i>Patients
                    </a>
                    <a class="nav-link" href="xray.php">
                        <i class="fas fa-x-ray me-2"></i>X-Ray Management
                    </a>
                    <a class="nav-link" href="point-of-sale.php">
                        <i class="fas fa-cash-register me-2"></i>Point of Sale
                    </a>
                    <a class="nav-link" href="invoice_generation.php">
                        <i class="fas fa-file-invoice me-2"></i>Invoices
                    </a>
                    <a class="nav-link" href="stock-tracking.php">
                        <i class="fas fa-boxes me-2"></i>Stock Tracking
                    </a>
                    <a class="nav-link" href="inventory.php">
                        <i class="fas fa-warehouse me-2"></i>Inventory
                    </a>
                    <a class="nav-link" href="services.php">
                        <i class="fas fa-teeth me-2"></i>Services
                    </a>
                    <a class="nav-link" href="invoice_items.php">
                        <i class="fas fa-receipt me-2"></i>Invoice Items
                    </a>
                    <a class="nav-link" href="financial-reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Financial Reports
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1" style="color: var(--primary);">Clinic Overview</h1>
                        <p class="text-muted mb-0">Welcome back! Here's what's happening today.</p>
                    </div>
                    <div class="quick-actions">
                        <a href="appointments.php?action=add" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>New Appointment
                        </a>
                        <a href="patients.php?action=add" class="btn btn-success">
                            <i class="fas fa-user-plus me-1"></i>New Patient
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 patients">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-users fa-3x" style="color: #1535c5ff;"></i>
                                </div>
                                <h6 class="card-title text-muted">Total Patients</h6>
                                <h2 class="mb-0" style="color: #667eea; font-size: 2.5rem;"><?php echo $patients_count; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 today">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-calendar-day fa-3x" style="color: #48dbfb;"></i>
                                </div>
                                <h6 class="card-title text-muted">Today's Appointments</h6>
                                <h2 class="mb-0" style="color: #5448fbff; font-size: 2.5rem;"><?php echo $today_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 tomorrow">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-calendar-alt fa-3x" style="color: #1dd1a1;"></i>
                                </div>
                                <h6 class="card-title text-muted">Tomorrow's Appointments</h6>
                                <h2 class="mb-0" style="color: #1dd1a1; font-size: 2.5rem;"><?php echo $tomorrow_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 revenue">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-dollar-sign fa-3x" style="color: #09e72eff;"></i>
                                </div>
                                <h6 class="card-title text-muted">Monthly Revenue</h6>
                                <h2 class="mb-1" style="color: #0bc734ff; font-size: 1.8rem;">$<?php echo number_format($monthly_revenue, 2); ?></h2>
                                <small class="currency-display">Sh <?php echo number_format($monthly_revenue * $exchange_rate, 0); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Appointments -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Upcoming Appointments (Today & Tomorrow)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcoming_appointments) > 0): ?>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="appointment-card p-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-clock text-primary me-2"></i>
                                                    <h6 class="mb-0 text-muted">
                                                        <?php echo date('M j, Y @ g:i A', strtotime($appointment['appointment_date'])); ?>
                                                    </h6>
                                                </div>
                                                <h4 class="text-primary mb-2"><?php echo htmlspecialchars($appointment['full_name']); ?></h4>
                                                <p class="text-dark mb-2">
                                                    <strong><?php echo htmlspecialchars($appointment['service_name'] ?? 'General Checkup'); ?></strong>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?>
                                                </small>
                                            </div>
                                            <div class="ms-3">
                                                <a href="patients.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Patient
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Upcoming Appointments</h5>
                                        <p class="text-muted">No appointments scheduled for today or tomorrow.</p>
                                        <a href="appointments.php?action=add" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i>Schedule Appointment
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #1dd1a1 0%, #10ac84 100%); color: white;">
                                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="appointments.php?action=add" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add New Appointment
                                    </a>
                                    <a href="patients.php?action=add" class="btn btn-success">
                                        <i class="fas fa-user-plus me-2"></i>Add New Patient
                                    </a>
                                    <a href="patients.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i>View All Patients
                                    </a>
                                    <a href="point-of-sale.php" class="btn btn-outline-success">
                                        <i class="fas fa-cash-register me-2"></i>Point of Sale
                                    </a>
                                </div>
                                
                                <div class="mt-4 p-3 rounded" style="background: linear-gradient(135deg, #a8e6cf 0%, #dcedc1 100%);">
                                    <h6 class="mb-3"><i class="fas fa-life-ring me-2"></i>Feedback & Help</h6>
                                    <a href="#" class="btn btn-outline-dark btn-sm">
                                        <i class="fas fa-question-circle me-1"></i>Help & Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
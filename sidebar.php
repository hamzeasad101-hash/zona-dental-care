<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-md-3 col-lg-2 px-0 sidebar">
    <div class="logo text-center py-3">
        <h4 class="text-white"><i class="fas fa-tooth me-2"></i>Zona Dental</h4>
    </div>
    <nav class="nav flex-column py-3">
        <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </a>

        <a class="nav-link <?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>" href="appointments.php">
            <i class="fas fa-calendar-check me-2"></i>Appointments
        </a>

        <a class="nav-link <?php echo $current_page == 'patients.php' ? 'active' : ''; ?>" href="patients.php">
            <i class="fas fa-users me-2"></i>Patients
        </a>

        <a class="nav-link <?php echo $current_page == 'xray.php' ? 'active' : ''; ?>" href="xray.php">
            <i class="fas fa-x-ray me-2"></i>X-Ray Management
        </a>

        <a class="nav-link <?php echo $current_page == 'point-of-sale.php' ? 'active' : ''; ?>" href="point-of-sale.php">
            <i class="fas fa-cash-register me-2"></i>Point of Sale
        </a>

        <a class="nav-link <?php echo $current_page == 'invoice_generation.php' ? 'active' : ''; ?>" href="invoice_generation.php">
            <i class="fas fa-file-invoice me-2"></i>Invoices
        </a>

        <!-- BALANCE MANAGEMENT LINK - ADDED -->
        <a class="nav-link <?php echo $current_page == 'balance_management.php' ? 'active' : ''; ?>" href="balance_management.php">
            <i class="fas fa-money-bill-wave me-2"></i>Balance Management
        </a>

        <a class="nav-link <?php echo $current_page == 'stock-tracking.php' ? 'active' : ''; ?>" href="stock-tracking.php">
            <i class="fas fa-boxes me-2"></i>Stock Tracking
        </a>

        <a class="nav-link <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
            <i class="fas fa-warehouse me-2"></i>Inventory
        </a>

        <a class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>" href="services.php">
            <i class="fas fa-teeth me-2"></i>Services
        </a>

        <a class="nav-link <?php echo $current_page == 'invoice_items.php' ? 'active' : ''; ?>" href="invoice_items.php">
            <i class="fas fa-receipt me-2"></i>Invoice Items
        </a>

        <a class="nav-link <?php echo $current_page == 'financial-reports.php' ? 'active' : ''; ?>" href="financial-reports.php">
            <i class="fas fa-chart-bar me-2"></i>Financial Reports
        </a>

        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </nav>
</div>

<style>
.sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    min-height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.logo {
    background: rgba(255,255,255,0.1);
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar .nav-link {
    color: rgba(255,255,255,0.9);
    padding: 12px 20px;
    margin: 2px 10px;
    border-radius: 8px;
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
    font-weight: 500;
    text-decoration: none;
    display: block;
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    border-left-color: #3498db;
    color: white;
}

.sidebar .nav-link.active {
    background: #3498db;
    border-left-color: white;
    color: white;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
}

.sidebar .nav-link i {
    width: 20px;
    text-align: center;
}
</style>

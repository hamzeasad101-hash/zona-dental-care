<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$exchange_rate = 10750;

// Get date range from filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'revenue';

try {
    // Financial Summary
    $revenue_data = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount_usd), 0) as total_revenue,
            COUNT(*) as invoice_count,
            AVG(total_amount_usd) as avg_invoice
        FROM invoices 
        WHERE invoice_date BETWEEN ? AND ? AND status = 'paid'
    ");
    $revenue_data->execute([$date_from, $date_to]);
    $financial_summary = $revenue_data->fetch();

    // Monthly revenue trend (last 6 months)
    $revenue_trend = $pdo->query("
        SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            SUM(total_amount_usd) as revenue,
            COUNT(*) as invoice_count
        FROM invoices 
        WHERE status = 'paid' AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ")->fetchAll();

    // Top services by revenue
    $top_services = $pdo->prepare("
        SELECT 
            s.name,
            COUNT(ii.id) as service_count,
            SUM(ii.total_price_usd) as total_revenue
        FROM invoice_items ii
        JOIN services s ON ii.service_id = s.id
        JOIN invoices i ON ii.invoice_id = i.id
        WHERE i.invoice_date BETWEEN ? AND ? AND i.status = 'paid'
        GROUP BY s.id, s.name
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $top_services->execute([$date_from, $date_to]);
    $top_services_data = $top_services->fetchAll();

    // Payment method breakdown
    $payment_methods = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(total_amount_usd) as total_amount
        FROM invoices 
        WHERE invoice_date BETWEEN ? AND ? AND status = 'paid'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $payment_methods->execute([$date_from, $date_to]);
    $payment_data = $payment_methods->fetchAll();

    // Recent invoices
    $recent_invoices = $pdo->prepare("
        SELECT i.*, p.full_name 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        WHERE i.invoice_date BETWEEN ? AND ?
        ORDER BY i.created_at DESC 
        LIMIT 10
    ");
    $recent_invoices->execute([$date_from, $date_to]);
    $invoices = $recent_invoices->fetchAll();

} catch (PDOException $e) {
    $financial_summary = ['total_revenue' => 0, 'invoice_count' => 0, 'avg_invoice' => 0];
    $revenue_trend = [];
    $top_services_data = [];
    $payment_data = [];
    $invoices = [];
    $error = "Error loading financial data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .financial-card {
            background: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .financial-card:hover {
            transform: translateY(-2px);
        }
        .revenue-chart {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-chart-bar me-2"></i>Financial Reports & Analytics</h1>
                    <div>
                        <a href="invoice_generation.php" class="btn btn-success me-2">
                            <i class="fas fa-file-invoice me-1"></i>View Invoices
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print Report
                        </button>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-calendar me-2"></i>Report Period</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-select" name="report_type">
                                    <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                                    <option value="services" <?php echo $report_type === 'services' ? 'selected' : ''; ?>>Services Analysis</option>
                                    <option value="payments" <?php echo $report_type === 'payments' ? 'selected' : ''; ?>>Payment Analysis</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i>Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Financial Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                </div>
                                <h6 class="card-title text-muted">Total Revenue</h6>
                                <div class="stat-number text-success">
                                    $<?php echo number_format($financial_summary['total_revenue'], 2); ?>
                                </div>
                                <small class="text-muted">
                                    Sh <?php echo number_format($financial_summary['total_revenue'] * $exchange_rate, 0); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-receipt fa-2x text-primary"></i>
                                </div>
                                <h6 class="card-title text-muted">Total Invoices</h6>
                                <div class="stat-number text-primary">
                                    <?php echo $financial_summary['invoice_count']; ?>
                                </div>
                                <small class="text-muted">Paid invoices</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-calculator fa-2x text-info"></i>
                                </div>
                                <h6 class="card-title text-muted">Average Invoice</h6>
                                <div class="stat-number text-info">
                                    $<?php echo number_format($financial_summary['avg_invoice'], 2); ?>
                                </div>
                                <small class="text-muted">Per transaction</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-calendar-alt fa-2x text-warning"></i>
                                </div>
                                <h6 class="card-title text-muted">Period</h6>
                                <div class="stat-number text-warning" style="font-size: 1.2rem;">
                                    <?php echo date('M j, Y', strtotime($date_from)); ?> -<br>
                                    <?php echo date('M j, Y', strtotime($date_to)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BALANCE STATISTICS - ADDED -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                                </div>
                                <h6 class="card-title text-muted">Outstanding Balance</h6>
                                <?php
                                $outstanding_balance = $pdo->query("SELECT COALESCE(SUM(balance_amount_usd), 0) FROM invoices WHERE status = 'pending' AND invoice_date BETWEEN '$date_from' AND '$date_to'")->fetchColumn();
                                ?>
                                <div class="stat-number text-warning">
                                    $<?php echo number_format($outstanding_balance, 2); ?>
                                </div>
                                <small class="text-muted">
                                    Sh <?php echo number_format($outstanding_balance * $exchange_rate, 0); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-file-invoice-dollar fa-2x text-danger"></i>
                                </div>
                                <h6 class="card-title text-muted">Unpaid Invoices</h6>
                                <?php
                                $unpaid_invoices = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending' AND invoice_date BETWEEN '$date_from' AND '$date_to'")->fetchColumn();
                                ?>
                                <div class="stat-number text-danger">
                                    <?php echo $unpaid_invoices; ?>
                                </div>
                                <small class="text-muted">Pending payment</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-percentage fa-2x text-info"></i>
                                </div>
                                <h6 class="card-title text-muted">Collection Rate</h6>
                                <?php
                                $total_invoiced = $pdo->query("SELECT COALESCE(SUM(total_amount_usd), 0) FROM invoices WHERE invoice_date BETWEEN '$date_from' AND '$date_to'")->fetchColumn();
                                $total_collected = $pdo->query("SELECT COALESCE(SUM(total_amount_usd - COALESCE(balance_amount_usd, 0)), 0) FROM invoices WHERE invoice_date BETWEEN '$date_from' AND '$date_to'")->fetchColumn();
                                $collection_rate = $total_invoiced > 0 ? ($total_collected / $total_invoiced) * 100 : 0;
                                ?>
                                <div class="stat-number text-info">
                                    <?php echo number_format($collection_rate, 1); ?>%
                                </div>
                                <small class="text-muted">Amount collected</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card financial-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-hand-holding-usd fa-2x text-success"></i>
                                </div>
                                <h6 class="card-title text-muted">Amount Collected</h6>
                                <div class="stat-number text-success">
                                    $<?php echo number_format($total_collected, 2); ?>
                                </div>
                                <small class="text-muted">
                                    Sh <?php echo number_format($total_collected * $exchange_rate, 0); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Analytics -->
                <div class="row">
                    <!-- Revenue Trend Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Revenue Trend (Last 6 Months)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Methods</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Services -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-star me-2"></i>Top Services by Revenue</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Service</th>
                                                <th>Count</th>
                                                <th>Revenue (USD)</th>
                                                <th>Revenue (Shillings)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_services_data as $service): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                                <td><?php echo $service['service_count']; ?></td>
                                                <td>$<?php echo number_format($service['total_revenue'], 2); ?></td>
                                                <td>Sh <?php echo number_format($service['total_revenue'] * $exchange_rate, 0); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Invoices -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Patient</th>
                                                <th>Amount</th>
                                                <th>Balance</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($invoices as $invoice): 
                                                $balance = $invoice['balance_amount_usd'] ?? $invoice['total_amount_usd'];
                                            ?>
                                            <tr>
                                                <td>#<?php echo $invoice['id']; ?></td>
                                                <td><?php echo htmlspecialchars($invoice['full_name']); ?></td>
                                                <td>
                                                    <small class="text-primary">$<?php echo number_format($invoice['total_amount_usd'], 2); ?></small><br>
                                                    <small class="text-muted">Sh <?php echo number_format($invoice['total_amount_usd'] * $exchange_rate, 0); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($balance > 0): ?>
                                                    <small class="text-danger fw-bold">$<?php echo number_format($balance, 2); ?></small>
                                                    <?php else: ?>
                                                    <small class="text-success">$0.00</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($invoice['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Financial Summary -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Detailed Financial Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h6>Total Revenue</h6>
                                        <h4 class="text-success">$<?php echo number_format($financial_summary['total_revenue'], 2); ?></h4>
                                        <small class="text-muted">Sh <?php echo number_format($financial_summary['total_revenue'] * $exchange_rate, 0); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Total Transactions</h6>
                                        <h4 class="text-primary"><?php echo $financial_summary['invoice_count']; ?></h4>
                                        <small class="text-muted">Paid invoices</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Average Transaction</h6>
                                        <h4 class="text-info">$<?php echo number_format($financial_summary['avg_invoice'], 2); ?></h4>
                                        <small class="text-muted">Per invoice</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h6>Outstanding Balance</h6>
                                        <h4 class="text-warning">$<?php echo number_format($outstanding_balance, 2); ?></h4>
                                        <small class="text-muted">To be collected</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M Y', strtotime($item['month'] . '-01')) . "'"; }, array_reverse($revenue_trend))); ?>],
                datasets: [{
                    label: 'Monthly Revenue (USD)',
                    data: [<?php echo implode(',', array_map(function($item) { return $item['revenue']; }, array_reverse($revenue_trend))); ?>],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Methods Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return "'" . ucfirst($item['payment_method']) . "'"; }, $payment_data)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(function($item) { return $item['total_amount']; }, $payment_data)); ?>],
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

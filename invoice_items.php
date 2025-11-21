<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$exchange_rate = 10750;

// Get invoice items with related data
try {
    $invoice_items = $pdo->query("
        SELECT 
            ii.*,
            i.invoice_date,
            i.total_amount_usd as invoice_total,
            i.status as invoice_status,
            p.full_name as patient_name,
            s.name as service_name,
            s.price_usd as service_price
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        JOIN patients p ON i.patient_id = p.id
        LEFT JOIN services s ON ii.service_id = s.id
        ORDER BY ii.id DESC
        LIMIT 100
    ")->fetchAll();
    
    // Summary statistics
    $summary_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(ii.total_price_usd) as total_revenue,
            AVG(ii.total_price_usd) as avg_item_value,
            COUNT(DISTINCT ii.invoice_id) as unique_invoices
        FROM invoice_items ii
        JOIN invoices i ON ii.invoice_id = i.id
        WHERE i.status = 'paid'
    ");
    $summary = $summary_stmt->fetch();
    
} catch (PDOException $e) {
    $invoice_items = [];
    $summary = ['total_items' => 0, 'total_revenue' => 0, 'avg_item_value' => 0, 'unique_invoices' => 0];
    $error = "Error loading invoice items: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Items - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .invoice-item-card {
            border-left: 4px solid #0d6efd;
            background: #f8f9fa;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .summary-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-receipt me-2"></i>Invoice Items Management</h1>
                    <a href="financial-reports.php" class="btn btn-info">
                        <i class="fas fa-chart-bar me-1"></i>View Reports
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-cube fa-2x text-primary"></i>
                                </div>
                                <h6 class="card-title text-muted">Total Items</h6>
                                <h3 class="text-primary"><?php echo $summary['total_items']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                </div>
                                <h6 class="card-title text-muted">Total Revenue</h6>
                                <h4 class="text-success">$<?php echo number_format($summary['total_revenue'], 2); ?></h4>
                                <small class="text-muted">Sh <?php echo number_format($summary['total_revenue'] * $exchange_rate, 0); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-calculator fa-2x text-info"></i>
                                </div>
                                <h6 class="card-title text-muted">Avg Item Value</h6>
                                <h4 class="text-info">$<?php echo number_format($summary['avg_item_value'], 2); ?></h4>
                                <small class="text-muted">Per item</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card summary-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-file-invoice fa-2x text-warning"></i>
                                </div>
                                <h6 class="card-title text-muted">Invoices</h6>
                                <h3 class="text-warning"><?php echo $summary['unique_invoices']; ?></h3>
                                <small class="text-muted">With items</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Items Table -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Invoice Items Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invoice_items)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Invoice Items Found</h5>
                                <p class="text-muted">Invoice items will appear here when you create invoices through the Point of Sale.</p>
                                <a href="point-of-sale.php" class="btn btn-primary">
                                    <i class="fas fa-cash-register me-1"></i>Go to Point of Sale
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Item ID</th>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Quantity</th>
                                            <th>Unit Price (USD)</th>
                                            <th>Unit Price (Shillings)</th>
                                            <th>Total (USD)</th>
                                            <th>Total (Shillings)</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice_items as $item): ?>
                                        <tr>
                                            <td><small>#<?php echo $item['id']; ?></small></td>
                                            <td>
                                                <a href="invoice_generation.php?id=<?php echo $item['invoice_id']; ?>" class="text-decoration-none">
                                                    <strong>INV<?php echo $item['invoice_id']; ?></strong>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['service_name'] ?? 'Custom Service'); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['unit_price_usd'], 2); ?></td>
                                            <td>Sh <?php echo number_format($item['unit_price_usd'] * $exchange_rate, 0); ?></td>
                                            <td>
                                                <strong class="text-primary">$<?php echo number_format($item['total_price_usd'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <strong class="text-success">Sh <?php echo number_format($item['total_price_usd'] * $exchange_rate, 0); ?></strong>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($item['invoice_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $item['invoice_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($item['invoice_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Summary Footer -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Items Summary</h6>
                                            <?php
                                            $total_items = count($invoice_items);
                                            $total_value = array_sum(array_column($invoice_items, 'total_price_usd'));
                                            $avg_value = $total_items > 0 ? $total_value / $total_items : 0;
                                            ?>
                                            <p class="mb-1">Total Items: <strong><?php echo $total_items; ?></strong></p>
                                            <p class="mb-1">Total Value: <strong>$<?php echo number_format($total_value, 2); ?></strong></p>
                                            <p class="mb-0">Average Value: <strong>$<?php echo number_format($avg_value, 2); ?></strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6>Currency Conversion</h6>
                                            <p class="mb-1">Exchange Rate: <strong>1 USD = <?php echo number_format($exchange_rate); ?> Shillings</strong></p>
                                            <p class="mb-1">Total Value (Shillings): <strong>Sh <?php echo number_format($total_value * $exchange_rate, 0); ?></strong></p>
                                            <p class="mb-0">Last Updated: <strong><?php echo date('F j, Y'); ?></strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
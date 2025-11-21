<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$exchange_rate = 10750;

// Get specific invoice if ID provided
$invoice_id = $_GET['id'] ?? '';
$invoice = null;
$invoice_items = [];

if ($invoice_id) {
    try {
        // Get invoice details
        $stmt = $pdo->prepare("
            SELECT i.*, p.full_name, p.phone, p.email, p.address 
            FROM invoices i 
            JOIN patients p ON i.patient_id = p.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();

        if ($invoice) {
            // Get invoice items
            $items_stmt = $pdo->prepare("
                SELECT ii.*, s.name as service_name 
                FROM invoice_items ii 
                LEFT JOIN services s ON ii.service_id = s.id 
                WHERE ii.invoice_id = ?
            ");
            $items_stmt->execute([$invoice_id]);
            $invoice_items = $items_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error loading invoice: " . $e->getMessage();
    }
}

// Get all invoices for listing
try {
    $invoices = $pdo->query("
        SELECT i.*, p.full_name 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        ORDER BY i.created_at DESC 
        LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) {
    $invoices = [];
    $error = "Error loading invoices: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Generation - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }
        .currency-column {
            text-align: right;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .invoice-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            body {
                background: white !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-file-invoice me-2"></i>Invoice Management</h1>
                    <div class="no-print">
                        <a href="point-of-sale.php" class="btn btn-success me-2">
                            <i class="fas fa-cash-register me-1"></i>Point of Sale
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i>Print Invoice
                        </button>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($invoice): ?>
                <!-- Invoice Display -->
                <div class="invoice-container">
                    <!-- Invoice Header -->
                    <div class="invoice-header p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h2 class="mb-1">ZONA DENTAL CARE</h2>
                                <p class="mb-0">Professional Dental Services</p>
                                <small>123 Dental Street, City, Country</small><br>
                                <small>Phone: +255 123 456 789 | Email: info@zonadental.com</small>
                            </div>
                            <div class="col-md-6 text-end">
                                <h3 class="mb-2">INVOICE</h3>
                                <p class="mb-1"><strong>Invoice #:</strong> INV<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                                <p class="mb-0"><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo strtoupper($invoice['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Information -->
                    <div class="p-4 border-bottom">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Bill To:</h5>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                                <?php if ($invoice['phone']): ?>
                                <p class="mb-1">Phone: <?php echo htmlspecialchars($invoice['phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($invoice['email']): ?>
                                <p class="mb-1">Email: <?php echo htmlspecialchars($invoice['email']); ?></p>
                                <?php endif; ?>
                                <?php if ($invoice['address']): ?>
                                <p class="mb-0">Address: <?php echo htmlspecialchars($invoice['address']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <h5>Payment Information:</h5>
                                <p class="mb-1"><strong>Method:</strong> <?php echo ucfirst($invoice['payment_method']); ?></p>
                                <p class="mb-1"><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                                <?php if ($invoice['notes']): ?>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($invoice['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Items -->
                    <div class="p-4">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="currency-column">Unit Price (USD)</th>
                                        <th class="currency-column">Unit Price (Shillings)</th>
                                        <th class="currency-column">Total (USD)</th>
                                        <th class="currency-column">Total (Shillings)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $subtotal = 0;
                                    foreach ($invoice_items as $index => $item): 
                                        $subtotal += $item['total_price_usd'];
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($item['service_name']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="currency-column">$<?php echo number_format($item['unit_price_usd'], 2); ?></td>
                                        <td class="currency-column">Sh <?php echo number_format($item['unit_price_usd'] * $exchange_rate, 0); ?></td>
                                        <td class="currency-column">$<?php echo number_format($item['total_price_usd'], 2); ?></td>
                                        <td class="currency-column">Sh <?php echo number_format($item['total_price_usd'] * $exchange_rate, 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="currency-column"><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                                        <td class="currency-column"><strong>Sh <?php echo number_format($subtotal * $exchange_rate, 0); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Tax (0%):</strong></td>
                                        <td class="currency-column"><strong>$0.00</strong></td>
                                        <td class="currency-column"><strong>Sh 0</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                                        <td class="currency-column"><strong>$<?php echo number_format($invoice['total_amount_usd'], 2); ?></strong></td>
                                        <td class="currency-column"><strong>Sh <?php echo number_format($invoice['total_amount_usd'] * $exchange_rate, 0); ?></strong></td>
                                    </tr>
                                    <!-- BALANCE INFORMATION - ADDED -->
                                    <?php 
                                    $balance_amount = $invoice['balance_amount_usd'] ?? $invoice['total_amount_usd'];
                                    $amount_paid = $invoice['total_amount_usd'] - $balance_amount;
                                    ?>
                                    <?php if ($balance_amount > 0): ?>
                                    <tr class="table-warning">
                                        <td colspan="5" class="text-end"><strong>Amount Paid:</strong></td>
                                        <td class="currency-column"><strong>$<?php echo number_format($amount_paid, 2); ?></strong></td>
                                        <td class="currency-column"><strong>Sh <?php echo number_format($amount_paid * $exchange_rate, 0); ?></strong></td>
                                    </tr>
                                    <tr class="table-danger">
                                        <td colspan="5" class="text-end"><strong>Balance Due:</strong></td>
                                        <td class="currency-column"><strong>$<?php echo number_format($balance_amount, 2); ?></strong></td>
                                        <td class="currency-column"><strong>Sh <?php echo number_format($balance_amount * $exchange_rate, 0); ?></strong></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr class="table-success">
                                        <td colspan="5" class="text-end"><strong>Amount Paid:</strong></td>
                                        <td class="currency-column"><strong>$<?php echo number_format($invoice['total_amount_usd'], 2); ?></strong></td>
                                        <td class="currency-column"><strong>Sh <?php echo number_format($invoice['total_amount_usd'] * $exchange_rate, 0); ?></strong></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="5" class="text-end"><strong>Balance Due:</strong></td>
                                        <td class="currency-column"><strong>$0.00</strong></td>
                                        <td class="currency-column"><strong>Sh 0</strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Invoice Footer -->
                    <div class="p-4 border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Payment Instructions:</h6>
                                <p class="mb-1">• Payment due upon receipt</p>
                                <p class="mb-1">• Make checks payable to Zona Dental Care</p>
                                <p class="mb-0">• For bank transfers, contact our office</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Thank you for your business!</h6>
                                <p class="mb-0">For questions about this invoice, contact:</p>
                                <p class="mb-0">Zona Dental Care | +255 123 456 789 | info@zonadental.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Invoices List -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Invoices</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invoices)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Invoices Found</h5>
                                <p class="text-muted">Create your first invoice using the Point of Sale system.</p>
                                <a href="point-of-sale.php" class="btn btn-primary">
                                    <i class="fas fa-cash-register me-1"></i>Go to Point of Sale
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Date</th>
                                            <th>Amount (USD)</th>
                                            <th>Amount (Shillings)</th>
                                            <th>Balance (USD)</th>
                                            <th>Status</th>
                                            <th>Payment Method</th>
                                            <th class="no-print">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): 
                                            $balance = $inv['balance_amount_usd'] ?? $inv['total_amount_usd'];
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo $inv['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($inv['full_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($inv['invoice_date'])); ?></td>
                                            <td>$<?php echo number_format($inv['total_amount_usd'], 2); ?></td>
                                            <td>Sh <?php echo number_format($inv['total_amount_usd'] * $exchange_rate, 0); ?></td>
                                            <td>
                                                <?php if ($balance > 0): ?>
                                                <span class="text-danger fw-bold">$<?php echo number_format($balance, 2); ?></span>
                                                <?php else: ?>
                                                <span class="text-success">$0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $inv['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($inv['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($inv['payment_method']); ?></td>
                                            <td class="no-print">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="invoice_generation.php?id=<?php echo $inv['id']; ?>" class="btn btn-primary" title="View Invoice">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="invoice_generation.php?id=<?php echo $inv['id']; ?>&print=true" class="btn btn-success" title="Print Invoice">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

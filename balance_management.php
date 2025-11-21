<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$exchange_rate = 10750;

// Handle balance payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_balance'])) {
    $invoice_id = $_POST['invoice_id'];
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    
    try {
        // Get current invoice
        $stmt = $pdo->prepare("SELECT balance_amount_usd, total_amount_usd FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            $_SESSION['error'] = "Invoice not found";
            header("Location: balance_management.php");
            exit();
        }
        
        if ($amount_paid > $invoice['balance_amount_usd']) {
            $_SESSION['error'] = "Amount paid cannot exceed balance due";
            header("Location: balance_management.php");
            exit();
        }
        
        // Update invoice balance
        $new_balance = $invoice['balance_amount_usd'] - $amount_paid;
        $status = $new_balance > 0 ? 'pending' : 'paid';
        
        $stmt = $pdo->prepare("UPDATE invoices SET balance_amount_usd = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_balance, $status, $invoice_id]);
        
        // Record payment transaction
        $stmt = $pdo->prepare("INSERT INTO payment_transactions (invoice_id, amount_paid_usd, payment_method, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $amount_paid, $payment_method, $notes]);
        
        $_SESSION['success'] = "Payment of $" . number_format($amount_paid, 2) . " recorded successfully. Remaining balance: $" . number_format($new_balance, 2);
        header("Location: balance_management.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error recording payment: " . $e->getMessage();
    }
}

// Get invoices with balances
try {
    $invoices_with_balance = $pdo->query("
        SELECT i.*, p.full_name, p.phone 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        WHERE i.balance_amount_usd > 0 
        ORDER BY i.invoice_date DESC
    ")->fetchAll();
    
    // Get payment history
    $payment_history = $pdo->query("
        SELECT pt.*, i.id as invoice_id, p.full_name 
        FROM payment_transactions pt 
        JOIN invoices i ON pt.invoice_id = i.id 
        JOIN patients p ON i.patient_id = p.id 
        ORDER BY pt.transaction_date DESC 
        LIMIT 20
    ")->fetchAll();
    
} catch (PDOException $e) {
    $invoices_with_balance = [];
    $payment_history = [];
    $error = "Error loading balance data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Management - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-money-bill-wave me-2"></i>Balance Management</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Outstanding Balances -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Outstanding Balances</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invoices_with_balance)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-success">No Outstanding Balances</h5>
                                <p class="text-muted">All invoices are fully paid.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Total Amount</th>
                                            <th>Balance Due</th>
                                            <th>Balance (Shillings)</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices_with_balance as $invoice): ?>
                                        <tr>
                                            <td><strong>#<?php echo $invoice['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($invoice['full_name']); ?></td>
                                            <td>$<?php echo number_format($invoice['total_amount_usd'], 2); ?></td>
                                            <td class="text-danger fw-bold">$<?php echo number_format($invoice['balance_amount_usd'], 2); ?></td>
                                            <td class="text-danger">Sh <?php echo number_format($invoice['balance_amount_usd'] * $exchange_rate, 0); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?></td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal" 
                                                        data-invoice-id="<?php echo $invoice['id']; ?>"
                                                        data-patient-name="<?php echo htmlspecialchars($invoice['full_name']); ?>"
                                                        data-balance-due="<?php echo $invoice['balance_amount_usd']; ?>">
                                                    <i class="fas fa-money-bill me-1"></i>Pay Balance
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Payment History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payment_history)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Payment History</h5>
                                <p class="text-muted">Payment transactions will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice #</th>
                                            <th>Patient</th>
                                            <th>Amount Paid</th>
                                            <th>Amount (Shillings)</th>
                                            <th>Method</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payment_history as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y H:i', strtotime($payment['transaction_date'])); ?></td>
                                            <td>#<?php echo $payment['invoice_id']; ?></td>
                                            <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                            <td class="text-success">$<?php echo number_format($payment['amount_paid_usd'], 2); ?></td>
                                            <td class="text-success">Sh <?php echo number_format($payment['amount_paid_usd'] * $exchange_rate, 0); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($payment['payment_method']); ?></span></td>
                                            <td><?php echo htmlspecialchars($payment['notes'] ?? 'No notes'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Record Balance Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="invoice_id" id="modalInvoiceId">
                        <input type="hidden" name="pay_balance" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Patient</label>
                            <input type="text" class="form-control" id="modalPatientName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Balance Due</label>
                            <input type="text" class="form-control" id="modalBalanceDue" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amount to Pay (USD) *</label>
                            <input type="number" class="form-control" name="amount_paid" step="0.01" min="0.01" required id="modalAmountPaid">
                            <small class="text-muted">Enter the amount being paid now</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="mobile">Mobile Money</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any notes about this payment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Payment Modal
        const paymentModal = document.getElementById('paymentModal');
        paymentModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const invoiceId = button.getAttribute('data-invoice-id');
            const patientName = button.getAttribute('data-patient-name');
            const balanceDue = button.getAttribute('data-balance-due');
            
            document.getElementById('modalInvoiceId').value = invoiceId;
            document.getElementById('modalPatientName').value = patientName;
            document.getElementById('modalBalanceDue').value = '$' + parseFloat(balanceDue).toFixed(2);
            document.getElementById('modalAmountPaid').max = balanceDue;
            document.getElementById('modalAmountPaid').value = balanceDue;
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

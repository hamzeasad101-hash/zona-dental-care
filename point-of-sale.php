<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$exchange_rate = 10750;

// Get data for POS
try {
    $patients = $pdo->query("SELECT id, full_name, phone FROM patients ORDER BY full_name")->fetchAll();
    $services = $pdo->query("SELECT id, name, price_usd, duration FROM services ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $patients = [];
    $services = [];
    $error = "Error loading data: " . $e->getMessage();
}

// Handle invoice creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $service_ids = $_POST['service_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $payment_method = $_POST['payment_method'];
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $notes = trim($_POST['notes']);
    
    try {
        // Calculate total
        $total_amount = 0;
        $invoice_items = [];
        
        foreach ($service_ids as $index => $service_id) {
            $quantity = $quantities[$index] ?? 1;
            $stmt = $pdo->prepare("SELECT price_usd FROM services WHERE id = ?");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch();
            
            if ($service) {
                $unit_price = $service['price_usd'];
                $item_total = $unit_price * $quantity;
                $total_amount += $item_total;
                
                $invoice_items[] = [
                    'service_id' => $service_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_price' => $item_total
                ];
            }
        }
        
        // Calculate balance
        $balance_amount = $total_amount - $amount_paid;
        $status = $balance_amount > 0 ? 'pending' : 'paid';
        
        // Create invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (patient_id, invoice_date, total_amount_usd, balance_amount_usd, status, payment_method, notes) 
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $total_amount, $balance_amount, $status, $payment_method, $notes]);
        $invoice_id = $pdo->lastInsertId();
        
        // Create invoice items
        $stmt = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, service_id, quantity, unit_price_usd, total_price_usd) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($invoice_items as $item) {
            $stmt->execute([$invoice_id, $item['service_id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
        }
        
        // Record payment transaction if amount was paid
        if ($amount_paid > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO payment_transactions (invoice_id, amount_paid_usd, payment_method, notes) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$invoice_id, $amount_paid, $payment_method, $notes]);
        }
        
        $_SESSION['success'] = "Invoice #$invoice_id created successfully. Total: $" . number_format($total_amount, 2) . 
                              ($balance_amount > 0 ? " | Balance: $" . number_format($balance_amount, 2) : " | Fully Paid");
        header("Location: invoice_generation.php?id=$invoice_id");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error creating invoice: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .service-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .service-card:hover {
            transform: translateY(-2px);
            border-color: #0d6efd;
        }
        .service-card.selected {
            border-color: #198754;
            background-color: #f8fff9;
        }
        .cart-item {
            border-left: 4px solid #0d6efd;
            background: #f8f9fa;
        }
        .pos-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .balance-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body class="pos-container">
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0"><i class="fas fa-cash-register me-2"></i>Point of Sale System</h1>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <form method="POST" id="posForm">
                            <div class="row">
                                <!-- Patient Selection -->
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Select Patient</h5>
                                        </div>
                                        <div class="card-body">
                                            <select class="form-select" name="patient_id" required id="patientSelect">
                                                <option value="">Choose a patient...</option>
                                                <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>">
                                                    <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['phone'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="mt-3 text-center">
                                                <a href="patient_form.php" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-plus me-1"></i>New Patient
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-info text-white">
                                            <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Payment Method *</label>
                                                <select class="form-select" name="payment_method" required>
                                                    <option value="">Select method...</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="card">Credit/Debit Card</option>
                                                    <option value="insurance">Insurance</option>
                                                    <option value="mobile">Mobile Money</option>
                                                    <option value="bank">Bank Transfer</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Amount Paid (USD)</label>
                                                <input type="number" class="form-control" name="amount_paid" id="amountPaid" 
                                                       step="0.01" min="0" value="0">
                                                <small class="text-muted">Enter 0 for unpaid invoices</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cart Summary -->
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header bg-warning text-dark">
                                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="cartSummary">
                                                <p class="text-muted">No services selected</p>
                                            </div>
                                            <div id="balanceInfo" class="mt-2 p-2 rounded balance-warning d-none">
                                                <small><strong>Balance will be calculated after payment</strong></small>
                                            </div>
                                            <hr>
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-success" id="checkoutBtn" disabled>
                                                    <i class="fas fa-credit-card me-1"></i>Process Payment
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Services Selection -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">
                                            <h5 class="mb-0"><i class="fas fa-teeth me-2"></i>Select Services</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row" id="servicesGrid">
                                                <?php foreach ($services as $service): ?>
                                                <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                                                    <div class="card service-card h-100" data-service-id="<?php echo $service['id']; ?>" data-price="<?php echo $service['price_usd']; ?>">
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($service['name']); ?></h6>
                                                            <p class="card-text">
                                                                <small class="text-muted"><?php echo $service['duration']; ?> min</small>
                                                            </p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="text-primary fw-bold">$<?php echo number_format($service['price_usd'], 2); ?></span>
                                                                <button type="button" class="btn btn-sm btn-outline-primary add-to-cart">
                                                                    <i class="fas fa-plus"></i> Add
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart Items (Hidden until items added) -->
                            <div class="row mt-4 d-none" id="cartSection">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-dark text-white">
                                            <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Selected Services</h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="cartItems"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Additional Notes</h5>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions or notes..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const exchangeRate = <?php echo $exchange_rate; ?>;
        let cart = [];
        
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function() {
                const card = this.closest('.service-card');
                const serviceId = card.dataset.serviceId;
                const serviceName = card.querySelector('.card-title').textContent;
                const servicePrice = parseFloat(card.dataset.price);
                
                addToCart(serviceId, serviceName, servicePrice);
            });
        });
        
        function addToCart(serviceId, serviceName, servicePrice) {
            // Check if service already in cart
            const existingItem = cart.find(item => item.serviceId === serviceId);
            
            if (existingItem) {
                existingItem.quantity++;
                existingItem.total = existingItem.quantity * servicePrice;
            } else {
                cart.push({
                    serviceId: serviceId,
                    serviceName: serviceName,
                    price: servicePrice,
                    quantity: 1,
                    total: servicePrice
                });
            }
            
            updateCartDisplay();
        }
        
        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
            const cartSection = document.getElementById('cartSection');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const balanceInfo = document.getElementById('balanceInfo');
            const amountPaidInput = document.getElementById('amountPaid');
            
            let itemsHTML = '';
            let totalUSD = 0;
            let itemCount = 0;
            
            // Generate hidden inputs for form submission
            itemsHTML += '<input type="hidden" name="service_id[]" value=""><input type="hidden" name="quantity[]" value="">';
            
            cart.forEach((item, index) => {
                totalUSD += item.total;
                itemCount += item.quantity;
                
                itemsHTML += `
                    <div class="cart-item p-3 mb-2 rounded">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>${item.serviceName}</strong>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">$${item.price.toFixed(2)}</small>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control form-control-sm" name="quantity[]" value="${item.quantity}" min="1" data-index="${index}">
                            </div>
                            <div class="col-md-2">
                                <strong>$${item.total.toFixed(2)}</strong>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="service_id[]" value="${item.serviceId}">
                    </div>
                `;
            });
            
            cartItems.innerHTML = itemsHTML;
            
            const totalShillings = totalUSD * exchangeRate;
            
            cartSummary.innerHTML = `
                <div class="text-center">
                    <h4 class="text-primary">$${totalUSD.toFixed(2)}</h4>
                    <h6 class="text-success">Sh ${totalShillings.toLocaleString()}</h6>
                    <small class="text-muted">${itemCount} item(s) in cart</small>
                </div>
            `;
            
            // Update amount paid max value
            amountPaidInput.max = totalUSD;
            
            // Show/hide cart section and balance info
            if (cart.length > 0) {
                cartSection.classList.remove('d-none');
                checkoutBtn.disabled = false;
                balanceInfo.classList.remove('d-none');
            } else {
                cartSection.classList.add('d-none');
                checkoutBtn.disabled = true;
                balanceInfo.classList.add('d-none');
            }
            
            // Add event listeners for quantity changes and removals
            document.querySelectorAll('input[name="quantity[]"]').forEach(input => {
                input.addEventListener('change', function() {
                    const index = this.dataset.index;
                    const newQuantity = parseInt(this.value);
                    
                    if (newQuantity > 0) {
                        cart[index].quantity = newQuantity;
                        cart[index].total = cart[index].price * newQuantity;
                        updateCartDisplay();
                    }
                });
            });
            
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const index = this.dataset.index;
                    cart.splice(index, 1);
                    updateCartDisplay();
                });
            });
        }
        
        // Form validation
        document.getElementById('posForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Please add at least one service to the cart.');
                return false;
            }
            
            const totalUSD = cart.reduce((sum, item) => sum + item.total, 0);
            const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
            
            if (amountPaid > totalUSD) {
                e.preventDefault();
                alert('Amount paid cannot be greater than total amount.');
                return false;
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
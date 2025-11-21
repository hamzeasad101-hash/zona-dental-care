<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$item = null;
$is_edit = false;
$exchange_rate = 10750;

// Check if editing existing item
if (isset($_GET['id'])) {
    $is_edit = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $_SESSION['error'] = "Inventory item not found";
            header("Location: inventory.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading inventory item: " . $e->getMessage();
        header("Location: inventory.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $quantity = intval($_POST['quantity']);
    $min_stock = intval($_POST['min_stock']);
    $price_usd = floatval($_POST['price_usd']);
    $supplier = trim($_POST['supplier']);
    
    try {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE inventory SET 
                item_name = ?, category = ?, quantity = ?, min_stock = ?, price_usd = ?, supplier = ?
                WHERE id = ?
            ");
            $stmt->execute([$item_name, $category, $quantity, $min_stock, $price_usd, $supplier, $item['id']]);
            $_SESSION['success'] = "Inventory item updated successfully";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO inventory (item_name, category, quantity, min_stock, price_usd, supplier) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$item_name, $category, $quantity, $min_stock, $price_usd, $supplier]);
            $_SESSION['success'] = "Inventory item added successfully";
        }
        
        header("Location: inventory.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error saving inventory item: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Inventory Item' : 'Add Inventory Item'; ?> - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary">
                        <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $is_edit ? 'Edit Inventory Item' : 'Add Inventory Item'; ?>
                    </h1>
                    <a href="inventory.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Inventory
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Item Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Item Name *</label>
                                            <input type="text" class="form-control" name="item_name" 
                                                   value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>" 
                                                   required placeholder="e.g., Dental Anesthetic, Gloves, Syringes">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Category</label>
                                            <input type="text" class="form-control" name="category" 
                                                   value="<?php echo htmlspecialchars($item['category'] ?? ''); ?>" 
                                                   placeholder="e.g., Medication, Equipment, Consumables">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Quantity *</label>
                                            <input type="number" class="form-control" name="quantity" 
                                                   value="<?php echo htmlspecialchars($item['quantity'] ?? 0); ?>" 
                                                   min="0" required>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Minimum Stock *</label>
                                            <input type="number" class="form-control" name="min_stock" 
                                                   value="<?php echo htmlspecialchars($item['min_stock'] ?? 5); ?>" 
                                                   min="1" required>
                                            <small class="text-muted">Alert when stock reaches this level</small>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Price (USD) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" name="price_usd" 
                                                       value="<?php echo htmlspecialchars($item['price_usd'] ?? ''); ?>" 
                                                       step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Supplier</label>
                                            <input type="text" class="form-control" name="supplier" 
                                                   value="<?php echo htmlspecialchars($item['supplier'] ?? ''); ?>" 
                                                   placeholder="Supplier company name">
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                <?php echo $is_edit ? 'Update Item' : 'Save Item'; ?>
                                            </button>
                                            <a href="inventory.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Price & Stock Info</h5>
                            </div>
                            <div class="card-body">
                                <div id="pricePreview">
                                    <p class="text-muted">Enter price to see conversion</p>
                                </div>
                                <hr>
                                <div class="stock-info">
                                    <h6>Stock Status:</h6>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <span id="stockStatus">Good Stock</span>
                                    </div>
                                    <small class="text-muted">
                                        System will alert when stock reaches minimum level.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow mt-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Inventory Tips</h5>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle text-success me-1"></i> Set realistic minimum stock levels<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Regular inventory audits<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Track supplier performance<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Monitor expiration dates
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const exchangeRate = <?php echo $exchange_rate; ?>;
        const priceInput = document.querySelector('input[name="price_usd"]');
        const quantityInput = document.querySelector('input[name="quantity"]');
        const minStockInput = document.querySelector('input[name="min_stock"]');
        const pricePreview = document.getElementById('pricePreview');
        const stockStatus = document.getElementById('stockStatus');
        const stockAlert = document.querySelector('.stock-info .alert');
        
        function updatePricePreview() {
            const priceUSD = parseFloat(priceInput.value) || 0;
            const priceShillings = priceUSD * exchangeRate;
            
            if (priceUSD > 0) {
                pricePreview.innerHTML = `
                    <h6>Unit Price:</h6>
                    <h4 class="text-primary">$${priceUSD.toFixed(2)}</h4>
                    <p class="text-muted mb-1">Sh ${priceShillings.toLocaleString()}</p>
                    <small class="text-muted">1 USD = ${exchangeRate.toLocaleString()} Shillings</small>
                `;
            } else {
                pricePreview.innerHTML = '<p class="text-muted">Enter price to see conversion</p>';
            }
        }
        
        function updateStockStatus() {
            const quantity = parseInt(quantityInput.value) || 0;
            const minStock = parseInt(minStockInput.value) || 5;
            
            if (quantity === 0) {
                stockStatus.textContent = 'Out of Stock';
                stockAlert.className = 'alert alert-danger';
            } else if (quantity <= minStock) {
                stockStatus.textContent = 'Low Stock - Reorder Needed';
                stockAlert.className = 'alert alert-warning';
            } else {
                stockStatus.textContent = 'Good Stock';
                stockAlert.className = 'alert alert-success';
            }
        }
        
        priceInput.addEventListener('input', updatePricePreview);
        quantityInput.addEventListener('input', updateStockStatus);
        minStockInput.addEventListener('input', updateStockStatus);
        
        // Initialize if editing
        <?php if ($is_edit): ?>
        setTimeout(() => {
            updatePricePreview();
            updateStockStatus();
        }, 100);
        <?php endif; ?>
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
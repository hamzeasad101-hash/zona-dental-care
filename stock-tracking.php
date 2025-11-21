<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $item_id = $_POST['item_id'];
    $new_quantity = $_POST['quantity'];
    $notes = trim($_POST['notes']);
    
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
        $stmt->execute([$new_quantity, $item_id]);
        
        // Log the stock adjustment
        $log_stmt = $pdo->prepare("INSERT INTO stock_log (item_id, adjustment, notes, adjusted_by) VALUES (?, ?, ?, ?)");
        $log_stmt->execute([$item_id, $new_quantity, $notes, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Stock updated successfully";
        header("Location: stock-tracking.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating stock: " . $e->getMessage();
    }
}

// Get inventory with stock status
try {
    $inventory = $pdo->query("
        SELECT * FROM inventory 
        ORDER BY 
            CASE WHEN quantity = 0 THEN 0 
                 WHEN quantity <= min_stock THEN 1 
                 ELSE 2 END,
            item_name
    ")->fetchAll();
    
    // Get stock movement history (if stock_log table exists)
    $stock_history = [];
    try {
        $stock_history = $pdo->query("
            SELECT sl.*, i.item_name, u.username 
            FROM stock_log sl 
            JOIN inventory i ON sl.item_id = i.id 
            LEFT JOIN users u ON sl.adjusted_by = u.id 
            ORDER BY sl.created_at DESC 
            LIMIT 20
        ")->fetchAll();
    } catch (Exception $e) {
        // stock_log table might not exist yet
    }
    
} catch (PDOException $e) {
    $inventory = [];
    $stock_history = [];
    $error = "Error loading stock data: " . $e->getMessage();
}

$exchange_rate = 10750;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Tracking - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stock-critical { background-color: #f8d7da !important; }
        .stock-warning { background-color: #fff3cd !important; }
        .stock-good { background-color: #d1edff !important; }
        .stock-card {
            transition: transform 0.2s ease;
            border-left: 4px solid #0d6efd;
        }
        .stock-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-boxes me-2"></i>Stock Tracking & Management</h1>
                    <a href="inventory.php" class="btn btn-primary">
                        <i class="fas fa-warehouse me-1"></i>Manage Inventory
                    </a>
                </div>

                <!-- Stock Summary -->
                <div class="row mb-4">
                    <?php
                    $total_items = count($inventory);
                    $critical_stock = 0;
                    $low_stock = 0;
                    $good_stock = 0;
                    $total_value = 0;
                    
                    foreach ($inventory as $item) {
                        $total_value += $item['price_usd'] * $item['quantity'];
                        if ($item['quantity'] == 0) {
                            $critical_stock++;
                        } elseif ($item['quantity'] <= $item['min_stock']) {
                            $low_stock++;
                        } else {
                            $good_stock++;
                        }
                    }
                    ?>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Items</h6>
                                <h3><?php echo $total_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Good Stock</h6>
                                <h3><?php echo $good_stock; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Low Stock</h6>
                                <h3><?php echo $low_stock; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h6>Out of Stock</h6>
                                <h3><?php echo $critical_stock; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stock Alerts -->
                <?php if ($critical_stock > 0 || $low_stock > 0): ?>
                <div class="alert alert-warning mb-4">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Stock Alerts</h5>
                    <p class="mb-0">
                        <?php if ($critical_stock > 0): ?>
                        <strong><?php echo $critical_stock; ?> items</strong> are out of stock and 
                        <?php endif; ?>
                        <?php if ($low_stock > 0): ?>
                        <strong><?php echo $low_stock; ?> items</strong> are running low.
                        <?php endif; ?>
                        Please review and reorder as needed.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Stock Items -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Current Stock Levels</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inventory)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Inventory Items</h5>
                                <p class="text-muted">Add inventory items to start tracking stock levels.</p>
                                <a href="inventory_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Add First Item
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Category</th>
                                            <th>Current Stock</th>
                                            <th>Min Stock</th>
                                            <th>Status</th>
                                            <th>Stock Value (USD)</th>
                                            <th>Stock Value (Shillings)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory as $item): 
                                            $stock_value_usd = $item['price_usd'] * $item['quantity'];
                                            $stock_class = '';
                                            if ($item['quantity'] == 0) {
                                                $stock_class = 'stock-critical';
                                            } elseif ($item['quantity'] <= $item['min_stock']) {
                                                $stock_class = 'stock-warning';
                                            } else {
                                                $stock_class = 'stock-good';
                                            }
                                        ?>
                                        <tr class="<?php echo $stock_class; ?>">
                                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $item['quantity'] == 0 ? 'danger' : ($item['quantity'] <= $item['min_stock'] ? 'warning' : 'success'); ?>">
                                                    <?php echo $item['quantity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $item['min_stock']; ?></td>
                                            <td>
                                                <?php if ($item['quantity'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($item['quantity'] <= $item['min_stock']): ?>
                                                    <span class="badge bg-warning text-dark">Reorder Needed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo number_format($stock_value_usd, 2); ?></td>
                                            <td>Sh <?php echo number_format($stock_value_usd * $exchange_rate, 0); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateStockModal" 
                                                        data-item-id="<?php echo $item['id']; ?>" 
                                                        data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                        data-current-stock="<?php echo $item['quantity']; ?>">
                                                    <i class="fas fa-edit"></i> Update
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

                <!-- Stock History -->
                <?php if (!empty($stock_history)): ?>
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Stock Adjustments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Adjustment</th>
                                        <th>Notes</th>
                                        <th>Updated By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_history as $history): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y H:i', strtotime($history['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($history['item_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $history['adjustment'] > 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $history['adjustment'] > 0 ? '+' : ''; ?><?php echo $history['adjustment']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($history['notes'] ?? 'No notes'); ?></td>
                                        <td><?php echo htmlspecialchars($history['username'] ?? 'System'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Update Stock Level</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="modalItemId">
                        <input type="hidden" name="update_stock" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="modalItemName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" class="form-control" id="modalCurrentStock" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Quantity *</label>
                            <input type="number" class="form-control" name="quantity" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Adjustment Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Reason for stock adjustment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update Stock Modal
        const updateStockModal = document.getElementById('updateStockModal');
        updateStockModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const itemId = button.getAttribute('data-item-id');
            const itemName = button.getAttribute('data-item-name');
            const currentStock = button.getAttribute('data-current-stock');
            
            document.getElementById('modalItemId').value = itemId;
            document.getElementById('modalItemName').value = itemName;
            document.getElementById('modalCurrentStock').value = currentStock;
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
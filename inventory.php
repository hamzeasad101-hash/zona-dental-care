<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Handle delete action
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "Inventory item deleted successfully";
        header("Location: inventory.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting inventory item: " . $e->getMessage();
        header("Location: inventory.php");
        exit();
    }
}

// Get all inventory items
try {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "SELECT * FROM inventory WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (item_name LIKE ? OR supplier LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY item_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();
    
    // Get unique categories for filter
    $categories = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $inventory = [];
    $categories = [];
    $error = "Error loading inventory: " . $e->getMessage();
}

$exchange_rate = 10750;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stock-low { background-color: #fff3cd !important; }
        .stock-out { background-color: #f8d7da !important; }
        .stock-good { background-color: #d1edff !important; }
        .inventory-card {
            transition: transform 0.2s ease;
            border-left: 4px solid #0d6efd;
        }
        .inventory-card:hover {
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

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-warehouse me-2"></i>Inventory Management</h1>
                    <a href="inventory_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Item
                    </a>
                </div>

                <!-- Inventory Summary -->
                <div class="row mb-4">
                    <?php
                    $total_items = count($inventory);
                    $low_stock_count = 0;
                    $out_of_stock_count = 0;
                    $total_value_usd = 0;
                    
                    foreach ($inventory as $item) {
                        $total_value_usd += $item['price_usd'] * $item['quantity'];
                        if ($item['quantity'] == 0) {
                            $out_of_stock_count++;
                        } elseif ($item['quantity'] <= $item['min_stock']) {
                            $low_stock_count++;
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
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Low Stock</h6>
                                <h3><?php echo $low_stock_count; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h6>Out of Stock</h6>
                                <h3><?php echo $out_of_stock_count; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Total Value</h6>
                                <h5>$<?php echo number_format($total_value_usd, 2); ?></h5>
                                <small>Sh <?php echo number_format($total_value_usd * $exchange_rate, 0); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Inventory</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="search" class="form-control" placeholder="Search items or suppliers..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="stock_status">
                                    <option value="">All Stock Status</option>
                                    <option value="low" <?php echo ($_GET['stock_status'] ?? '') === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out" <?php echo ($_GET['stock_status'] ?? '') === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                    <option value="good" <?php echo ($_GET['stock_status'] ?? '') === 'good' ? 'selected' : ''; ?>>Good Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inventory List -->
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Inventory Items (<?php echo count($inventory); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inventory)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Inventory Items Found</h5>
                                <p class="text-muted"><?php echo ($search || $category) ? 'No items match your filters.' : 'Get started by adding your first inventory item.'; ?></p>
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
                                            <th>Quantity</th>
                                            <th>Min Stock</th>
                                            <th>Price (USD)</th>
                                            <th>Price (Shillings)</th>
                                            <th>Supplier</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory as $item): 
                                            $stock_class = '';
                                            if ($item['quantity'] == 0) {
                                                $stock_class = 'stock-out';
                                            } elseif ($item['quantity'] <= $item['min_stock']) {
                                                $stock_class = 'stock-low';
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
                                            <td>$<?php echo number_format($item['price_usd'], 2); ?></td>
                                            <td>Sh <?php echo number_format($item['price_usd'] * $exchange_rate, 0); ?></td>
                                            <td><?php echo htmlspecialchars($item['supplier'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($item['quantity'] == 0): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($item['quantity'] <= $item['min_stock']): ?>
                                                    <span class="badge bg-warning text-dark">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="inventory_form.php?id=<?php echo $item['id']; ?>" class="btn btn-warning" title="Edit Item">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="inventory.php?delete_id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-danger" 
                                                       title="Delete Item"
                                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($item['item_name']); ?>?')">
                                                        <i class="fas fa-trash"></i>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
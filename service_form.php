<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$service = null;
$is_edit = false;
$exchange_rate = 10750;

// Check if editing existing service
if (isset($_GET['id'])) {
    $is_edit = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $service = $stmt->fetch();
        
        if (!$service) {
            $_SESSION['error'] = "Service not found";
            header("Location: services.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading service: " . $e->getMessage();
        header("Location: services.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price_usd = floatval($_POST['price_usd']);
    $duration = intval($_POST['duration']);
    
    try {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE services SET 
                name = ?, description = ?, price_usd = ?, duration = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $price_usd, $duration, $service['id']]);
            $_SESSION['success'] = "Service updated successfully";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO services (name, description, price_usd, duration) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price_usd, $duration]);
            $_SESSION['success'] = "Service added successfully";
        }
        
        header("Location: services.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error saving service: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Service' : 'Add Service'; ?> - Zona Dental Care</title>
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
                        <?php echo $is_edit ? 'Edit Service' : 'Add New Service'; ?>
                    </h1>
                    <a href="services.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Services
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
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-teeth me-2"></i>Service Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Service Name *</label>
                                            <input type="text" class="form-control" name="name" 
                                                   value="<?php echo htmlspecialchars($service['name'] ?? ''); ?>" 
                                                   required placeholder="e.g., Dental Checkup, Filling, Root Canal">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea class="form-control" name="description" rows="4" placeholder="Describe the service, procedure details, or any special instructions..."><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Price (USD) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" name="price_usd" 
                                                       value="<?php echo htmlspecialchars($service['price_usd'] ?? ''); ?>" 
                                                       step="0.01" min="0" required>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Duration (minutes) *</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="duration" 
                                                       value="<?php echo htmlspecialchars($service['duration'] ?? 30); ?>" 
                                                       min="5" max="480" required>
                                                <span class="input-group-text">min</span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                <?php echo $is_edit ? 'Update Service' : 'Save Service'; ?>
                                            </button>
                                            <a href="services.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Price Preview</h5>
                            </div>
                            <div class="card-body">
                                <div id="pricePreview">
                                    <p class="text-muted">Enter price to see conversion</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips</h5>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle text-success me-1"></i> Use clear, descriptive service names<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Include all relevant procedure details<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Set realistic duration estimates<br>
                                    <i class="fas fa-check-circle text-success me-1"></i> Consider market-competitive pricing
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
        const pricePreview = document.getElementById('pricePreview');
        
        function updatePricePreview() {
            const priceUSD = parseFloat(priceInput.value) || 0;
            const priceShillings = priceUSD * exchangeRate;
            
            if (priceUSD > 0) {
                pricePreview.innerHTML = `
                    <h4 class="text-primary">$${priceUSD.toFixed(2)}</h4>
                    <p class="text-muted mb-2">Equivalent to:</p>
                    <h5 class="text-success">Sh ${priceShillings.toLocaleString()}</h5>
                    <small class="text-muted">Exchange rate: 1 USD = ${exchangeRate.toLocaleString()} Shillings</small>
                `;
            } else {
                pricePreview.innerHTML = '<p class="text-muted">Enter price to see conversion</p>';
            }
        }
        
        priceInput.addEventListener('input', updatePricePreview);
        
        // Initialize price preview if editing
        <?php if ($is_edit && isset($service['price_usd'])): ?>
        setTimeout(updatePricePreview, 100);
        <?php endif; ?>
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
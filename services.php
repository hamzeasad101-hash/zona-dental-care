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
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "Service deleted successfully";
        header("Location: services.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting service: " . $e->getMessage();
        header("Location: services.php");
        exit();
    }
}

// Get all services
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $services = [];
    $error = "Error loading services: " . $e->getMessage();
}

$exchange_rate = 10750;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .price-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .service-card {
            transition: transform 0.2s ease;
            border: 1px solid #e9ecef;
        }
        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
                    <h1 class="h3 text-primary"><i class="fas fa-teeth me-2"></i>Dental Services Management</h1>
                    <a href="service_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add New Service
                    </a>
                </div>

                <!-- Services Grid -->
                <div class="row">
                    <?php if (empty($services)): ?>
                        <div class="col-12">
                            <div class="card shadow text-center py-5">
                                <div class="card-body">
                                    <i class="fas fa-teeth-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Services Found</h5>
                                    <p class="text-muted">Get started by adding your first dental service.</p>
                                    <a href="service_form.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Add First Service
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                            <div class="card service-card shadow h-100">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($service['name']); ?></h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars($service['description'] ?? 'No description available.'); ?>
                                    </p>
                                    
                                    <div class="price-card text-center">
                                        <h4 class="mb-1">$<?php echo number_format($service['price_usd'], 2); ?></h4>
                                        <h6 class="mb-0">Sh <?php echo number_format($service['price_usd'] * $exchange_rate, 0); ?></h6>
                                        <small>Duration: <?php echo $service['duration'] ?? 30; ?> minutes</small>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i> 
                                            <?php echo $service['duration'] ?? 30; ?> minutes
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="btn-group w-100">
                                        <a href="service_form.php?id=<?php echo $service['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="services.php?delete_id=<?php echo $service['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($service['name']); ?>?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Services Summary -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Services Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3 class="text-primary"><?php echo count($services); ?></h3>
                                        <p class="text-muted mb-0">Total Services</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-success">$<?php 
                                            $total_value = array_sum(array_column($services, 'price_usd'));
                                            echo number_format($total_value, 2);
                                        ?></h3>
                                        <p class="text-muted mb-0">Total Value (USD)</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-warning">Sh <?php 
                                            echo number_format($total_value * $exchange_rate, 0);
                                        ?></h3>
                                        <p class="text-muted mb-0">Total Value (Shillings)</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-info"><?php 
                                            $avg_duration = array_sum(array_column($services, 'duration')) / count($services);
                                            echo round($avg_duration);
                                        ?> min</h3>
                                        <p class="text-muted mb-0">Average Duration</p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
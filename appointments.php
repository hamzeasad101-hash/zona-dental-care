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
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "Appointment deleted successfully";
        header("Location: appointments.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting appointment: " . $e->getMessage();
        header("Location: appointments.php");
        exit();
    }
}

// Get appointments with filters
try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "
        SELECT a.*, p.full_name, p.phone, s.name as service_name, s.price_usd
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN services s ON a.service_id = s.id 
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(a.appointment_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(a.appointment_date) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($status)) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY a.appointment_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $appointments = [];
    $error = "Error loading appointments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                    <h1 class="h3 text-primary"><i class="fas fa-calendar-check me-2"></i>Appointments Management</h1>
                    <a href="appointment_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>New Appointment
                    </a>
                </div>

                <!-- Filter Section -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Appointments</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments List -->
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Appointments List (<?php echo count($appointments); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Appointments Found</h5>
                                <p class="text-muted"><?php echo ($date_from || $date_to || $status) ? 'No appointments match your filters.' : 'No appointments scheduled yet.'; ?></p>
                                <a href="appointment_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create First Appointment
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Patient Name</th>
                                            <th>Phone</th>
                                            <th>Date & Time</th>
                                            <th>Service</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $exchange_rate = 3600;
                                        foreach ($appointments as $index => $appointment): 
                                            $price_shillings = ($appointment['price_usd'] ?? 0) * $exchange_rate;
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($appointment['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php echo date('M d, Y @ h:i A', strtotime($appointment['appointment_date'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['service_name'] ?? 'General Checkup'); ?></td>
                                            <td>
                                                <small class="text-primary">$<?php echo number_format($appointment['price_usd'] ?? 0, 2); ?></small><br>
                                                <small class="text-muted">Sh <?php echo number_format($price_shillings, 0); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badge = [
                                                    'scheduled' => ['bg-warning text-dark', 'Scheduled'],
                                                    'completed' => ['bg-success text-white', 'Completed'],
                                                    'cancelled' => ['bg-danger text-white', 'Cancelled']
                                                ][$appointment['status']] ?? ['bg-secondary text-white', 'Unknown'];
                                                ?>
                                                <span class="badge <?php echo $status_badge[0]; ?>">
                                                    <?php echo $status_badge[1]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="appointment_form.php?id=<?php echo $appointment['id']; ?>" class="btn btn-warning" title="Edit Appointment">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="appointments.php?delete_id=<?php echo $appointment['id']; ?>" 
                                                       class="btn btn-danger" 
                                                       title="Delete Appointment"
                                                       onclick="return confirm('Are you sure you want to delete this appointment?')">
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
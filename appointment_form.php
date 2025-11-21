<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$appointment = null;
$is_edit = false;
$exchange_rate = 10750;

// Check if editing existing appointment
if (isset($_GET['id'])) {
    $is_edit = true;
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.full_name, s.name as service_name, s.price_usd
            FROM appointments a 
            LEFT JOIN patients p ON a.patient_id = p.id 
            LEFT JOIN services s ON a.service_id = s.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            $_SESSION['error'] = "Appointment not found";
            header("Location: appointments.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading appointment: " . $e->getMessage();
        header("Location: appointments.php");
        exit();
    }
}

// Get patients and services for dropdowns
try {
    $patients = $pdo->query("SELECT id, full_name, phone FROM patients ORDER BY full_name")->fetchAll();
    $services = $pdo->query("SELECT id, name, price_usd, duration FROM services ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $patients = [];
    $services = [];
    $error = "Error loading data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $service_id = $_POST['service_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    
    try {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE appointments SET 
                patient_id = ?, service_id = ?, appointment_date = ?, status = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$patient_id, $service_id, $appointment_datetime, $status, $notes, $appointment['id']]);
            $_SESSION['success'] = "Appointment updated successfully";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, service_id, appointment_date, status, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$patient_id, $service_id, $appointment_datetime, $status, $notes]);
            $_SESSION['success'] = "Appointment created successfully";
        }
        
        header("Location: appointments.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error saving appointment: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Appointment' : 'New Appointment'; ?> - Zona Dental Care</title>
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
                        <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-calendar-plus'; ?> me-2"></i>
                        <?php echo $is_edit ? 'Edit Appointment' : 'Schedule New Appointment'; ?>
                    </h1>
                    <a href="appointments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Appointments
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
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Appointment Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Select Patient *</label>
                                            <select class="form-select" name="patient_id" required>
                                                <option value="">Choose a patient...</option>
                                                <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>" 
                                                    <?php echo ($appointment['patient_id'] ?? '') == $patient['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['phone'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Select Service *</label>
                                            <select class="form-select" name="service_id" required id="serviceSelect">
                                                <option value="">Choose a service...</option>
                                                <?php foreach ($services as $service): ?>
                                                <option value="<?php echo $service['id']; ?>" 
                                                    data-price="<?php echo $service['price_usd']; ?>"
                                                    <?php echo ($appointment['service_id'] ?? '') == $service['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($service['name'] . ' - $' . number_format($service['price_usd'], 2)); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Appointment Date *</label>
                                            <input type="date" class="form-control" name="appointment_date" 
                                                   value="<?php echo isset($appointment['appointment_date']) ? date('Y-m-d', strtotime($appointment['appointment_date'])) : date('Y-m-d'); ?>" 
                                                   required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Appointment Time *</label>
                                            <input type="time" class="form-control" name="appointment_time" 
                                                   value="<?php echo isset($appointment['appointment_date']) ? date('H:i', strtotime($appointment['appointment_date'])) : '09:00'; ?>" 
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="scheduled" <?php echo ($appointment['status'] ?? 'scheduled') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="completed" <?php echo ($appointment['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($appointment['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="notes" rows="4" placeholder="Any special notes or instructions..."><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i>
                                                <?php echo $is_edit ? 'Update Appointment' : 'Schedule Appointment'; ?>
                                            </button>
                                            <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Price Information</h5>
                            </div>
                            <div class="card-body">
                                <div id="priceDisplay">
                                    <p class="text-muted">Select a service to see pricing details</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Info</h5>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i> Clinic Hours: 8:00 AM - 6:00 PM<br>
                                    <i class="fas fa-calendar me-1"></i> Monday - Saturday<br>
                                    <i class="fas fa-phone me-1"></i> Emergency: 24/7 Available
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
        const serviceSelect = document.getElementById('serviceSelect');
        const priceDisplay = document.getElementById('priceDisplay');
        
        function updatePriceDisplay() {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const price = selectedOption.dataset.price;
            
            if (price) {
                const priceUSD = parseFloat(price);
                const priceShillings = priceUSD * exchangeRate;
                
                priceDisplay.innerHTML = `
                    <h4 class="text-primary">$${priceUSD.toFixed(2)}</h4>
                    <p class="text-muted mb-2">Equivalent to:</p>
                    <h5 class="text-success">Sh ${priceShillings.toLocaleString()}</h5>
                    <small class="text-muted">Exchange rate: 1 USD = ${exchangeRate.toLocaleString()} Shillings</small>
                `;
            } else {
                priceDisplay.innerHTML = '<p class="text-muted">Select a service to see pricing details</p>';
            }
        }
        
        serviceSelect.addEventListener('change', updatePriceDisplay);
        
        // Initialize price display if editing
        <?php if ($is_edit && isset($appointment['price_usd'])): ?>
        setTimeout(updatePriceDisplay, 100);
        <?php endif; ?>
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Get patient ID from URL
$patient_id = $_GET['id'] ?? '';

if (empty($patient_id)) {
    $_SESSION['error'] = "Patient ID not specified";
    header("Location: patients.php");
    exit();
}

// Get patient details
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        $_SESSION['error'] = "Patient not found";
        header("Location: patients.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading patient details: " . $e->getMessage();
    header("Location: patients.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary">
                        <i class="fas fa-user me-2"></i>Patient Details
                    </h1>
                    <div>
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Patients
                        </a>
                        <a href="patient_form.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i>Edit Patient
                        </a>
                    </div>
                </div>

                <!-- Patient Details Card -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Patient Information - <?php echo htmlspecialchars($patient['full_name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Personal Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="30%"><strong>Full Name:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date of Birth:</strong></td>
                                        <td>
                                            <?php 
                                            if (!empty($patient['date_of_birth']) && $patient['date_of_birth'] != '0000-00-00') {
                                                echo date('M d, Y', strtotime($patient['date_of_birth']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Gender:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-muted">Additional Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Address:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['address'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Emergency Contact:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['emergency_contact'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Insurance:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['insurance'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Medical History:</strong></td>
                                        <td><?php echo htmlspecialchars($patient['medical_history'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date Added:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($patient['notes'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted">Additional Notes</h6>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($patient['notes'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$patient = null;
$is_edit = false;

// Check if editing existing patient
if (isset($_GET['id'])) {
    $is_edit = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            $_SESSION['error'] = "Patient not found";
            header("Location: patients.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading patient: " . $e->getMessage();
        header("Location: patients.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $date_of_birth = $_POST['date_of_birth'];
    $address = trim($_POST['address']);
    $medical_history = trim($_POST['medical_history']);
    
    try {
        if ($is_edit) {
            $stmt = $pdo->prepare("
                UPDATE patients SET 
                full_name = ?, email = ?, phone = ?, date_of_birth = ?, address = ?, medical_history = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $date_of_birth, $address, $medical_history, $patient['id']]);
            $_SESSION['success'] = "Patient updated successfully";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO patients (full_name, email, phone, date_of_birth, address, medical_history) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$full_name, $email, $phone, $date_of_birth, $address, $medical_history]);
            $_SESSION['success'] = "Patient added successfully";
        }
        
        header("Location: patients.php");
        exit();
        
    } catch (PDOException $e) {
        $error = "Error saving patient: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Patient' : 'Add Patient'; ?> - Zona Dental Care</title>
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
                        <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-user-plus'; ?> me-2"></i>
                        <?php echo $is_edit ? 'Edit Patient' : 'Add New Patient'; ?>
                    </h1>
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Patients
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>" 
                                           required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Medical History</label>
                                    <textarea class="form-control" name="medical_history" rows="4" placeholder="Any known allergies, medical conditions, or previous dental treatments..."><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>
                                        <?php echo $is_edit ? 'Update Patient' : 'Save Patient'; ?>
                                    </button>
                                    <a href="patients.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
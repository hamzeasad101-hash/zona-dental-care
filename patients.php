<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Handle actions
$action = $_GET['action'] ?? '';
$patient_id = $_GET['id'] ?? '';

// Delete patient
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "Patient deleted successfully";
        header("Location: patients.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting patient: " . $e->getMessage();
        header("Location: patients.php");
        exit();
    }
}

// Get all patients
try {
    $search = $_GET['search'] ?? '';
    $sql = "SELECT * FROM patients";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ?";
        $search_term = "%$search%";
        $params = [$search_term, $search_term, $search_term];
    }
    
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    $patients = [];
    $error = "Error loading patients: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .patient-card {
            transition: transform 0.2s ease;
        }
        .patient-card:hover {
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
                    <h1 class="h3 text-primary"><i class="fas fa-users me-2"></i>Patients Management</h1>
                    <a href="patient_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Add New Patient
                    </a>
                </div>

                <!-- Search and Filter -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-search me-2"></i>Search Patients</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, phone, or email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Patients List -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Patients List (<?php echo count($patients); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($patients)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Patients Found</h5>
                                <p class="text-muted"><?php echo empty($search) ? 'Get started by adding your first patient.' : 'No patients match your search criteria.'; ?></p>
                                <a href="patient_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Add First Patient
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <th>Full Name</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Date of Birth</th>
                                            <th>Date Added</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $index => $patient): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($patient['date_of_birth']) && $patient['date_of_birth'] != '0000-00-00') {
                                                    echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="patient_form.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning" title="Edit Patient">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="patients.php?delete_id=<?php echo $patient['id']; ?>" 
                                                       class="btn btn-danger" 
                                                       title="Delete Patient"
                                                       onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($patient['full_name']); ?>? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
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

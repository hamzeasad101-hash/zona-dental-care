<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xray_file'])) {
    $patient_id = $_POST['patient_id'];
    $xray_name = trim($_POST['xray_name']);
    $xray_date = $_POST['xray_date'];
    $description = trim($_POST['description']);
    
    $upload_dir = 'uploads/xrays/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['xray_file']['name']);
    $target_file = $upload_dir . $file_name;
    
    // Check if file is an image
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'dicom'];
    
    if (in_array($image_file_type, $allowed_types)) {
        if (move_uploaded_file($_FILES['xray_file']['tmp_name'], $target_file)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO xrays (patient_id, xray_name, xray_date, description, file_path) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$patient_id, $xray_name, $xray_date, $description, $target_file]);
                $_SESSION['success'] = "X-Ray uploaded successfully";
                header("Location: xray.php");
                exit();
            } catch (PDOException $e) {
                $error = "Error saving X-Ray record: " . $e->getMessage();
                // Delete uploaded file if database insert fails
                unlink($target_file);
            }
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    } else {
        $error = "Sorry, only JPG, JPEG, PNG, GIF, BMP & DICOM files are allowed.";
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    try {
        // Get file path before deleting record
        $stmt = $pdo->prepare("SELECT file_path FROM xrays WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $xray = $stmt->fetch();
        
        if ($xray && file_exists($xray['file_path'])) {
            unlink($xray['file_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM xrays WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['success'] = "X-Ray deleted successfully";
        header("Location: xray.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting X-Ray: " . $e->getMessage();
        header("Location: xray.php");
        exit();
    }
}

// Get all xrays with patient information
try {
    $xrays = $pdo->query("
        SELECT x.*, p.full_name, p.phone 
        FROM xrays x 
        JOIN patients p ON x.patient_id = p.id 
        ORDER BY x.created_at DESC
    ")->fetchAll();
    
    // Get patients for dropdown
    $patients = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name")->fetchAll();
    
} catch (PDOException $e) {
    $xrays = [];
    $patients = [];
    $error = "Error loading X-Ray data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X-Ray Management - Zona Dental Care</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .xray-card {
            transition: transform 0.2s ease;
            border: 1px solid #e9ecef;
        }
        .xray-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .xray-image {
            height: 200px;
            object-fit: cover;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .upload-area {
            border: 2px dashed #0d6efd;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #0b5ed7;
        }
        .upload-area.dragover {
            background: #d1edff;
            border-color: #0b5ed7;
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

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary"><i class="fas fa-x-ray me-2"></i>X-Ray Management</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadXrayModal">
                        <i class="fas fa-upload me-1"></i>Upload X-Ray
                    </button>
                </div>

                <!-- X-Ray Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total X-Rays</h6>
                                <h3><?php echo count($xrays); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Total Patients</h6>
                                <h3><?php echo count($patients); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>This Month</h6>
                                <h3>
                                    <?php
                                    $current_month = date('Y-m');
                                    $month_count = 0;
                                    foreach ($xrays as $xray) {
                                        if (date('Y-m', strtotime($xray['xray_date'])) === $current_month) {
                                            $month_count++;
                                        }
                                    }
                                    echo $month_count;
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Storage Used</h6>
                                <h3>
                                    <?php
                                    $total_size = 0;
                                    foreach ($xrays as $xray) {
                                        if (file_exists($xray['file_path'])) {
                                            $total_size += filesize($xray['file_path']);
                                        }
                                    }
                                    echo round($total_size / (1024 * 1024), 1) . ' MB';
                                    ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- X-Rays Grid -->
                <div class="row">
                    <?php if (empty($xrays)): ?>
                        <div class="col-12">
                            <div class="card shadow text-center py-5">
                                <div class="card-body">
                                    <i class="fas fa-x-ray fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No X-Rays Found</h5>
                                    <p class="text-muted">Upload your first X-Ray to get started.</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadXrayModal">
                                        <i class="fas fa-upload me-1"></i>Upload First X-Ray
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($xrays as $xray): ?>
                        <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                            <div class="card xray-card shadow h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($xray['xray_name']); ?></h6>
                                </div>
                                <div class="card-body">
                                    <!-- X-Ray Image Preview -->
                                    <div class="text-center mb-3">
                                        <?php if (file_exists($xray['file_path'])): 
                                            $file_ext = strtolower(pathinfo($xray['file_path'], PATHINFO_EXTENSION));
                                        ?>
                                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])): ?>
                                                <img src="<?php echo $xray['file_path']; ?>" alt="X-Ray" class="xray-image w-100" 
                                                     style="cursor: pointer;" onclick="openImageViewer('<?php echo $xray['file_path']; ?>')">
                                            <?php else: ?>
                                                <div class="xray-image w-100 d-flex align-items-center justify-content-center bg-light">
                                                    <i class="fas fa-file-medical fa-3x text-muted"></i>
                                                </div>
                                                <small class="text-muted">DICOM File - <?php echo strtoupper($file_ext); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="xray-image w-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                            </div>
                                            <small class="text-danger">File not found</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- X-Ray Details -->
                                    <div class="xray-details">
                                        <p class="mb-2">
                                            <strong>Patient:</strong> <?php echo htmlspecialchars($xray['full_name']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Date:</strong> <?php echo date('M j, Y', strtotime($xray['xray_date'])); ?>
                                        </p>
                                        <?php if ($xray['description']): ?>
                                        <p class="mb-2">
                                            <strong>Description:</strong> 
                                            <small><?php echo htmlspecialchars($xray['description']); ?></small>
                                        </p>
                                        <?php endif; ?>
                                        <p class="mb-0">
                                            <strong>Uploaded:</strong> 
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($xray['created_at'])); ?></small>
                                        </p>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="btn-group w-100">
                                        <?php if (file_exists($xray['file_path'])): ?>
                                        <a href="<?php echo $xray['file_path']; ?>" class="btn btn-primary btn-sm" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="<?php echo $xray['file_path']; ?>" class="btn btn-info btn-sm" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="xray.php?delete_id=<?php echo $xray['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this X-Ray?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Upload X-Ray Modal -->
    <div class="modal fade" id="uploadXrayModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Upload New X-Ray</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Patient *</label>
                                <select class="form-select" name="patient_id" required>
                                    <option value="">Choose a patient...</option>
                                    <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">X-Ray Date *</label>
                                <input type="date" class="form-control" name="xray_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">X-Ray Name *</label>
                                <input type="text" class="form-control" name="xray_name" 
                                       placeholder="e.g., Panoramic X-Ray, Bitewing, Periapical" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Any notes or observations about this X-Ray..."></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">X-Ray File *</label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>Drag & Drop X-Ray File Here</h5>
                                    <p class="text-muted">or click to browse</p>
                                    <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF, BMP, DICOM</small>
                                    <input type="file" name="xray_file" id="xrayFile" class="d-none" accept=".jpg,.jpeg,.png,.gif,.bmp,.dicom" required>
                                </div>
                                <div id="fileInfo" class="mt-2 d-none">
                                    <div class="alert alert-info">
                                        <i class="fas fa-file me-2"></i>
                                        <span id="fileName"></span>
                                        <small class="text-muted" id="fileSize"></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload X-Ray</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">X-Ray View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="X-Ray" id="modalXrayImage" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('xrayFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });
        
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = ` (${formatFileSize(file.size)})`;
                fileInfo.classList.remove('d-none');
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Image viewer
        function openImageViewer(imageSrc) {
            const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
            document.getElementById('modalXrayImage').src = imageSrc;
            modal.show();
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
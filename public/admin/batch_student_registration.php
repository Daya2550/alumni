<?php
/**
 * Batch Student Registration - Admin Only
 * Allows admins to register multiple students at once
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
Auth::requireRole('admin');
$user = Auth::getCurrentUser();

$message = '';
$error = '';
$success_count = 0;
$error_count = 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'csv_upload') {
            // Handle CSV file upload
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $csv_file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($csv_file, 'r');
                
                if ($handle !== false) {
                    $header = fgetcsv($handle); // Skip header row
                    $batch_data = [];
                    
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) >= 3) { // name, email, batch
                            $batch_data[] = [
                                'name' => trim($data[0]),
                                'email' => trim($data[1]),
                                'batch' => trim($data[2]),
                                'role' => trim($data[3] ?? 'student')
                            ];
                        }
                    }
                    fclose($handle);
                    
                    // Process batch registration
                    foreach ($batch_data as $student) {
                        if (empty($student['name']) || empty($student['email']) || empty($student['batch'])) {
                            $results[] = [
                                'status' => 'error',
                                'name' => $student['name'],
                                'email' => $student['email'],
                                'message' => 'Missing required fields'
                            ];
                            $error_count++;
                            continue;
                        }
                        
                        if (!validateEmail($student['email'])) {
                            $results[] = [
                                'status' => 'error',
                                'name' => $student['name'],
                                'email' => $student['email'],
                                'message' => 'Invalid email format'
                            ];
                            $error_count++;
                            continue;
                        }
                        
                        // Check if user already exists
                        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$student['email']]);
                        if ($existing) {
                            $results[] = [
                                'status' => 'error',
                                'name' => $student['name'],
                                'email' => $student['email'],
                                'message' => 'Email already exists'
                            ];
                            $error_count++;
                            continue;
                        }
                        
                        try {
                            // Create new user
                            $user_id = generateUUID();
                            $password = generateRandomPassword();
                            $verification_token = bin2hex(random_bytes(32));
                            
                            db()->execute(
                                "INSERT INTO users (id, name, email, password_hash, role, batch, verification_token, email_verified, is_active, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())",
                                [
                                    $user_id,
                                    sanitizeInput($student['name']),
                                    sanitizeInput($student['email']),
                                    hashPassword($password),
                                    sanitizeInput($student['role']),
                                    sanitizeInput($student['batch']),
                                    $verification_token
                                ]
                            );
                            
                            $results[] = [
                                'status' => 'success',
                                'name' => $student['name'],
                                'email' => $student['email'],
                                'message' => 'Registered successfully',
                                'password' => $password
                            ];
                            $success_count++;
                            
                        } catch (Exception $e) {
                            $results[] = [
                                'status' => 'error',
                                'name' => $student['name'],
                                'email' => $student['email'],
                                'message' => 'Database error: ' . $e->getMessage()
                            ];
                            $error_count++;
                        }
                    }
                    
                    $message = "Batch registration completed. Success: $success_count, Errors: $error_count";
                } else {
                    $error = 'Failed to read CSV file.';
                }
            } else {
                $error = 'Please select a valid CSV file.';
            }
        } elseif ($action === 'manual_add') {
            // Handle manual single student addition
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $batch = sanitizeInput($_POST['batch'] ?? '');
            $role = sanitizeInput($_POST['role'] ?? 'student');
            
            if (empty($name) || empty($email) || empty($batch)) {
                $error = 'Please fill in all required fields.';
            } elseif (!validateEmail($email)) {
                $error = 'Please provide a valid email address.';
            } else {
                // Check if user already exists
                $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existing) {
                    $error = 'An account with this email already exists.';
                } else {
                    try {
                        $user_id = generateUUID();
                        $password = generateRandomPassword();
                        $verification_token = bin2hex(random_bytes(32));
                        
                        db()->execute(
                            "INSERT INTO users (id, name, email, password_hash, role, batch, verification_token, email_verified, is_active, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())",
                            [$user_id, $name, $email, hashPassword($password), $role, $batch, $verification_token]
                        );
                        
                        $message = "Student registered successfully! Password: $password";
                        $success_count = 1;
                        
                    } catch (Exception $e) {
                        $error = 'Registration failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Get CSRF token
$csrf_token = Auth::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Student Registration - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        .result-item {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        .result-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-users-plus me-2"></i>
                        Batch Student Registration
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- CSV Upload Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-file-csv me-2"></i>
                                    CSV Upload
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Upload a CSV file with student information. Format: Name, Email, Batch, Role (optional)</p>
                                
                                <form method="POST" enctype="multipart/form-data" id="csvForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="csv_upload">
                                    
                                    <div class="upload-area" id="uploadArea">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h5>Drop CSV file here or click to browse</h5>
                                        <p class="text-muted">Maximum file size: 10MB</p>
                                        <input type="file" name="csv_file" id="csvFile" accept=".csv" class="d-none" required>
                                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('csvFile').click()">
                                            <i class="fas fa-folder-open me-2"></i>
                                            Choose File
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i>
                                            Upload and Process
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>CSV Format:</strong><br>
                                        Name, Email, Batch, Role<br>
                                        John Doe, john@example.com, 2020-2024, student<br>
                                        Jane Smith, jane@example.com, 2019-2023, alumni
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Registration Section -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Manual Registration
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="manualForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="manual_add">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="batch" class="form-label">Batch/Year *</label>
                                        <input type="text" class="form-control" id="batch" name="batch" 
                                               placeholder="e.g., 2020-2024" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select" id="role" name="role">
                                            <option value="student" selected>Student</option>
                                            <option value="alumni">Alumni</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Register Student
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Section -->
                <?php if (!empty($results)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>
                                Registration Results
                                <span class="badge bg-success ms-2"><?php echo $success_count; ?> Success</span>
                                <span class="badge bg-danger ms-1"><?php echo $error_count; ?> Errors</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Message</th>
                                            <th>Password</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $result): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($result['status'] === 'success'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Success
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-times"></i> Error
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($result['name']); ?></td>
                                                <td><?php echo htmlspecialchars($result['email']); ?></td>
                                                <td><?php echo htmlspecialchars($result['message']); ?></td>
                                                <td>
                                                    <?php if (isset($result['password'])): ?>
                                                        <code><?php echo htmlspecialchars($result['password']); ?></code>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload drag and drop
        const uploadArea = document.getElementById('uploadArea');
        const csvFile = document.getElementById('csvFile');

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
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                csvFile.files = files;
                updateFileDisplay(files[0]);
            }
        });

        csvFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateFileDisplay(e.target.files[0]);
            }
        });

        function updateFileDisplay(file) {
            const uploadArea = document.getElementById('uploadArea');
            uploadArea.innerHTML = `
                <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                <h5>${file.name}</h5>
                <p class="text-muted">Size: ${(file.size / 1024).toFixed(2)} KB</p>
                <button type="button" class="btn btn-outline-secondary" onclick="resetUpload()">
                    <i class="fas fa-times me-2"></i>
                    Remove
                </button>
            `;
        }

        function resetUpload() {
            const uploadArea = document.getElementById('uploadArea');
            csvFile.value = '';
            uploadArea.innerHTML = `
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                <h5>Drop CSV file here or click to browse</h5>
                <p class="text-muted">Maximum file size: 10MB</p>
                <input type="file" name="csv_file" id="csvFile" accept=".csv" class="d-none" required>
                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('csvFile').click()">
                    <i class="fas fa-folder-open me-2"></i>
                    Choose File
                </button>
            `;
        }
    </script>
</body>
</html>










<?php
/**
 * Health Check for Alumni Portal
 * Simple diagnostic page to check system status
 */

// Basic PHP info
$php_version = phpversion();
$php_status = version_compare($php_version, '7.4.0', '>=') ? "✅ PHP $php_version" : "❌ PHP $php_version (requires 7.4+)";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'curl'];
$extensions_status = [];
foreach ($required_extensions as $ext) {
    $extensions_status[$ext] = extension_loaded($ext) ? "✅ $ext" : "❌ $ext";
}

// Check file permissions
$upload_dirs = ['uploads', 'public/uploads'];
$permissions_status = [];
foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        $permissions_status[$dir] = is_writable($dir) ? "✅ $dir writable" : "❌ $dir not writable";
    } else {
        $permissions_status[$dir] = "❌ $dir directory missing";
    }
}

// Test database connection
try {
    require_once 'config.php';
    require_once 'includes/database.php';
    $test_query = db()->fetchOne("SELECT 1 as test");
    $db_status = "✅ Database connection successful";
} catch (Exception $e) {
    $db_status = "❌ Database connection failed: " . $e->getMessage();
}

// Test session
$session_status = session_status() === PHP_SESSION_ACTIVE ? "✅ Session active" : "❌ Session not active";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Portal - Health Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-card {
            border-left: 4px solid #28a745;
        }
        .status-card.error {
            border-left-color: #dc3545;
        }
        .status-card.warning {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h2 class="card-title mb-0">
                            <i class="fas fa-heartbeat me-2"></i>
                            Alumni Portal - Health Check
                        </h2>
                    </div>
                    <div class="card-body">
                        
                        <!-- System Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card status-card">
                                    <div class="card-body">
                                        <h6 class="card-title">PHP Version</h6>
                                        <p class="card-text"><?php echo $php_status; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card status-card">
                                    <div class="card-body">
                                        <h6 class="card-title">Session Status</h6>
                                        <p class="card-text"><?php echo $session_status; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Database Status -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card status-card">
                                    <div class="card-body">
                                        <h6 class="card-title">Database Connection</h6>
                                        <p class="card-text"><?php echo $db_status; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Required Extensions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5>Required PHP Extensions</h5>
                                <div class="row">
                                    <?php foreach ($extensions_status as $ext => $status): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="card status-card <?php echo strpos($status, '❌') !== false ? 'error' : ''; ?>">
                                                <div class="card-body py-2">
                                                    <small><?php echo $status; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- File Permissions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5>File Permissions</h5>
                                <div class="row">
                                    <?php foreach ($permissions_status as $dir => $status): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="card status-card <?php echo strpos($status, '❌') !== false ? 'error' : ''; ?>">
                                                <div class="card-body py-2">
                                                    <small><?php echo $status; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="row">
                            <div class="col-12">
                                <h5>Quick Links</h5>
                                <div class="d-grid gap-2 d-md-block">
                                    <a href="public/index.php" class="btn btn-primary">
                                        <i class="fas fa-home me-2"></i>
                                        Go to Homepage
                                    </a>
                                    <a href="public/login.php" class="btn btn-outline-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Regular Login
                                    </a>
                                    <a href="public/test_oauth_flow.php" class="btn btn-outline-info">
                                        <i class="fas fa-cogs me-2"></i>
                                        Test OAuth Flow
                                    </a>
                                    <a href="public/admin/batch_student_registration.php" class="btn btn-outline-success">
                                        <i class="fas fa-users-plus me-2"></i>
                                        Batch Registration (Admin)
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Server Information -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h5>Server Information</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Server Software:</strong></td>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Document Root:</strong></td>
                                            <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Current Directory:</strong></td>
                                            <td><?php echo getcwd(); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>PHP Memory Limit:</strong></td>
                                            <td><?php echo ini_get('memory_limit'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Max Upload Size:</strong></td>
                                            <td><?php echo ini_get('upload_max_filesize'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>










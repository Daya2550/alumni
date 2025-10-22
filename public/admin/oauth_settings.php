<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
Auth::requireRole('admin');
$user = Auth::getCurrentUser();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $linkedin_client_id = trim($_POST['linkedin_client_id'] ?? '');
    $linkedin_client_secret = trim($_POST['linkedin_client_secret'] ?? '');
    $linkedin_redirect_uri = trim($_POST['linkedin_redirect_uri'] ?? '');
    $linkedin_enabled = isset($_POST['linkedin_enabled']) ? 1 : 0;
    
    $google_client_id = trim($_POST['google_client_id'] ?? '');
    $google_client_secret = trim($_POST['google_client_secret'] ?? '');
    $google_redirect_uri = trim($_POST['google_redirect_uri'] ?? '');
    $google_enabled = isset($_POST['google_enabled']) ? 1 : 0;
    
    try {
        // Update LinkedIn settings
        $linkedin_settings = [
            'linkedin_client_id' => $linkedin_client_id,
            'linkedin_client_secret' => $linkedin_client_secret,
            'linkedin_redirect_uri' => $linkedin_redirect_uri,
            'linkedin_enabled' => $linkedin_enabled
        ];
        
        // Update Google settings
        $google_settings = [
            'google_client_id' => $google_client_id,
            'google_client_secret' => $google_client_secret,
            'google_redirect_uri' => $google_redirect_uri,
            'google_enabled' => $google_enabled
        ];
        
        // Update settings in database
        foreach (array_merge($linkedin_settings, $google_settings) as $key => $value) {
            db()->execute(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value]
            );
        }
        
        $message = '<div class="alert alert-success">OAuth settings updated successfully!</div>';
        
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error updating settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get current settings
$settings = [];
$setting_keys = [
    'linkedin_client_id', 'linkedin_client_secret', 'linkedin_redirect_uri', 'linkedin_enabled',
    'google_client_id', 'google_client_secret', 'google_redirect_uri', 'google_enabled'
];

foreach ($setting_keys as $key) {
    $result = db()->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    $settings[$key] = $result ? $result['setting_value'] : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-light" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="user_management.php">
                                <i class="fas fa-users me-2"></i>
                                User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light active" href="oauth_settings.php">
                                <i class="fas fa-cog me-2"></i>
                                OAuth Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">OAuth Settings</h1>
                </div>

                <?php echo $message; ?>

                <div class="row">
                    <!-- LinkedIn Settings -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fab fa-linkedin text-primary me-2"></i>
                                    LinkedIn OAuth Configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="linkedin_client_id" class="form-label">Client ID</label>
                                        <input type="text" class="form-control" id="linkedin_client_id" name="linkedin_client_id" 
                                               value="<?php echo htmlspecialchars($settings['linkedin_client_id']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="linkedin_client_secret" class="form-label">Client Secret</label>
                                        <input type="password" class="form-control" id="linkedin_client_secret" name="linkedin_client_secret" 
                                               value="<?php echo htmlspecialchars($settings['linkedin_client_secret']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="linkedin_redirect_uri" class="form-label">Redirect URI</label>
                                        <input type="url" class="form-control" id="linkedin_redirect_uri" name="linkedin_redirect_uri" 
                                               value="<?php echo htmlspecialchars($settings['linkedin_redirect_uri'] ?: 'https://linkedin.free.nf/public/linkedin_callback.php'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="linkedin_enabled" name="linkedin_enabled" 
                                               <?php echo $settings['linkedin_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="linkedin_enabled">
                                            Enable LinkedIn Login
                                        </label>
                                    </div>
                            </div>
                        </div>
                    </div>

                    <!-- Google Settings -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fab fa-google text-danger me-2"></i>
                                    Google OAuth Configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                    <div class="mb-3">
                                        <label for="google_client_id" class="form-label">Client ID</label>
                                        <input type="text" class="form-control" id="google_client_id" name="google_client_id" 
                                               value="<?php echo htmlspecialchars($settings['google_client_id']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="google_client_secret" class="form-label">Client Secret</label>
                                        <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" 
                                               value="<?php echo htmlspecialchars($settings['google_client_secret']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="google_redirect_uri" class="form-label">Redirect URI</label>
                                        <input type="url" class="form-control" id="google_redirect_uri" name="google_redirect_uri" 
                                               value="<?php echo htmlspecialchars($settings['google_redirect_uri'] ?: 'https://linkedin.free.nf/public/google_callback.php'); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="google_enabled" name="google_enabled" 
                                               <?php echo $settings['google_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="google_enabled">
                                            Enable Google Login
                                        </label>
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>
                                    Save OAuth Settings
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                </form>

                <!-- Quick Links -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-link me-2"></i>
                                    Quick Links
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="../login.php" class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="fas fa-sign-in-alt me-1"></i>
                                        Test Login Page
                                    </a>
                                    <a href="../linkedin_login.php" class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="fab fa-linkedin me-1"></i>
                                        Test LinkedIn Login
                                    </a>
                                    <a href="../google_login.php" class="btn btn-outline-danger btn-sm" target="_blank">
                                        <i class="fab fa-google me-1"></i>
                                        Test Google Login
                                    </a>
                                    <a href="user_management.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-users me-1"></i>
                                        View Users
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
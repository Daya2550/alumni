<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
Auth::requireRole('admin');
$user = Auth::getCurrentUser();

$message = '';
$status_checks = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        // Save all integration settings
        $settings = [
            // LinkedIn Settings
            'linkedin_client_id' => trim($_POST['linkedin_client_id'] ?? ''),
            'linkedin_client_secret' => trim($_POST['linkedin_client_secret'] ?? ''),
            'linkedin_redirect_uri' => trim($_POST['linkedin_redirect_uri'] ?? ''),
            'linkedin_enabled' => isset($_POST['linkedin_enabled']) ? 1 : 0,
            
            // Google Settings
            'google_client_id' => trim($_POST['google_client_id'] ?? ''),
            'google_client_secret' => trim($_POST['google_client_secret'] ?? ''),
            'google_redirect_uri' => trim($_POST['google_redirect_uri'] ?? ''),
            'google_enabled' => isset($_POST['google_enabled']) ? 1 : 0,
            
            // Facebook Settings (for future)
            'facebook_app_id' => trim($_POST['facebook_app_id'] ?? ''),
            'facebook_app_secret' => trim($_POST['facebook_app_secret'] ?? ''),
            'facebook_redirect_uri' => trim($_POST['facebook_redirect_uri'] ?? ''),
            'facebook_enabled' => isset($_POST['facebook_enabled']) ? 1 : 0,
            
            // Twitter Settings (for future)
            'twitter_api_key' => trim($_POST['twitter_api_key'] ?? ''),
            'twitter_api_secret' => trim($_POST['twitter_api_secret'] ?? ''),
            'twitter_redirect_uri' => trim($_POST['twitter_redirect_uri'] ?? ''),
            'twitter_enabled' => isset($_POST['twitter_enabled']) ? 1 : 0,
            
            // Email Settings
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => trim($_POST['smtp_port'] ?? ''),
            'smtp_username' => trim($_POST['smtp_username'] ?? ''),
            'smtp_password' => trim($_POST['smtp_password'] ?? ''),
            'smtp_encryption' => trim($_POST['smtp_encryption'] ?? ''),
            
            // reCAPTCHA Settings
            'recaptcha_site_key' => trim($_POST['recaptcha_site_key'] ?? ''),
            'recaptcha_secret_key' => trim($_POST['recaptcha_secret_key'] ?? ''),
            'recaptcha_enabled' => isset($_POST['recaptcha_enabled']) ? 1 : 0,
        ];
        
        try {
            foreach ($settings as $key => $value) {
                db()->execute(
                    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$key, $value]
                );
            }
            $message = '<div class="alert alert-success">All integration settings saved successfully!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Error saving settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Get current settings
$settings = [];
$setting_keys = [
    'linkedin_client_id', 'linkedin_client_secret', 'linkedin_redirect_uri', 'linkedin_enabled',
    'google_client_id', 'google_client_secret', 'google_redirect_uri', 'google_enabled',
    'facebook_app_id', 'facebook_app_secret', 'facebook_redirect_uri', 'facebook_enabled',
    'twitter_api_key', 'twitter_api_secret', 'twitter_redirect_uri', 'twitter_enabled',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption',
    'recaptcha_site_key', 'recaptcha_secret_key', 'recaptcha_enabled'
];

foreach ($setting_keys as $key) {
    $result = db()->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    $settings[$key] = $result ? $result['setting_value'] : '';
}

// Set default values
$settings['linkedin_redirect_uri'] = $settings['linkedin_redirect_uri'] ?: 'https://linkedin.free.nf/callback.php';
$settings['google_redirect_uri'] = $settings['google_redirect_uri'] ?: 'https://linkedin.free.nf/public/google_callback.php';
$settings['facebook_redirect_uri'] = $settings['facebook_redirect_uri'] ?: 'https://linkedin.free.nf/public/facebook_callback.php';
$settings['twitter_redirect_uri'] = $settings['twitter_redirect_uri'] ?: 'https://linkedin.free.nf/public/twitter_callback.php';
$settings['smtp_port'] = $settings['smtp_port'] ?: '587';
$settings['smtp_encryption'] = $settings['smtp_encryption'] ?: 'tls';

// Function to check integration status
function checkIntegrationStatus($provider, $client_id, $client_secret) {
    if (empty($client_id) || empty($client_secret)) {
        return ['status' => 'not_configured', 'message' => 'API credentials not set'];
    }
    
    // Basic validation - check if credentials look valid
    $valid_patterns = [
        'linkedin' => '/^[a-zA-Z0-9]+$/',
        'google' => '/^[a-zA-Z0-9-_]+\.apps\.googleusercontent\.com$/',
        'facebook' => '/^[0-9]+$/',
        'twitter' => '/^[a-zA-Z0-9]+$/'
    ];
    
    if (isset($valid_patterns[$provider])) {
        if (!preg_match($valid_patterns[$provider], $client_id)) {
            return ['status' => 'invalid', 'message' => 'Invalid credential format'];
        }
    }
    
    return ['status' => 'configured', 'message' => 'Credentials configured'];
}

// Check status of each integration
$status_checks = [
    'linkedin' => checkIntegrationStatus('linkedin', $settings['linkedin_client_id'], $settings['linkedin_client_secret']),
    'google' => checkIntegrationStatus('google', $settings['google_client_id'], $settings['google_client_secret']),
    'facebook' => checkIntegrationStatus('facebook', $settings['facebook_app_id'], $settings['facebook_app_secret']),
    'twitter' => checkIntegrationStatus('twitter', $settings['twitter_api_key'], $settings['twitter_api_secret']),
    'email' => checkIntegrationStatus('email', $settings['smtp_host'], $settings['smtp_username']),
    'recaptcha' => checkIntegrationStatus('recaptcha', $settings['recaptcha_site_key'], $settings['recaptcha_secret_key'])
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .integration-card {
            transition: transform 0.2s;
        }
        .integration-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .config-section {
            border-left: 4px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
    </style>
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
                            <a class="nav-link text-light active" href="integrations.php">
                                <i class="fas fa-plug me-2"></i>
                                Integrations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-light" href="oauth_settings.php">
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
                    <h1 class="h2">Integration Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../test_oauth_flow.php" class="btn btn-outline-primary" target="_blank">
                            <i class="fas fa-flask me-1"></i>
                            Test Integrations
                        </a>
                    </div>
                </div>

                <?php echo $message; ?>

                <!-- Integration Status Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Integration Status Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($status_checks as $provider => $status): ?>
                                    <div class="col-md-2 col-sm-4 mb-2">
                                        <div class="d-flex align-items-center">
                                            <i class="fab fa-<?php echo $provider === 'email' ? 'envelope' : ($provider === 'recaptcha' ? 'shield-alt' : $provider); ?> fa-2x me-2 text-<?php echo $status['status'] === 'configured' ? 'success' : ($status['status'] === 'invalid' ? 'warning' : 'secondary'); ?>"></i>
                                            <div>
                                                <div class="fw-bold text-capitalize"><?php echo $provider; ?></div>
                                                <span class="badge status-badge bg-<?php echo $status['status'] === 'configured' ? 'success' : ($status['status'] === 'invalid' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($status['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <!-- OAuth Providers -->
                    <div class="config-section">
                        <h4><i class="fas fa-key me-2"></i>OAuth Providers</h4>
                        <p class="text-muted">Configure social login integrations</p>
                        
                        <div class="row">
                            <!-- LinkedIn -->
                            <div class="col-md-6 mb-4">
                                <div class="card integration-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fab fa-linkedin text-primary me-2"></i>
                                            LinkedIn
                                        </h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="linkedin_enabled" name="linkedin_enabled" 
                                                   <?php echo $settings['linkedin_enabled'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="linkedin_client_id" class="form-label">Client ID</label>
                                            <input type="text" class="form-control" id="linkedin_client_id" name="linkedin_client_id" 
                                                   value="<?php echo htmlspecialchars($settings['linkedin_client_id']); ?>" 
                                                   placeholder="78jcfr9mle6fu5">
                                        </div>
                                        <div class="mb-3">
                                            <label for="linkedin_client_secret" class="form-label">Client Secret</label>
                                            <input type="password" class="form-control" id="linkedin_client_secret" name="linkedin_client_secret" 
                                                   value="<?php echo htmlspecialchars($settings['linkedin_client_secret']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="linkedin_redirect_uri" class="form-label">Redirect URI</label>
                                            <input type="url" class="form-control" id="linkedin_redirect_uri" name="linkedin_redirect_uri" 
                                                   value="<?php echo htmlspecialchars($settings['linkedin_redirect_uri']); ?>">
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Get credentials from <a href="https://www.linkedin.com/developers/" target="_blank">LinkedIn Developers</a>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Google -->
                            <div class="col-md-6 mb-4">
                                <div class="card integration-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fab fa-google text-danger me-2"></i>
                                            Google
                                        </h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="google_enabled" name="google_enabled" 
                                                   <?php echo $settings['google_enabled'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="google_client_id" class="form-label">Client ID</label>
                                            <input type="text" class="form-control" id="google_client_id" name="google_client_id" 
                                                   value="<?php echo htmlspecialchars($settings['google_client_id']); ?>" 
                                                   placeholder="your-app.apps.googleusercontent.com">
                                        </div>
                                        <div class="mb-3">
                                            <label for="google_client_secret" class="form-label">Client Secret</label>
                                            <input type="password" class="form-control" id="google_client_secret" name="google_client_secret" 
                                                   value="<?php echo htmlspecialchars($settings['google_client_secret']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="google_redirect_uri" class="form-label">Redirect URI</label>
                                            <input type="url" class="form-control" id="google_redirect_uri" name="google_redirect_uri" 
                                                   value="<?php echo htmlspecialchars($settings['google_redirect_uri']); ?>">
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Get credentials from <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Facebook (Future) -->
                            <div class="col-md-6 mb-4">
                                <div class="card integration-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fab fa-facebook text-primary me-2"></i>
                                            Facebook
                                            <span class="badge bg-secondary ms-2">Coming Soon</span>
                                        </h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="facebook_enabled" name="facebook_enabled" 
                                                   <?php echo $settings['facebook_enabled'] ? 'checked' : ''; ?> disabled>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="facebook_app_id" class="form-label">App ID</label>
                                            <input type="text" class="form-control" id="facebook_app_id" name="facebook_app_id" 
                                                   value="<?php echo htmlspecialchars($settings['facebook_app_id']); ?>" disabled>
                                        </div>
                                        <div class="mb-3">
                                            <label for="facebook_app_secret" class="form-label">App Secret</label>
                                            <input type="password" class="form-control" id="facebook_app_secret" name="facebook_app_secret" 
                                                   value="<?php echo htmlspecialchars($settings['facebook_app_secret']); ?>" disabled>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Facebook integration coming soon
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Twitter (Future) -->
                            <div class="col-md-6 mb-4">
                                <div class="card integration-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fab fa-twitter text-info me-2"></i>
                                            Twitter
                                            <span class="badge bg-secondary ms-2">Coming Soon</span>
                                        </h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="twitter_enabled" name="twitter_enabled" 
                                                   <?php echo $settings['twitter_enabled'] ? 'checked' : ''; ?> disabled>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="twitter_api_key" class="form-label">API Key</label>
                                            <input type="text" class="form-control" id="twitter_api_key" name="twitter_api_key" 
                                                   value="<?php echo htmlspecialchars($settings['twitter_api_key']); ?>" disabled>
                                        </div>
                                        <div class="mb-3">
                                            <label for="twitter_api_secret" class="form-label">API Secret</label>
                                            <input type="password" class="form-control" id="twitter_api_secret" name="twitter_api_secret" 
                                                   value="<?php echo htmlspecialchars($settings['twitter_api_secret']); ?>" disabled>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Twitter integration coming soon
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Configuration -->
                    <div class="config-section">
                        <h4><i class="fas fa-envelope me-2"></i>Email Configuration</h4>
                        <p class="text-muted">Configure SMTP settings for email notifications</p>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" 
                                                       placeholder="smtp.gmail.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" 
                                                       placeholder="587">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_username" class="form-label">Username</label>
                                                <input type="email" class="form-control" id="smtp_username" name="smtp_username" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" 
                                                       placeholder="your-email@gmail.com">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="smtp_password" class="form-label">Password</label>
                                                <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" 
                                                       placeholder="App password">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="smtp_encryption" class="form-label">Encryption</label>
                                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Configuration -->
                    <div class="config-section">
                        <h4><i class="fas fa-shield-alt me-2"></i>Security Configuration</h4>
                        <p class="text-muted">Configure security features</p>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fas fa-robot me-2"></i>
                                            reCAPTCHA
                                        </h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="recaptcha_enabled" name="recaptcha_enabled" 
                                                   <?php echo $settings['recaptcha_enabled'] ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="recaptcha_site_key" class="form-label">Site Key</label>
                                            <input type="text" class="form-control" id="recaptcha_site_key" name="recaptcha_site_key" 
                                                   value="<?php echo htmlspecialchars($settings['recaptcha_site_key']); ?>" 
                                                   placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI">
                                        </div>
                                        <div class="mb-3">
                                            <label for="recaptcha_secret_key" class="form-label">Secret Key</label>
                                            <input type="password" class="form-control" id="recaptcha_secret_key" name="recaptcha_secret_key" 
                                                   value="<?php echo htmlspecialchars($settings['recaptcha_secret_key']); ?>">
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Get credentials from <a href="https://www.google.com/recaptcha/" target="_blank">Google reCAPTCHA</a>
                                        </small>
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
                                        Save All Integration Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tools me-2"></i>
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <a href="../test_oauth_flow.php" class="btn btn-outline-primary w-100" target="_blank">
                                            <i class="fas fa-flask me-1"></i>
                                            Test OAuth Flow
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="../login.php" class="btn btn-outline-secondary w-100" target="_blank">
                                            <i class="fas fa-sign-in-alt me-1"></i>
                                            Test Login Page
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="user_management.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-users me-1"></i>
                                            View Users
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="../health_check.php" class="btn btn-outline-success w-100" target="_blank">
                                            <i class="fas fa-heartbeat me-1"></i>
                                            System Health
                                        </a>
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
        // Auto-save functionality
        let saveTimeout;
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('change', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    // Optional: Auto-save after 2 seconds of inactivity
                    // document.querySelector('form').submit();
                }, 2000);
            });
        });
    </script>
</body>
</html>
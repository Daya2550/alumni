<?php
/**
 * Test OAuth Authentication Flow
 * This file helps test both LinkedIn and Google OAuth login processes
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$user_info = '';

// Check if user is logged in
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    $user_info = "Logged in as: " . htmlspecialchars($user['name']) . " (" . htmlspecialchars($user['email']) . ")";
    $user_info .= "<br>Role: " . htmlspecialchars($user['role']);
    $user_info .= "<br>Batch: " . htmlspecialchars($user['batch'] ?? 'Not set');
    $user_info .= "<br>Profile Picture: " . (isset($user['profile_picture']) ? 'Yes' : 'No');
}

// Handle logout
if (isset($_GET['logout'])) {
    Auth::logout();
    header("Location: test_oauth_flow.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Flow Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> OAuth Authentication Flow Test</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($user_info): ?>
                            <div class="alert alert-success">
                                <h5>Authentication Successful!</h5>
                                <?php echo $user_info; ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="student-dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                </a>
                                <a href="test_oauth_flow.php?logout=1" class="btn btn-outline-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5>Test OAuth Authentication</h5>
                                <p>This page tests both LinkedIn and Google OAuth authentication flows. Click the buttons below to test the login processes.</p>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fab fa-linkedin fa-3x text-primary mb-3"></i>
                                            <h5>LinkedIn OAuth</h5>
                                            <p class="text-muted">Test LinkedIn login and auto-registration</p>
                                            <a href="linkedin_login.php" class="btn btn-primary">
                                                <i class="fab fa-linkedin me-2"></i> Test LinkedIn Login
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fab fa-google fa-3x text-danger mb-3"></i>
                                            <h5>Google OAuth</h5>
                                            <p class="text-muted">Test Google login and auto-registration</p>
                                            <a href="google_login.php" class="btn btn-danger">
                                                <i class="fab fa-google me-2"></i> Test Google Login
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i> Regular Login
                                </a>
                                <a href="register.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-user-plus me-2"></i> Manual Registration
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <h5>OAuth Flow Description:</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fab fa-linkedin text-primary"></i> LinkedIn Flow:</h6>
                                <ol>
                                    <li><strong>Email Check:</strong> System checks if user exists by email</li>
                                    <li><strong>Existing User:</strong> If found, logs in directly (no password required)</li>
                                    <li><strong>New User:</strong> If not found, creates account automatically with LinkedIn data</li>
                                    <li><strong>Auto Login:</strong> New users are logged in immediately after account creation</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fab fa-google text-danger"></i> Google Flow:</h6>
                                <ol>
                                    <li><strong>Email Check:</strong> System checks if user exists by email</li>
                                    <li><strong>Existing User:</strong> If found, logs in directly (no password required)</li>
                                    <li><strong>New User:</strong> If not found, creates account automatically with Google data</li>
                                    <li><strong>Auto Login:</strong> New users are logged in immediately after account creation</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Test Steps:</h6>
                            <ol>
                                <li>Click "Test LinkedIn Login" or "Test Google Login"</li>
                                <li>Authorize the OAuth app</li>
                                <li>You'll be redirected back here with login status</li>
                                <li>If new user, account will be created automatically</li>
                                <li>Test with different email addresses to verify both flows</li>
                            </ol>
                        </div>
                        
                        <div class="alert alert-warning mt-4">
                            <h6><i class="fas fa-exclamation-triangle"></i> Configuration Required:</h6>
                            <p>Make sure to configure your OAuth credentials in the <a href="admin/oauth_settings.php">Admin OAuth Settings</a> page before testing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
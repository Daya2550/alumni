<?php
/**
 * Google OAuth Login Page
 * Initiates the OAuth flow by generating the authorization URL.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Google OAuth Credentials
const CLIENT_ID = GOOGLE_CLIENT_ID;
const REDIRECT_URI = GOOGLE_REDIRECT_URI;

// Google Authorization Endpoint
$auth_url = "https://accounts.google.com/o/oauth2/v2/auth?"
    . "client_id=" . CLIENT_ID
    . "&redirect_uri=" . urlencode(REDIRECT_URI)
    . "&scope=openid%20email%20profile"
    . "&response_type=code"
    . "&state=" . bin2hex(random_bytes(16)); // CSRF protection

// Check if user is already logged in to prevent unnecessary OAuth flow
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    
    // Direct users to their appropriate dashboard based on role
    $dashboard_map = [
        'admin'   => 'admin-dashboard.php',
        'staff'   => 'staff-dashboard.php',
        'student' => 'student-dashboard.php',
    ];
    
    $redirect_page = $dashboard_map[$user['role']] ?? 'student-dashboard.php';
    
    header("Location: " . $redirect_page);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Login - <?php echo defined('APP_NAME') ? APP_NAME : 'Alumni Portal'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .google-btn {
            background-color: #db4437;
            border-color: #db4437;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .google-btn:hover {
            background-color: #c23321;
            border-color: #c23321;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(219, 68, 55, 0.3);
        }
        .login-container { 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card text-center">
            <div class="mb-4">
                <i class="fab fa-google fa-3x text-danger mb-3"></i>
                <h2 class="h4 mb-3">Sign in with Google</h2>
                <p class="text-muted">Login or create your account automatically</p>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <a href="<?php echo htmlspecialchars($auth_url); ?>" class="google-btn">
                <i class="fab fa-google"></i>
                Continue with Google
            </a>
            
            <div class="mt-4">
                <p class="text-muted small">
                    By continuing, you agree to our 
                    <a href="#" class="text-primary">Terms of Service</a> and 
                    <a href="#" class="text-primary">Privacy Policy</a>
                </p>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-center gap-3">
                <a href="login.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    Regular Login
                </a>
                <a href="register.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-user-plus me-1"></i>
                    Manual Registration
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
/**
 * Login Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Check login attempts (basic rate limiting)
        $login_attempts_key = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
        $attempts = $_SESSION[$login_attempts_key] ?? 0;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $error = 'Too many login attempts. Please try again later.';
        } else {
            $user = db()->fetchOne(
                "SELECT * FROM users WHERE email = ? AND role IN ('student', 'alumni') AND is_active = 1",
                [$email]
            );
            
            if ($user && verifyPassword($password, $user['password_hash'])) {
                // Reset login attempts
                unset($_SESSION[$login_attempts_key]);
                
                // Login user
                Auth::login($user['id'], $remember);
                
                // Redirect to intended page or dashboard
                $redirect = $_GET['redirect'] ?? 'index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                // Increment login attempts
                $_SESSION[$login_attempts_key] = $attempts + 1;
                $error = 'Invalid email or password.';
            }
        }
    }
}

// Handle OAuth redirects
if (isset($_GET['oauth']) && isset($_GET['code'])) {
    // This would handle OAuth callback
    // Implementation depends on OAuth provider
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-graduation-cap text-primary" style="font-size: 3rem;"></i>
                            <h2 class="mt-3"><?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>
                        
                        <!-- OAuth Login Options -->
                        <div class="text-center mb-4">
                            <p class="text-muted mb-3">Quick sign in with</p>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <a href="linkedin_login.php" class="btn btn-linkedin w-100" style="background-color: #0077b5; border-color: #0077b5; color: white;">
                                        <i class="fab fa-linkedin me-2"></i>
                                        LinkedIn
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="google_login.php" class="btn btn-google w-100" style="background-color: #db4437; border-color: #db4437; color: white;">
                                        <i class="fab fa-google me-2"></i>
                                        Google
                                    </a>
                                </div>
                            </div>
                            <hr class="my-4">
                            <p class="text-muted">Or sign in with your credentials below</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                <div class="invalid-feedback">
                                    Please provide a valid email address.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">
                                    Please provide your password.
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">


                        <div class="text-center mt-4">
                            <a href="forgot-password.php" class="text-decoration-none">
                                <i class="fas fa-key"></i> Forgot your password?
                            </a>
                        </div>

                        <div class="text-center mt-3">
                            <span class="text-muted">Don't have an account?</span>
                            <a href="register.php" class="text-decoration-none ms-1">
                                <i class="fas fa-user-plus"></i> Sign up
                            </a>
                        </div>
                        
                        <div class="text-center mt-2">
                            <span class="text-muted">Other login options:</span>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="staff-login.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-user-tie"></i> Staff Login
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="admin-login.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-shield-alt"></i> Admin Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Preview -->
                <div class="row mt-4">
                    <div class="col-md-4 text-center">
                        <i class="fas fa-users text-primary fa-2x mb-2"></i>
                        <h6>Connect</h6>
                        <small class="text-muted">Network with alumni</small>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-briefcase text-success fa-2x mb-2"></i>
                        <h6>Opportunities</h6>
                        <small class="text-muted">Find job openings</small>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-calendar-alt text-info fa-2x mb-2"></i>
                        <h6>Events</h6>
                        <small class="text-muted">Stay updated</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

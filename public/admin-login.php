<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as admin
if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    if ($user && $user['role'] === 'admin') {
        header('Location: home-admin.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please provide email and password.';
    } else {
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE email = ? AND role = 'admin' AND is_active = 1",
            [$email]
        );

        if ($user && verifyPassword($password, $user['password_hash'])) {
            Auth::login($user['id']);
            header('Location: home-admin.php');
            exit;
        } else {
            $error = 'Invalid credentials or insufficient privileges.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
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
                            <i class="fas fa-shield-alt text-danger" style="font-size: 3rem;"></i>
                            <h2 class="mt-3">Admin Login</h2>
                            <p class="text-muted">Administrative access only</p>
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

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control" name="email" required>
                                <div class="invalid-feedback">Please enter your email.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" name="password" required>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>
                            <div class="d-grid">
                                <button class="btn btn-danger btn-lg" type="submit">
                                    <i class="fas fa-sign-in-alt"></i> Admin Sign In
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <span class="text-muted">Other login options:</span>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-6">
                                <a href="login.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user"></i> User Login
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="staff-login.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-user-tie"></i> Staff Login
                                </a>
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


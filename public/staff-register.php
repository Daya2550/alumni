<?php
/**
 * Staff Registration Page (separate)
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (Auth::isLoggedIn()) {
    $user = Auth::getCurrentUser();
    if ($user && $user['role'] === 'staff') {
        header('Location: home-staff.php');
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $existing = db()->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'Email already registered.';
        } else {
            try {
                $user_id = generateUUID();
                $verification_token = bin2hex(random_bytes(32));
                db()->execute(
                    "INSERT INTO users (id, name, email, password_hash, role, is_active, verification_token, email_verified) VALUES (?, ?, ?, ?, 'staff', 0, ?, 0)",
                    [$user_id, $name, $email, hashPassword($password), $verification_token]
                );

                $success = 'Registration submitted. An admin will verify your account.';
            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-tie text-primary" style="font-size: 3rem;"></i>
                            <h2 class="mt-3">Staff Registration</h2>
                            <p class="text-muted">Submit your details for admin verification</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-user"></i> Full Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                    <div class="invalid-feedback">Please enter your name.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-envelope"></i> Email *</label>
                                    <input type="email" class="form-control" name="email" required>
                                    <div class="invalid-feedback">Please enter a valid email.</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock"></i> Password *</label>
                                    <input type="password" class="form-control" name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    <div class="invalid-feedback">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock"></i> Confirm Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    <div class="invalid-feedback">Passwords must match.</div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary btn-lg" type="submit">
                                    <i class="fas fa-user-plus"></i> Submit for Verification
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <span class="text-muted">Already registered?</span>
                            <a href="staff-login.php" class="text-decoration-none ms-1">
                                <i class="fas fa-sign-in-alt"></i> Staff Login
                            </a>
                        </div>
                        
                        <div class="text-center mt-2">
                            <span class="text-muted">Are you a student/alumni?</span>
                            <a href="register.php" class="text-decoration-none ms-1">
                                <i class="fas fa-user-graduate"></i> User Registration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




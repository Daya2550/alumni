<?php
/**
 * Registration Page
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
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $batch = sanitizeInput($_POST['batch'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'student');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!validateEmail($email)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $existing_user = db()->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$email]
        );
        
        if ($existing_user) {
            $error = 'An account with this email already exists.';
        } else {
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            try {
                // Insert new user
                $user_id = generateUUID();
                // Users (students/alumni) are active by default
                $is_active = 1;
                db()->execute(
                    "INSERT INTO users (id, name, email, password_hash, role, batch, verification_token, email_verified, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)",
                    [$user_id, $name, $email, hashPassword($password), $role, $batch, $verification_token, $is_active]
                );
                
                // TODO: Send verification email
                // sendVerificationEmail($email, $name, $verification_token);
                
                $success = 'Registration successful! Please check your email to verify your account.';
                
                // Auto-login users after registration
                Auth::login($user_id);
                header('Location: index.php');
                exit;
                
            } catch (Exception $e) {
                $error = 'Registration failed. Please try again.';
                error_log("Registration error: " . $e->getMessage());
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
    <title>Register - <?php echo APP_NAME; ?></title>
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
                            <i class="fas fa-graduation-cap text-primary" style="font-size: 3rem;"></i>
                            <h2 class="mt-3">Join <?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Create your account to get started</p>
                        </div>
                        
                        <!-- OAuth Registration Options -->
                        <div class="text-center mb-4">
                            <p class="text-muted mb-3">Quick registration with</p>
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
                            <p class="text-muted">Or register manually below</p>
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
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-user"></i> Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide your full name.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide a valid email address.
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i> Password *
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    <div class="invalid-feedback">
                                        Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-lock"></i> Confirm Password *
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    <div class="invalid-feedback">
                                        Passwords must match.
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">
                                        <i class="fas fa-user-tag"></i> Role *
                                    </label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="student" <?php echo ($role ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="alumni" <?php echo ($role ?? '') === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                                    </select>
                                    <small class="form-text text-muted">Are you a current student or alumni?</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar"></i> Admission year/Graduation year
                                    </label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="number" class="form-control" id="admission_year" name="admission_year"
                                                   placeholder="Admission (e.g., 2020)" min="2010" max="2099" step="1">
                                        </div>
                                        <div class="col-6">
                                            <input type="number" class="form-control" id="graduation_year" name="graduation_year"
                                                   placeholder="Graduation (e.g., 2024)" min="2010" max="2099" step="1">
                                        </div>
                                    </div>
                                    <input type="hidden" id="batch" name="batch" value="<?php echo htmlspecialchars($batch ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and 
                                    <a href="privacy.php" target="_blank">Privacy Policy</a> *
                                </label>
                                <div class="invalid-feedback">
                                    You must agree to the terms and conditions.
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <span class="text-muted">Already have an account?</span>
                            <a href="login.php" class="text-decoration-none ms-1">
                                <i class="fas fa-sign-in-alt"></i> Sign in
                            </a>
                        </div>
                        
                        <div class="text-center mt-2">
                            <span class="text-muted">Are you staff?</span>
                            <a href="staff-register.php" class="text-decoration-none ms-1">
                                <i class="fas fa-user-tie"></i> Staff Registration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Combine Admission and Graduation Year into hidden batch field as "YYYY - YYYY"
        (function() {
            const admission = document.getElementById('admission_year');
            const graduation = document.getElementById('graduation_year');
            const batchInput = document.getElementById('batch');
            const form = document.querySelector('form');

            if (!admission || !graduation || !batchInput || !form) return;

            function updateBatch() {
                const a = (admission.value || '').trim();
                const g = (graduation.value || '').trim();
                if (a && g) {
                    batchInput.value = `${a} - ${g}`;
                } else if (a || g) {
                    // If only one is present, keep what's entered (to avoid losing user input)
                    batchInput.value = a || g;
                } else {
                    batchInput.value = '';
                }
            }

            // Pre-fill year fields if batch already has a value like "YYYY - YYYY" or "YYYY-YYYY"
            (function prefillFromBatch() {
                const v = (batchInput.value || '').trim();
                const match = v.match(/^(\d{4})\s*-\s*(\d{4})$/);
                if (match) {
                    admission.value = match[1];
                    graduation.value = match[2];
                }
            })();

            admission.addEventListener('input', updateBatch);
            graduation.addEventListener('input', updateBatch);
            form.addEventListener('submit', updateBatch);
        })();
    </script>
</body>
</html>

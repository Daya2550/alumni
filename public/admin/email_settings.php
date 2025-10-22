<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/mail.php';

Auth::requireAnyRole(['admin', 'staff']);

$user = Auth::getCurrentUser();
$message = '';
$error = '';

// Load settings
$smtp_host = AppSettings::get('smtp_host', SMTP_HOST);
$smtp_port = AppSettings::get('smtp_port', SMTP_PORT);
$smtp_user = AppSettings::get('smtp_user', SMTP_USER);
$smtp_pass = AppSettings::get('smtp_pass', SMTP_PASS);
$from_email = AppSettings::get('from_email', FROM_EMAIL);
$from_name = AppSettings::get('from_name', FROM_NAME);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else if (isset($_POST['save_settings'])) {
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = (int) ($_POST['smtp_port'] ?? 587);
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $from_email = trim($_POST['from_email'] ?? '');
        $from_name = trim($_POST['from_name'] ?? '');

        if ($from_email && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'FROM email is invalid.';
        } else {
            AppSettings::set('smtp_host', $smtp_host);
            AppSettings::set('smtp_port', (string)$smtp_port);
            AppSettings::set('smtp_user', $smtp_user);
            AppSettings::set('smtp_pass', $smtp_pass);
            AppSettings::set('from_email', $from_email);
            AppSettings::set('from_name', $from_name);
            $message = 'Settings saved.';
        }
    } else if (isset($_POST['test_email'])) {
        $to = trim($_POST['test_to'] ?? '');
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid test email address.';
        } else {
            $ok = sendEmail($to, 'Test Email - ' . APP_NAME, '<p>This is a test email from ' . htmlspecialchars(APP_NAME) . '.</p>');
            if ($ok) { $message = 'Test email sent to ' . htmlspecialchars($to) . '.'; }
            else { $error = 'Failed to send test email. Check server mail configuration.'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>.form-text small{color:#666;}</style>
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="X-Frame-Options" content="DENY" />
    <meta http-equiv="X-Content-Type-Options" content="nosniff" />
    <meta http-equiv="Referrer-Policy" content="no-referrer" />
    <meta http-equiv="Permissions-Policy" content="interest-cohort=()" />
    <meta http-equiv="Cross-Origin-Opener-Policy" content="same-origin" />
    <meta http-equiv="Cross-Origin-Resource-Policy" content="same-site" />
    <meta http-equiv="Cross-Origin-Embedder-Policy" content="require-corp" />
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3"><i class="fas fa-envelope text-primary"></i> Email Settings</h1>
        <a href="/public/admin/index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>SMTP / From Settings</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                <div class="col-md-6">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="col-md-3">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars((string)$smtp_port); ?>" placeholder="587">
                </div>
                <div class="col-md-3">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($smtp_user); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" class="form-control" name="smtp_pass" value="<?php echo htmlspecialchars($smtp_pass); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">From Email</label>
                    <input type="email" class="form-control" name="from_email" value="<?php echo htmlspecialchars($from_email); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">From Name</label>
                    <input type="text" class="form-control" name="from_name" value="<?php echo htmlspecialchars($from_name); ?>" required>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-primary" name="save_settings" value="1"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Send Test Email</strong></div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                <div class="col-md-8">
                    <label class="form-label">Test recipient</label>
                    <input type="email" class="form-control" name="test_to" placeholder="you@example.com" required>
                </div>
                <div class="col-md-4 align-self-end text-end">
                    <button class="btn btn-outline-primary" name="test_email" value="1"><i class="fas fa-paper-plane"></i> Send Test</button>
                </div>
            </form>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



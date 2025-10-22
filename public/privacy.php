<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy - <?php echo APP_NAME; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
  <?php include 'includes/header.php'; ?>

  <main class="container py-4">
    <div class="row">
      <div class="col-12">
        <div class="card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h1 class="h4 mb-0">
              <i class="fas fa-shield-alt text-primary me-2"></i>Privacy Policy
            </h1>
            <small class="text-muted">Last updated: <?php echo date('F j, Y'); ?></small>
          </div>
          <div class="card-body">
            <p><?php echo APP_NAME; ?> respects your privacy. This policy explains what data we collect, how we use it, and your choices.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>1. Information We Collect</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Account data (e.g., name, email, role, batch, profile details) you provide.</li>
              <li>Usage data (e.g., pages visited, interactions) to improve the service.</li>
              <li>Content you upload (e.g., posts, attachments, profile photo).</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>2. How We Use Information</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Operate and improve the platform and its features.</li>
              <li>Facilitate networking, messaging, events, and other alumni activities.</li>
              <li>Maintain security, prevent abuse, and comply with policies.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>3. Sharing</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Within the community according to your privacy settings (e.g., profile visibility, batch visibility).</li>
              <li>With service providers needed to run the platform (e.g., email, storage), bound by confidentiality obligations.</li>
              <li>When required by law or to protect rights and safety.</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>4. Your Choices</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Manage profile visibility and messaging preferences in Settings.</li>
              <li>Update or delete your content where supported by the product.</li>
              <li>Contact support to request data corrections where necessary.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>5. Cookies & Storage</strong></div>
          <div class="card-body">
            <p class="mb-0">We use session cookies and local storage to keep you signed in and to remember preferences. You can control cookies in your browser settings; some features may not work without them.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>6. Security</strong></div>
          <div class="card-body">
            <p class="mb-0">We apply reasonable safeguards to protect your data. No system is perfectly secure; please use strong passwords and protect your account.</p>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-header"><strong>7. Contact</strong></div>
          <div class="card-body">
            <p class="mb-2">Questions about this policy? Contact us:</p>
            <ul class="mb-0">
              <li>Email: <a href="mailto:<?php echo htmlspecialchars(FROM_EMAIL); ?>"><?php echo htmlspecialchars(FROM_EMAIL); ?></a></li>
              <li>Phone: <a href="tel:+918380025630">+918380025630</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

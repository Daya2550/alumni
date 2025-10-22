<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms of Use - <?php echo APP_NAME; ?></title>
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
              <i class="fas fa-file-contract text-primary me-2"></i>Terms of Use
            </h1>
            <small class="text-muted">Last updated: <?php echo date('F j, Y'); ?></small>
          </div>
          <div class="card-body">
            <p>Welcome to <?php echo APP_NAME; ?>. By accessing or using this site, you agree to these Terms of Use. If you do not agree, please do not use the site.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>1. Use of the Service</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Use the platform in compliance with applicable laws and institutional policies.</li>
              <li>Do not attempt to disrupt the service or compromise other users’ data.</li>
              <li>Content you post must be respectful, accurate, and appropriate for an alumni/community platform.</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>2. Accounts</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>You are responsible for safeguarding your account credentials.</li>
              <li>Notify administrators of any suspected unauthorized use of your account.</li>
              <li>We may restrict or terminate accounts that violate these terms.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>3. User Content</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>You retain ownership of content you submit.</li>
              <li>By posting, you grant us a non-exclusive license to host and display your content on the platform.</li>
              <li>Do not upload content that infringes on others’ rights or contains sensitive information you do not wish to share.</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>4. Prohibited Activities</strong></div>
          <div class="card-body">
            <ul class="mb-0">
              <li>Harassment, hate speech, or illegal activities.</li>
              <li>Spamming, phishing, or distributing malware.</li>
              <li>Bypassing access controls or scraping protected data.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>5. Disclaimers</strong></div>
          <div class="card-body">
            <p class="mb-0">The service is provided on an "as is" basis. We do not guarantee uninterrupted availability or freedom from errors. Use at your own risk.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><strong>6. Changes</strong></div>
          <div class="card-body">
            <p class="mb-0">We may update these terms from time to time. Continued use after changes indicates acceptance of the revised terms.</p>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-header"><strong>7. Contact</strong></div>
          <div class="card-body">
            <p class="mb-2">Questions about these Terms? Reach us at:</p>
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

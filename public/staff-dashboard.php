<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireRole('staff');
$user = Auth::getCurrentUser();

$pending_staff = db()->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role = 'staff' AND is_active = 0")['c'] ?? 0;
$pending_jobs = db()->fetchOne("SELECT COUNT(*) AS c FROM job_opportunities WHERE status = 'pending'")['c'] ?? 0;
$draft_news = db()->fetchOne("SELECT COUNT(*) AS c FROM news_posts WHERE status = 'draft'")['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-user-check fa-2x text-primary me-3"></i>
                        <div>
                            <div class="text-muted">Pending Staff Approvals</div>
                            <div class="h5 mb-0"><?php echo $pending_staff; ?></div>
                        </div>
                        <a href="admin/" class="btn btn-sm btn-outline-primary ms-auto">Review</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-briefcase fa-2x text-warning me-3"></i>
                        <div>
                            <div class="text-muted">Jobs Pending</div>
                            <div class="h5 mb-0"><?php echo $pending_jobs; ?></div>
                        </div>
                        <a href="jobs.php" class="btn btn-sm btn-outline-warning ms-auto">Manage</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-newspaper fa-2x text-info me-3"></i>
                        <div>
                            <div class="text-muted">News Drafts</div>
                            <div class="h5 mb-0"><?php echo $draft_news; ?></div>
                        </div>
                        <a href="news.php" class="btn btn-sm btn-outline-info ms-auto">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Quick Actions</strong></div>
            <div class="card-body">
                <div class="d-grid gap-2 d-md-flex">
                    <a href="admin/" class="btn btn-primary"><i class="fas fa-user-check"></i> Approve Staff</a>
                    <a href="notices.php" class="btn btn-outline-primary"><i class="fas fa-bullhorn"></i> Post Notice</a>
                    <a href="news.php" class="btn btn-outline-info"><i class="fas fa-newspaper"></i> Post News</a>
                    <a href="gallery.php" class="btn btn-outline-secondary"><i class="fas fa-images"></i> Upload Gallery</a>
                    <a href="analytics.php" class="btn btn-outline-dark"><i class="fas fa-chart-bar"></i> Analytics Dashboard</a>
                    <a href="aiassistant-admin.php" class="btn btn-outline-success"><i class="fas fa-robot"></i> AI Assistant Admin</a>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




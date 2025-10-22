<?php
/**
 * Admin Jobs Management - Review and moderate job submissions
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

Auth::requireAnyRole(['staff','admin']);

// Actions: approve, reject, delete
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    // Approve the job
    db()->execute("UPDATE job_opportunities SET status='approved' WHERE id = ?", [$id]);
    // Load job details to compose notification
    $job = db()->fetchOne("SELECT title, company, posted_by FROM job_opportunities WHERE id = ?", [$id]);
    $admin = Auth::getCurrentUser();
    if ($job) {
        $title = (string)($job['title'] ?? 'Job Opportunity');
        $company = (string)($job['company'] ?? '');
        $msg = $company ? ($title . ' at ' . $company) : $title;
        // Notify all active users except the approving admin
        Notifications::notifyAllActiveUsersExcept($admin['id'] ?? null, 'New Job Opportunity', substr($msg, 0, 120), 'info', 'job', $id);
    }
    header('Location: jobs.php');
    exit;
}

if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    db()->execute("UPDATE job_opportunities SET status='rejected' WHERE id = ?", [$id]);
    header('Location: jobs.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $job = db()->fetchOne("SELECT company_logo FROM job_opportunities WHERE id = ?", [$id]);
    if ($job && !empty($job['company_logo']) && strpos($job['company_logo'], 'uploads/jobs/') === 0) {
        $logoPath = __DIR__ . '/../../' . $job['company_logo'];
        if (file_exists($logoPath)) { @unlink($logoPath); }
    }
    db()->execute("DELETE FROM job_opportunities WHERE id = ?", [$id]);
    header('Location: jobs.php');
    exit;
}

// Lists
$pending = db()->fetchAll(
    "SELECT jo.*, u.name AS author_name FROM job_opportunities jo JOIN users u ON u.id = jo.posted_by WHERE jo.status = 'pending' ORDER BY jo.created_at DESC"
);
$recent = db()->fetchAll(
    "SELECT jo.*, u.name AS author_name FROM job_opportunities jo JOIN users u ON u.id = jo.posted_by ORDER BY jo.created_at DESC LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3"><i class="fas fa-briefcase"></i> Manage Jobs</h1>
            <a href="../jobs.php" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View Jobs</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Pending Submissions</strong></div>
                    <div class="card-body">
                        <?php if (empty($pending)): ?>
                            <p class="text-muted m-0">No pending submissions.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($pending as $item): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <small class="text-muted">By <?php echo htmlspecialchars($item['author_name']); ?> • <?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?approve=<?php echo $item['id']; ?>" class="btn btn-success" title="Approve"><i class="fas fa-check"></i></a>
                                                <a href="?reject=<?php echo $item['id']; ?>" class="btn btn-warning" title="Reject"><i class="fas fa-ban"></i></a>
                                                <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-danger" title="Delete" onclick="return confirm('Delete this job?');"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Recent Jobs</strong></div>
                    <div class="card-body">
                        <?php if (empty($recent)): ?>
                            <p class="text-muted m-0">No jobs yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent as $item): ?>
                                    <a class="list-group-item list-group-item-action" href="../job.php?id=<?php echo $item['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <small class="text-muted text-uppercase"><?php echo htmlspecialchars($item['status']); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>











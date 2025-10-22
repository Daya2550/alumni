<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = sanitizeInput($_POST['category'] ?? 'portal');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target_type = sanitizeInput($_POST['target_type'] ?? 'none');
    $target_id = sanitizeInput($_POST['target_id'] ?? '');
    if ($subject === '' || $message === '') {
        $error = 'Subject and message are required.';
    } else {
        try {
            db()->execute("INSERT INTO feedback (id, user_id, category, subject, message, target_type, target_id, status, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [generateUUID(), $user['id'], $category, $subject, $message, $target_type, $target_id, 'new']
            );
            $success = 'Thank you! Your feedback has been submitted.';
        } catch (Exception $e) {
            $error = 'Failed to submit feedback.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
        <h1><i class="fas fa-comment-dots text-primary"></i> Submit Feedback</h1>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="portal" selected>Portal / UI</option>
                                <option value="event">Event</option>
                                <option value="job">Job</option>
                                <option value="news">News</option>
                                <option value="notice">Notice</option>
                                <option value="project">Project</option>
                                <option value="gallery">Gallery</option>
                                <option value="profile">Profile</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Target Type</label>
                            <select class="form-select" name="target_type">
                                <option value="none" selected>None</option>
                                <option value="event">Event</option>
                                <option value="job">Job</option>
                                <option value="news">News</option>
                                <option value="notice">Notice</option>
                                <option value="project">Project</option>
                                <option value="gallery">Gallery</option>
                                <option value="profile">Profile</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Target ID (optional)</label>
                            <input class="form-control" name="target_id" placeholder="e.g., related item id">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input class="form-control" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="5" required></textarea>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i> Submit</button>
                    </div>
                </form>
            </div>
        </div>
        <?php if (Auth::hasAnyRole(['admin','staff'])): ?>
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="fas fa-inbox"></i> Recent Feedback</strong>
                <a href="admin/feedback.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-sliders-h"></i> Manage</a>
            </div>
            <div class="card-body">
                <?php $recent_feedback = db()->fetchAll("SELECT f.*, u.name as user_name FROM feedback f LEFT JOIN users u ON u.id = f.user_id ORDER BY f.created_at DESC LIMIT 10"); ?>
                <?php if (empty($recent_feedback)): ?>
                    <div class="text-muted">No feedback yet.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_feedback as $r): ?>
                            <a class="list-group-item list-group-item-action" href="admin/feedback.php?status=<?= urlencode($r['status']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($r['subject']); ?></div>
                                        <small class="text-muted">By <a href="profile.php?id=<?php echo urlencode($r['user_id']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($r['user_name'] ?: $r['user_id']); ?></a> • <?php echo htmlspecialchars($r['category']); ?></small>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($r['status']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



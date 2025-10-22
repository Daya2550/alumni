<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

$id = sanitizeInput($_GET['id'] ?? '');

if (!$id) {
    header('HTTP/1.1 404 Not Found');
    echo 'Feedback not found';
    exit;
}

$feedback = db()->fetchOne(
    "SELECT f.*, u.name as user_name, u.email as user_email, u.profile_photo
     FROM feedback f
     LEFT JOIN users u ON u.id = f.user_id
     WHERE f.id = ?",
    [$id]
);

if (!$feedback) {
    header('HTTP/1.1 404 Not Found');
    echo 'Feedback not found';
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = sanitizeInput($_POST['status']);
    if (in_array($new_status, ['new', 'in_review', 'resolved', 'closed'])) {
        try {
            db()->execute("UPDATE feedback SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $id]);
            $feedback['status'] = $new_status;
            $success_message = 'Status updated successfully';
        } catch (Exception $e) {
            $error_message = 'Failed to update status';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Details - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="fas fa-comment-dots text-primary"></i> Feedback Details</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="feedback.php">Feedback Management</a></li>
                        <li class="breadcrumb-item active">Details</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <a href="feedback.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <a href="../profile.php?id=<?php echo urlencode($feedback['user_id']); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-user"></i> View User Profile
                </a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Feedback Content -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-comment"></i> Feedback Content</h5>
                        <?php
                        $status_colors = [
                            'new' => 'warning',
                            'in_review' => 'info', 
                            'resolved' => 'success',
                            'closed' => 'secondary'
                        ];
                        $color = $status_colors[$feedback['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?> fs-6"><?php echo ucfirst($feedback['status']); ?></span>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3"><?php echo htmlspecialchars($feedback['subject']); ?></h4>
                        <div class="mb-3">
                            <strong>Category:</strong> 
                            <span class="badge bg-secondary"><?php echo ucfirst($feedback['category']); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Target Type:</strong> 
                            <span class="text-muted"><?php echo ucfirst($feedback['target_type'] ?: 'None'); ?></span>
                            <?php if ($feedback['target_id']): ?>
                                <span class="text-muted">(ID: <?php echo htmlspecialchars($feedback['target_id']); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <h6 class="mb-2">Message:</h6>
                            <div class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Status Update -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Update Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">New Status</label>
                                <select class="form-select" name="status" required>
                                    <?php foreach (['new', 'in_review', 'resolved', 'closed'] as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo $feedback['status'] === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- User Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> User Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <?php if ($feedback['profile_photo']): ?>
                                <img src="<?php echo htmlspecialchars($feedback['profile_photo']); ?>" 
                                     class="rounded-circle me-3" width="50" height="50">
                            <?php else: ?>
                                <i class="fas fa-user-circle fa-3x text-muted me-3"></i>
                            <?php endif; ?>
                            <div>
                                <h6 class="mb-1">
                                    <a href="../profile.php?id=<?php echo urlencode($feedback['user_id']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($feedback['user_name'] ?: 'Unknown User'); ?>
                                    </a>
                                </h6>
                                <small class="text-muted"><?php echo htmlspecialchars($feedback['user_email'] ?: 'No email'); ?></small>
                            </div>
                        </div>
                        <div class="d-grid">
                            <a href="../profile.php?id=<?php echo urlencode($feedback['user_id']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-user"></i> View Full Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Feedback Metadata -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Feedback Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Submitted:</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($feedback['created_at'])); ?></span>
                        </div>
                        <?php if ($feedback['updated_at'] && $feedback['updated_at'] !== $feedback['created_at']): ?>
                        <div class="mb-3">
                            <strong>Last Updated:</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($feedback['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <strong>Feedback ID:</strong><br>
                            <code class="small"><?php echo htmlspecialchars($feedback['id']); ?></code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>






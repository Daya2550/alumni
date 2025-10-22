<?php
/**
 * Individual Notice View
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$notice_id = intval($_GET['id'] ?? 0);

if (!$notice_id) {
    header('Location: notices.php');
    exit;
}

// Get notice details
$notice = db()->fetchOne(
    "SELECT n.*, u.name as posted_by_name, u.email as posted_by_email
     FROM notices n 
     JOIN users u ON n.posted_by = u.id 
     WHERE n.id = ? AND n.is_active = 1",
    [$notice_id]
);

if (!$notice) {
    header('HTTP/1.1 404 Not Found');
    echo 'Notice not found';
    exit;
}

// Check if user can view this notice (batch access)
if ($notice['batch'] && !Auth::canAccessBatch($notice['batch'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Mark as read
$existing_read = db()->fetchOne(
    "SELECT id FROM notice_reads WHERE notice_id = ? AND user_id = ?",
    [$notice_id, $user['id']]
);

if (!$existing_read) {
    db()->execute(
        "INSERT INTO notice_reads (notice_id, user_id) VALUES (?, ?)",
        [$notice_id, $user['id']]
    );
    
    // Increment view count
    db()->execute(
        "UPDATE notices SET views = views + 1 WHERE id = ?",
        [$notice_id]
    );
}

// Get attachments if any
$attachments = [];
if ($notice['attachments']) {
    $attachments = json_decode($notice['attachments'], true);
}

// Get related notices (same batch or recent)
$related_notices = db()->fetchAll(
    "SELECT n.id, n.title, n.created_at
     FROM notices n 
     WHERE n.id != ? AND n.is_active = 1 
     AND (n.batch = ? OR n.batch IS NULL)
     ORDER BY n.created_at DESC 
     LIMIT 5",
    [$notice_id, $notice['batch']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($notice['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Notice Content -->
                <article class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="h3 mb-1"><?php echo htmlspecialchars($notice['title']); ?></h1>
                                <div class="text-muted">
                                    <i class="fas fa-user"></i> By <?php echo htmlspecialchars($notice['posted_by_name']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y \a\t g:i A', strtotime($notice['created_at'])); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($notice['batch']): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($notice['batch']); ?></span>
                                <?php endif; ?>
                                <?php if ($notice['expiry_date']): ?>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 
                                            Expires: <?php echo date('M j, Y', strtotime($notice['expiry_date'])); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="notice-content">
                            <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                        </div>
                        
                        <?php if (!empty($attachments)): ?>
                            <div class="mt-4">
                                <h5><i class="fas fa-paperclip"></i> Attachments</h5>
                                <!-- Debug info (remove in production) -->
                                <?php /* debug removed */ ?>
                                <div class="row">
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <?php 
                                                    $file_ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
                                                    $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                    ?>
                                                    
                                                    <?php if ($is_image): ?>
                                                        <div class="text-center mb-2">
                                                            <img src="<?php echo htmlspecialchars($attachment['url']); ?>" 
                                                                 class="img-fluid rounded" 
                                                                 style="max-height: 200px; object-fit: cover;"
                                                                 alt="<?php echo htmlspecialchars($attachment['name']); ?>">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center mb-2">
                                                            <i class="fas fa-file fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <h6 class="card-title"><?php echo htmlspecialchars($attachment['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo formatFileSize($attachment['size']); ?>
                                                    </small>
                                                    
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($attachment['url']); ?>" 
                                                           class="btn btn-sm btn-outline-primary w-100" target="_blank">
                                                            <i class="fas fa-<?php echo $is_image ? 'eye' : 'download'; ?>"></i> 
                                                            <?php echo $is_image ? 'View' : 'Download'; ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <i class="fas fa-eye"></i> <?php echo $notice['views']; ?> views
                            </div>
                            <div>
                                <a href="notices.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Notices
                                </a>
                                <?php if (Auth::hasAnyRole(['admin', 'staff'])): ?>
                                    <a href="admin/edit_notice.php?id=<?php echo $notice['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            
            <div class="col-lg-4">
                <!-- Related Notices -->
                <?php if (!empty($related_notices)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list"></i> Related Notices</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($related_notices as $related): ?>
                                    <a href="notice.php?id=<?php echo $related['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="fw-bold"><?php echo htmlspecialchars($related['title']); ?></div>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($related['created_at'])); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="notices.php" class="btn btn-outline-primary">
                                <i class="fas fa-bullhorn"></i> All Notices
                            </a>
                            <?php if ($notice['batch']): ?>
                                <a href="notices.php?batch=<?php echo urlencode($notice['batch']); ?>" class="btn btn-outline-info">
                                    <i class="fas fa-filter"></i> Batch Notices
                                </a>
                            <?php endif; ?>
                            <button onclick="window.print()" class="btn btn-outline-secondary">
                                <i class="fas fa-print"></i> Print Notice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
/**
 * Helper function to format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

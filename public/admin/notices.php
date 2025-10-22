<?php
/**
 * Admin Notices Management
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

Auth::requireAnyRole(['admin', 'staff']);

$user = Auth::getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = sanitizeInput($_POST['content'] ?? '');
        $batch = sanitizeInput($_POST['batch'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        
        // Validate required fields
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required';
        } else {
            try {
                // Handle file uploads
                $attachments = [];
                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = UPLOAD_PATH . 'notices/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_count = count($_FILES['attachments']['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['attachments']['name'][$i];
                            $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                            $file_size = $_FILES['attachments']['size'][$i];
                            $file_type = $_FILES['attachments']['type'][$i];
                            
                            // Validate file type
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            if (!in_array($file_ext, ALLOWED_FILE_TYPES)) {
                                throw new Exception("File type not allowed: $file_ext");
                            }
                            
                            // Validate file size
                            if ($file_size > UPLOAD_MAX_SIZE) {
                                throw new Exception("File too large: " . $file_name);
                            }
                            
                            // Generate unique filename
                            $unique_name = uniqid() . '_' . $file_name;
                            $file_path = $upload_dir . $unique_name;
                            
                            if (move_uploaded_file($file_tmp, $file_path)) {
                                $attachments[] = [
                                    'name' => $file_name,
                                    'path' => $file_path,
                                    'url' => 'uploads/notices/' . $unique_name,
                                    'size' => $file_size,
                                    'type' => $file_type
                                ];
                            }
                        }
                    }
                }
                
                // Insert notice
                $attachments_json = !empty($attachments) ? json_encode($attachments) : null;
                $expiry_date_sql = !empty($expiry_date) ? $expiry_date : null;
                $batch_sql = !empty($batch) ? $batch : null;
                
                db()->execute(
                    "INSERT INTO notices (title, content, posted_by, batch, expiry_date, attachments) 
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$title, $content, $user['id'], $batch_sql, $expiry_date_sql, $attachments_json]
                );
                $newNoticeId = db()->lastInsertId();

                // Notify all active users (optionally filter by batch when provided)
                if (!empty($batch_sql)) {
                    $userIds = db()->fetchAll("SELECT id FROM users WHERE is_active = 1 AND (batch = ? OR role IN ('admin','staff'))", [$batch_sql]);
                    Notifications::notifyUsers(array_column($userIds, 'id'), 'New Notice', substr($title, 0, 120), 'info', 'notice', $newNoticeId);
                } else {
                    Notifications::notifyAllActiveUsersExcept($user['id'], 'New Notice', substr($title, 0, 120), 'info', 'notice', $newNoticeId);
                }
                
                $message = 'Notice created successfully!';
                
            } catch (Exception $e) {
                $error = 'Error creating notice: ' . $e->getMessage();
            }
        }
    }
}

// Get all notices for management
$notices = db()->fetchAll(
    "SELECT n.*, u.name as posted_by_name 
     FROM notices n 
     JOIN users u ON n.posted_by = u.id 
     ORDER BY n.created_at DESC"
);

// Get available batches
$batches = db()->fetchAll(
    "SELECT DISTINCT batch FROM users WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Notices - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bullhorn text-primary"></i> Manage Notices</h1>
                    <div>
                        <a href="../notices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-eye"></i> View All Notices
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Create Notice Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-plus"></i> Create New Notice</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content *</label>
                                        <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="batch" class="form-label">Target Batch (Optional)</label>
                                        <select class="form-select" id="batch" name="batch">
                                            <option value="">All Batches</option>
                                            <?php foreach ($batches as $batch): ?>
                                                <option value="<?php echo htmlspecialchars($batch['batch']); ?>" 
                                                        <?php echo ($_POST['batch'] ?? '') === $batch['batch'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($batch['batch']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                               value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">Attachments</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" 
                                               multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                                        <div class="form-text">Max file size: <?php echo round(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB per file</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Notice
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Notices -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> All Notices</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notices)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bullhorn text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No notices found</h5>
                                <p class="text-muted">Create your first notice using the form above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Attachment</th>
                                            <th>Batch</th>
                                            <th>Posted By</th>
                                            <th>Created</th>
                                            <th>Views</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notices as $notice): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($notice['title']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars(substr(strip_tags($notice['content']), 0, 50)); ?>...
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $firstImageUrl = '';
                                                    if (!empty($notice['attachments'])) {
                                                        $atts = json_decode($notice['attachments'], true);
                                                        if (is_array($atts)) {
                                                            foreach ($atts as $att) {
                                                                $ext = strtolower(pathinfo($att['name'] ?? '', PATHINFO_EXTENSION));
                                                                if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $firstImageUrl = $att['url'] ?? ''; break; }
                                                            }
                                                        }
                                                    }
                                                    if ($firstImageUrl): ?>
                                                        <img src="<?php echo htmlspecialchars($firstImageUrl); ?>" class="img-thumbnail" style="width: 80px; height: 60px; object-fit: cover; cursor: pointer;" onclick="showNoticeImage('<?php echo htmlspecialchars($firstImageUrl, ENT_QUOTES); ?>')">
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($notice['batch']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($notice['batch']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">All</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($notice['posted_by_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($notice['created_at'])); ?></td>
                                                <td><?php echo $notice['views']; ?></td>
                                                <td>
                                                    <?php if ($notice['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($notice['expiry_date']): ?>
                                                        <?php if (strtotime($notice['expiry_date']) < time()): ?>
                                                            <span class="badge bg-warning">Expired</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="../notice.php?id=<?php echo $notice['id']; ?>" 
                                                           class="btn btn-outline-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit_notice.php?id=<?php echo $notice['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteNotice(<?php echo $notice['id']; ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showNoticeImage(url){
            var modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = '<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body p-0"><img src="'+url+'" class="img-fluid w-100"></div></div></div>';
            document.body.appendChild(modal);
            var bsModal = new bootstrap.Modal(modal);
            modal.addEventListener('hidden.bs.modal', function(){ modal.remove(); });
            bsModal.show();
        }
        function deleteNotice(noticeId) {
            if (confirm('Are you sure you want to delete this notice? This action cannot be undone.')) {
                fetch('../api/notice_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        notice_id: noticeId,
                        csrf_token: '<?php echo Auth::generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete notice'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the notice');
                });
            }
        }
    </script>
</body>
</html>


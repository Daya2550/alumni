<?php
/**
 * Edit Notice
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin', 'staff']);

$user = Auth::getCurrentUser();
$notice_id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

if (!$notice_id) {
    header('Location: notices.php');
    exit;
}

// Get notice details
$notice = db()->fetchOne(
    "SELECT * FROM notices WHERE id = ?",
    [$notice_id]
);

if (!$notice) {
    header('Location: notices.php');
    exit;
}

// Check if user can edit this notice
if ($user['role'] !== 'admin' && $notice['posted_by'] !== $user['id']) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = sanitizeInput($_POST['content'] ?? '');
        $batch = sanitizeInput($_POST['batch'] ?? '');
        $expiry_date = $_POST['expiry_date'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate required fields
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required';
        } else {
            try {
                // Handle file uploads
                $attachments = [];
                if ($notice['attachments']) {
                    $attachments = json_decode($notice['attachments'], true) ?: [];
                }
                
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
                
                // Handle file deletion
                if (isset($_POST['delete_files'])) {
                    $files_to_delete = $_POST['delete_files'];
                    foreach ($files_to_delete as $file_index) {
                        if (isset($attachments[$file_index])) {
                            $file_path = $attachments[$file_index]['path'];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                            unset($attachments[$file_index]);
                        }
                    }
                    $attachments = array_values($attachments); // Re-index array
                }
                
                // Update notice
                $attachments_json = !empty($attachments) ? json_encode($attachments) : null;
                $expiry_date_sql = !empty($expiry_date) ? $expiry_date : null;
                $batch_sql = !empty($batch) ? $batch : null;
                
                db()->execute(
                    "UPDATE notices SET title = ?, content = ?, batch = ?, expiry_date = ?, 
                     attachments = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                    [$title, $content, $batch_sql, $expiry_date_sql, $attachments_json, $is_active, $notice_id]
                );
                
                $message = 'Notice updated successfully!';
                
                // Refresh notice data
                $notice = db()->fetchOne(
                    "SELECT * FROM notices WHERE id = ?",
                    [$notice_id]
                );
                
            } catch (Exception $e) {
                $error = 'Error updating notice: ' . $e->getMessage();
            }
        }
    }
}

// Get current attachments
$attachments = [];
if ($notice['attachments']) {
    $attachments = json_decode($notice['attachments'], true) ?: [];
}

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
    <title>Edit Notice - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-edit text-primary"></i> Edit Notice</h1>
                    <div>
                        <a href="notices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Notices
                        </a>
                        <a href="../notice.php?id=<?php echo $notice['id']; ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye"></i> View Notice
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

                <!-- Edit Notice Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit"></i> Edit Notice</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($notice['title']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content *</label>
                                        <textarea class="form-control" id="content" name="content" rows="8" required><?php echo htmlspecialchars($notice['content']); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="batch" class="form-label">Target Batch</label>
                                        <select class="form-select" id="batch" name="batch">
                                            <option value="">All Batches</option>
                                            <?php foreach ($batches as $batch): ?>
                                                <option value="<?php echo htmlspecialchars($batch['batch']); ?>" 
                                                        <?php echo $notice['batch'] === $batch['batch'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($batch['batch']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                               value="<?php echo $notice['expiry_date'] ? date('Y-m-d', strtotime($notice['expiry_date'])) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                   <?php echo $notice['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">
                                                Active Notice
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="attachments" class="form-label">Add New Attachments</label>
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" 
                                               multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                                        <div class="form-text">Max file size: <?php echo round(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB per file</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Current Attachments -->
                            <?php if (!empty($attachments)): ?>
                                <div class="mb-4">
                                    <h6><i class="fas fa-paperclip"></i> Current Attachments</h6>
                                    <div class="row">
                                        <?php foreach ($attachments as $index => $attachment): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="card-title"><?php echo htmlspecialchars($attachment['name']); ?></h6>
                                                                <small class="text-muted">
                                                                    <?php echo formatFileSize($attachment['size']); ?>
                                                                </small>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       name="delete_files[]" value="<?php echo $index; ?>" 
                                                                       id="delete_<?php echo $index; ?>">
                                                                <label class="form-check-label" for="delete_<?php echo $index; ?>">
                                                                    Delete
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="../<?php echo htmlspecialchars($attachment['url']); ?>" 
                                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="fas fa-download"></i> View
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-end">
                                <a href="notices.php" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Notice
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        return number_format($bytes / 1024 / 1024, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024 / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>


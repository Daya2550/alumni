<?php
/**
 * Admin File Cleanup Management Interface
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

Auth::requireRole('admin');

$user = Auth::getCurrentUser();
$message = '';
$error = '';

// Handle cleanup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'process_cleanup_log':
                    $files_deleted = FileCleanup::processCleanupLog();
                    $message = "Processed cleanup log. Deleted {$files_deleted} files.";
                    break;
                    
                case 'cleanup_user_files':
                    $user_id = trim($_POST['user_id'] ?? '');
                    if ($user_id) {
                        $files_deleted = FileCleanup::cleanupUserFiles($user_id);
                        $message = "Cleaned up {$files_deleted} files for user {$user_id}.";
                    } else {
                        $error = 'User ID is required.';
                    }
                    break;
                    
                case 'run_full_cleanup':
                    // This would run the full cleanup script
                    $output = [];
                    $return_code = 0;
                    exec('php ' . __DIR__ . '/../../cleanup_files.php 2>&1', $output, $return_code);
                    
                    if ($return_code === 0) {
                        $message = 'Full cleanup completed successfully. Output: ' . implode('\n', $output);
                    } else {
                        $error = 'Cleanup failed. Output: ' . implode('\n', $output);
                    }
                    break;
                    
                default:
                    $error = 'Invalid action.';
            }
        } catch (Exception $e) {
            $error = 'Operation failed: ' . $e->getMessage();
            error_log('Admin file cleanup error: ' . $e->getMessage());
        }
    }
}

// Get cleanup statistics
$stats = FileCleanup::getCleanupStats();

// Get recent cleanup log entries
$recent_cleanup = db()->fetchAll(
    "SELECT file_path, deleted_at FROM attachment_cleanup_log ORDER BY deleted_at DESC LIMIT 20"
);

// Get file statistics by type
$file_stats = [
    'events' => db()->fetchOne("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM event_attachments"),
    'jobs' => db()->fetchOne("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM job_attachments"),
    'projects' => db()->fetchOne("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM project_attachments"),
    'gallery' => db()->fetchOne("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM gallery_media"),
    'messages' => db()->fetchOne("SELECT COUNT(*) as count FROM messages WHERE file_path IS NOT NULL")
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Cleanup Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="fas fa-broom text-primary me-2"></i>
                File Cleanup Management
            </h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Admin
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Pending Cleanup</h6>
                                <h3 class="mb-0"><?php echo $stats['pending_cleanup']; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Upload Size</h6>
                                <h3 class="mb-0"><?php echo formatBytes($stats['total_upload_size']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-hdd fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Upload Directories</h6>
                                <h3 class="mb-0"><?php echo count($stats['upload_directories']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-folder fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">System Status</h6>
                                <h3 class="mb-0">
                                    <?php echo $stats['pending_cleanup'] > 100 ? 'Needs Attention' : 'Good'; ?>
                                </h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-<?php echo $stats['pending_cleanup'] > 100 ? 'exclamation-triangle' : 'check-circle'; ?> fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="process_cleanup_log">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Process cleanup log now?')">
                                <i class="fas fa-play me-1"></i>Process Cleanup Log
                            </button>
                        </form>
                        <small class="text-muted">Process files marked for deletion</small>
                    </div>
                    <div class="col-md-4">
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="run_full_cleanup">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Run full cleanup? This may take a while.')">
                                <i class="fas fa-broom me-1"></i>Full Cleanup
                            </button>
                        </form>
                        <small class="text-muted">Run complete cleanup process</small>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-info d-grid" data-bs-toggle="modal" data-bs-target="#userCleanupModal">
                            <i class="fas fa-user-times me-1"></i>Cleanup User Files
                        </button>
                        <small class="text-muted">Clean up files for specific user</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- File Statistics by Type -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>File Statistics by Type</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($file_stats as $type => $stats): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="border rounded p-3">
                            <h6 class="text-capitalize"><?php echo $type; ?></h6>
                            <div class="d-flex justify-content-between">
                                <span>Files:</span>
                                <strong><?php echo number_format($stats['count']); ?></strong>
                            </div>
                            <?php if (isset($stats['total_size'])): ?>
                            <div class="d-flex justify-content-between">
                                <span>Size:</span>
                                <strong><?php echo formatBytes($stats['total_size']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Upload Directory Structure -->
        <?php if (!empty($stats['upload_directories'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-folder-tree me-2"></i>Upload Directory Structure</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats['upload_directories'] as $dir => $info): ?>
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            <div>
                                <i class="fas fa-folder text-warning me-2"></i>
                                <strong><?php echo htmlspecialchars($dir); ?>/</strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block"><?php echo $info['file_count']; ?> files</small>
                                <small class="text-muted"><?php echo formatBytes($info['size']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Cleanup Log -->
        <?php if (!empty($recent_cleanup)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Cleanup Log</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>File Path</th>
                                <th>Marked for Deletion</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_cleanup as $item): ?>
                            <tr>
                                <td>
                                    <code class="small"><?php echo htmlspecialchars($item['file_path']); ?></code>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('M j, Y H:i', strtotime($item['deleted_at'])); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $is_old = strtotime($item['deleted_at']) < strtotime('-1 hour');
                                    echo $is_old ? 
                                        '<span class="badge bg-success">Ready for cleanup</span>' : 
                                        '<span class="badge bg-warning">Pending</span>';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- User Cleanup Modal -->
    <div class="modal fade" id="userCleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Cleanup User Files</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cleanup_user_files">
                        
                        <div class="mb-3">
                            <label for="user_id" class="form-label">User ID</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" required 
                                   placeholder="Enter user UUID">
                            <div class="form-text">
                                This will delete all files associated with the user (profile photo, uploads, etc.)
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action cannot be undone. All files associated with this user will be permanently deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User Files</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh statistics every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

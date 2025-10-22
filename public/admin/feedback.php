<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

$status = sanitizeInput($_GET['status'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');

$where = [];
$params = [];
if ($status) { $where[] = 'f.status = ?'; $params[] = $status; }
if ($category) { $where[] = 'f.category = ?'; $params[] = $category; }
if ($date_from) { $where[] = 'DATE(f.created_at) >= ?'; $params[] = $date_from; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = db()->fetchAll("\nSELECT f.*, u.name as user_name\nFROM feedback f\nLEFT JOIN users u ON u.id = f.user_id\n$whereSql\nORDER BY f.created_at DESC\n", $params);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="fas fa-comment-dots text-primary"></i> Feedback Management</h1>
                <p class="text-muted mb-0">Review and manage user feedback submissions</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../feedback.php" class="btn btn-outline-primary">
                    <i class="fas fa-plus"></i> Submit Feedback
                </a>
                <button class="btn btn-outline-secondary" onclick="exportFeedback()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <?php 
            $total_feedback = db()->fetchOne("SELECT COUNT(*) as count FROM feedback")['count'];
            $new_feedback = db()->fetchOne("SELECT COUNT(*) as count FROM feedback WHERE status = 'new'")['count'];
            $resolved_feedback = db()->fetchOne("SELECT COUNT(*) as count FROM feedback WHERE status = 'resolved'")['count'];
            $this_month = db()->fetchOne("SELECT COUNT(*) as count FROM feedback WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")['count'];
            ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-comment-dots fa-2x text-primary me-3"></i>
                        <div>
                            <div class="text-muted">Total Feedback</div>
                            <div class="h5 mb-0"><?php echo $total_feedback; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-clock fa-2x text-warning me-3"></i>
                        <div>
                            <div class="text-muted">New</div>
                            <div class="h5 mb-0"><?php echo $new_feedback; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                        <div>
                            <div class="text-muted">Resolved</div>
                            <div class="h5 mb-0"><?php echo $resolved_feedback; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-calendar fa-2x text-info me-3"></i>
                        <div>
                            <div class="text-muted">This Month</div>
                            <div class="h5 mb-0"><?php echo $this_month; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
            </div>
            <div class="card-body">
                <form class="row g-3" method="GET">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <?php foreach (['new','in_review','resolved','closed'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $status===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php 
                            $categories = db()->fetchAll("SELECT DISTINCT category FROM feedback ORDER BY category");
                            foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($_GET['category'] ?? '') === $cat['category'] ? 'selected' : ''; ?>><?php echo ucfirst(htmlspecialchars($cat['category'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Feedback List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Feedback Submissions</h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary"><?php echo count($rows); ?> items</span>
                    <?php if (!empty($rows)): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAllFeedback()">
                        <i class="fas fa-trash"></i> Delete All
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                        <?php if (empty($rows)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No feedback found</h5>
                        <p class="text-muted">No feedback submissions match your current filters.</p>
                    </div>
                        <?php else: ?>
                    <div class="list-group list-group-flush">
                            <?php foreach ($rows as $r): ?>
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <?php
                                                $status_colors = [
                                                    'new' => 'warning',
                                                    'in_review' => 'info', 
                                                    'resolved' => 'success',
                                                    'closed' => 'secondary'
                                                ];
                                                $status_icons = [
                                                    'new' => 'fas fa-clock',
                                                    'in_review' => 'fas fa-eye',
                                                    'resolved' => 'fas fa-check-circle',
                                                    'closed' => 'fas fa-times-circle'
                                                ];
                                                $color = $status_colors[$r['status']] ?? 'secondary';
                                                $icon = $status_icons[$r['status']] ?? 'fas fa-circle';
                                                ?>
                                                <i class="<?php echo $icon; ?> fa-2x text-<?php echo $color; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="../profile.php?id=<?php echo urlencode($r['user_id']); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($r['user_name'] ?: $r['user_id']); ?>
                                                    </a>
                                                    <span class="badge bg-<?php echo $color; ?> ms-2"><?php echo ucfirst($r['status']); ?></span>
                                                </h6>
                                                <h5 class="mb-2"><?php echo htmlspecialchars($r['subject']); ?></h5>
                                                <p class="text-muted mb-2"><?php echo htmlspecialchars(mb_strimwidth($r['message'], 0, 200, '...')); ?></p>
                                                <div class="d-flex align-items-center text-muted small">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <span class="me-3"><?php echo ucfirst($r['category']); ?></span>
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <span><?php echo date('M j, Y g:i A', strtotime($r['created_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-flex flex-column gap-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewFeedback('<?php echo $r['id']; ?>')">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-success" onclick="updateStatus('<?php echo $r['id']; ?>', 'resolved')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="updateStatus('<?php echo $r['id']; ?>', 'in_review')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="updateStatus('<?php echo $r['id']; ?>', 'closed')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" title="Delete" onclick="deleteFeedback('<?php echo $r['id']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
            </div>
        </div>
    </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeedback(feedbackId) {
            // Open feedback details in modal or new page
            window.open('feedback_details.php?id=' + feedbackId, '_blank');
        }
        
        function updateStatus(feedbackId, newStatus) {
            if (!confirm('Update feedback status to ' + newStatus + '?')) return;
            
            fetch('feedback_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(feedbackId) + '&status=' + encodeURIComponent(newStatus)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error updating status');
                console.error(error);
            });
        }
        
        function deleteFeedback(feedbackId) {
            if (!confirm('Delete this feedback permanently?')) return;
            fetch('feedback_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(feedbackId)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { location.reload(); }
                else { alert(data.message || 'Failed to delete'); }
            })
            .catch(() => alert('Error deleting'));
        }

        function deleteAllFeedback() {
            if (!confirm('Delete ALL feedback records? This cannot be undone.')) return;
            fetch('feedback_delete_all.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) { location.reload(); }
                else { alert(data.message || 'Failed to delete all'); }
            })
            .catch(() => alert('Error deleting all feedback'));
        }
        
        function exportFeedback() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('feedback_export.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>



<?php
/**
 * Noticeboard - List all notices
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$batch_filter = sanitizeInput($_GET['batch'] ?? '');

// Build query
$where_conditions = ['n.is_active = 1'];
$params = [];

// Filter by batch if specified and user has access
if ($batch_filter && Auth::canAccessBatch($batch_filter)) {
    $where_conditions[] = 'n.batch = ? OR n.batch IS NULL';
    $params[] = $batch_filter;
} elseif (!$batch_filter && $user['batch']) {
    // Show notices for user's batch and general notices
    $where_conditions[] = '(n.batch = ? OR n.batch IS NULL)';
    $params[] = $user['batch'];
}

// Search functionality
if ($search) {
    $where_conditions[] = '(n.title LIKE ? OR n.content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get notices
$notices_query = "
    SELECT 
        n.*, 
        u.name AS posted_by_name,
        (SELECT COUNT(*) FROM notice_reads nr WHERE nr.notice_id = n.id) AS read_count,
        EXISTS(
            SELECT 1 FROM notice_reads ur 
            WHERE ur.notice_id = n.id AND ur.user_id = ?
        ) AS is_read
    FROM notices n
    JOIN users u ON n.posted_by = u.id
    $where_clause
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
";

$params = array_merge([$user['id']], $params, [$limit, $offset]);
$notices = db()->fetchAll($notices_query, $params);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM notices n 
    $where_clause
";
$count_params = array_slice($params, 1, -2); // Remove user_id, limit, offset
$total_notices = db()->fetchOne($count_query, $count_params)['total'];
$total_pages = ceil($total_notices / $limit);

// Get available batches for filter
$batches = db()->fetchAll(
    "SELECT DISTINCT batch FROM users WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bullhorn text-primary"></i> Noticeboard</h1>
                    <?php if (Auth::hasRole('staff')): ?>
                        <a href="admin/notices.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Post Notice
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search Notices</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search notices...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="batch" class="form-label">Filter by Batch</label>
                                <select class="form-select" id="batch" name="batch">
                                    <option value="">All Batches</option>
                                    <?php foreach ($batches as $batch): ?>
                                        <option value="<?php echo htmlspecialchars($batch['batch']); ?>" 
                                                <?php echo $batch_filter === $batch['batch'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($batch['batch']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notices List -->
                <?php if (empty($notices)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-bullhorn text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-muted">No notices found</h3>
                            <p class="text-muted">There are no notices matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($notices as $notice): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 notice-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title">
                                                <a href="notice.php?id=<?php echo $notice['id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($notice['title']); ?>
                                                </a>
                                            </h5>
                                            <?php if (!$notice['is_read']): ?>
                                                <span class="badge bg-primary">New</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="card-text text-muted">
                                            <?php echo htmlspecialchars(substr(strip_tags($notice['content']), 0, 100)); ?>
                                            <?php if (strlen(strip_tags($notice['content'])) > 100): ?>...<?php endif; ?>
                                        </p>
                                        
                                        <?php if ($notice['batch']): ?>
                                            <span class="badge bg-info mb-2"><?php echo htmlspecialchars($notice['batch']); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if ($notice['expiry_date']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> 
                                                    Expires: <?php echo date('M j, Y', strtotime($notice['expiry_date'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($notice['posted_by_name']); ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $notice['read_count']; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('M j, Y', strtotime($notice['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Notices pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&batch=<?php echo urlencode($batch_filter); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&batch=<?php echo urlencode($batch_filter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&batch=<?php echo urlencode($batch_filter); ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

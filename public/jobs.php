<?php
/**
 * Job Opportunities - List all job postings
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$mine = isset($_GET['mine']) && $user ? true : false;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$location = sanitizeInput($_GET['location'] ?? '');
$salary_min = intval($_GET['salary_min'] ?? 0);

// Build query
$where_conditions = ["jo.status = 'approved'", "jo.deadline >= CURDATE()"];
$params = [];

// Location filter
if ($location) {
    $where_conditions[] = 'jo.location LIKE ?';
    $params[] = "%$location%";
}

// Salary filter
if ($salary_min > 0) {
    $where_conditions[] = 'jo.salary_range LIKE ?';
    $params[] = "%$salary_min%";
}

// Search functionality
if ($search) {
    $where_conditions[] = '(jo.title LIKE ? OR jo.description LIKE ? OR jo.company LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get job opportunities
$jobs_query = "
    SELECT 
        jo.id,
        jo.title,
        jo.company,
        jo.company_logo,
        jo.description,
        jo.location,
        jo.salary_range,
        jo.deadline,
        jo.skills,
        jo.created_at,
        u.name AS posted_by_name,
        (
            SELECT COUNT(*) 
            FROM job_applications ja 
            WHERE ja.job_id = jo.id
        ) AS applications_count,
        (
            SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
            FROM job_applications ja2 
            WHERE ja2.job_id = jo.id AND ja2.user_id = ?
        ) AS user_applied
    FROM job_opportunities jo
    JOIN users u ON jo.posted_by = u.id
    $where_clause
    ORDER BY jo.created_at DESC
    LIMIT ? OFFSET ?
";

$params = array_merge([$user['id']], $params, [$limit, $offset]);
$jobs = db()->fetchAll($jobs_query, $params);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM job_opportunities jo 
    $where_clause
";
$count_params = array_slice($params, 1, -2); // Remove user_id, limit, offset
$total_jobs = db()->fetchOne($count_query, $count_params)['total'];
$total_pages = ceil($total_jobs / $limit);

// Get unique locations for filter
$locations = db()->fetchAll(
    "SELECT DISTINCT location FROM job_opportunities WHERE location IS NOT NULL AND location != '' ORDER BY location"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Opportunities - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-briefcase text-warning"></i> Job Opportunities</h1>
                    <div class="d-flex gap-2">
                        <?php if (Auth::hasAnyRole(['student','alumni','staff','admin'])): ?>
                            <a href="submit_job.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Submit Job
                            </a>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
                            <a href="admin/jobs.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Review Submissions
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Jobs</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search jobs, companies...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label">Location</label>
                                <select class="form-select" id="location" name="location">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo htmlspecialchars($loc['location']); ?>" 
                                                <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="salary_min" class="form-label">Minimum Salary</label>
                                <input type="number" class="form-control" id="salary_min" name="salary_min" 
                                       value="<?php echo $salary_min; ?>" placeholder="e.g., 50000">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Job Listings -->
                <?php if (empty($jobs)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-briefcase text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-muted">No job opportunities found</h3>
                            <p class="text-muted">There are no job postings matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100 job-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <a href="job.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($job['title']); ?>
                                                    </a>
                                                </h5>
                                                <h6 class="text-muted mb-0">
                                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company']); ?>
                                                </h6>
                                            </div>
                                            <?php if ($job['company_logo']): ?>
                                                <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                     class="rounded" width="60" height="60" style="object-fit: cover;">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($job['location']): ?>
                                            <p class="mb-2">
                                                <i class="fas fa-map-marker-alt text-muted"></i> 
                                                <?php echo htmlspecialchars($job['location']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($job['salary_range']): ?>
                                            <p class="mb-2">
                                                <i class="fas fa-dollar-sign text-success"></i> 
                                                <strong><?php echo htmlspecialchars($job['salary_range']); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <p class="card-text">
                                            <?php echo htmlspecialchars(substr(strip_tags($job['description']), 0, 150)); ?>
                                            <?php if (strlen(strip_tags($job['description'])) > 150): ?>...<?php endif; ?>
                                        </p>
                                        
                                        <?php if ($job['skills']): ?>
                                            <div class="mb-3">
                                                <?php 
                                                $skills = explode(',', $job['skills']);
                                                foreach (array_slice($skills, 0, 3) as $skill): 
                                                ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($skills) > 3): ?>
                                                    <span class="badge bg-light text-dark">+<?php echo count($skills) - 3; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-muted">
                                                <small>
                                                    <i class="fas fa-calendar"></i> 
                                                    Deadline: <?php echo date('M j, Y', strtotime($job['deadline'])); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info me-2">
                                                    <?php echo $job['applications_count']; ?> applications
                                                </span>
                                                
                                                <?php if ($job['user_applied']): ?>
                                                    <span class="badge bg-success">Applied</span>
                                                <?php else: ?>
                                                    <a href="job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">
                                                        View & Apply
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (Auth::hasAnyRole(['staff','admin']) || $job['posted_by_name'] === $user['name']): ?>
                                                    <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 delete-job-btn" data-job-id="<?php echo $job['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Jobs pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&salary_min=<?php echo $salary_min; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&salary_min=<?php echo $salary_min; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&salary_min=<?php echo $salary_min; ?>">
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
    <script>
        document.querySelectorAll('.delete-job-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const jobId = this.dataset.jobId;
                if (!confirm('Delete this job posting? This cannot be undone.')) return;
                fetch('api/job_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        job_id: jobId,
                        csrf_token: '<?php echo Auth::generateCSRFToken(); ?>'
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete');
                    }
                }).catch(() => alert('Network error'));
            });
        });
    </script>
</body>
</html>

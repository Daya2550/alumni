<?php
/**
 * Members Directory - Browse alumni and students
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20; // More items for directory
$offset = ($page - 1) * $limit;

// Search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$batch_filter = sanitizeInput($_GET['batch'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$company_filter = sanitizeInput($_GET['company'] ?? '');
$department_filter = sanitizeInput($_GET['department'] ?? '');

// Build query
$where_conditions = ["u.is_active = 1", "u.role IN ('student','alumni','staff')"]; // Hide admins from directory by default
$params = [];

// Role filter
if ($role_filter && in_array($role_filter, ['student', 'alumni', 'staff'])) {
    $where_conditions[] = 'u.role = ?';
    $params[] = $role_filter;
}

// Batch filter
if ($batch_filter) {
    $where_conditions[] = 'u.batch = ?';
    $params[] = $batch_filter;
}

// Company filter
if ($company_filter) {
    $where_conditions[] = 'u.company LIKE ?';
    $params[] = "%$company_filter%";
}

// Department filter
if ($department_filter) {
    $where_conditions[] = 'u.department = ?';
    $params[] = $department_filter;
}

// Search functionality
if ($search) {
    $where_conditions[] = '(u.name LIKE ? OR u.job_title LIKE ? OR u.company LIKE ? OR u.skills LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Enforce profile visibility for non-admin/staff: hide users who set profile_visibility=private
if (!Auth::hasAnyRole(['admin','staff'])) {
    // Only include users where privacy_settings->profile_visibility is not 'private'
    // MySQL JSON safe check: handle null or non-JSON by treating as default 'public'
    $where_clause .= " AND (JSON_EXTRACT(COALESCE(u.privacy_settings, '{}'), '$.profile_visibility') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(COALESCE(u.privacy_settings, '{}'), '$.profile_visibility')) <> 'private')";
}

// Get users
$users_query = "
    SELECT u.id, u.name, u.email, u.role, u.batch, u.department, u.profile_photo, u.bio, 
           u.job_title, u.company, u.skills, u.privacy_settings
    FROM users u 
    $where_clause
    ORDER BY u.name ASC 
    LIMIT ? OFFSET ?
";

$params = array_merge($params, [$limit, $offset]);
$users = db()->fetchAll($users_query, $params);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM users u 
    $where_clause
";
$total_users = db()->fetchOne($count_query, array_slice($params, 0, -2))['total'];
$total_pages = ceil($total_users / $limit);

// Get available batches for filter
$batches = db()->fetchAll(
    "SELECT DISTINCT batch FROM users WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC"
);

// Get companies for filter
$companies = db()->fetchAll(
    "SELECT DISTINCT company FROM users WHERE company IS NOT NULL AND company != '' ORDER BY company"
);

// Get departments for filter
$departments = db()->fetchAll(
    "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Directory - <?php echo APP_NAME; ?></title>
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
                    <h1><i class="fas fa-users text-primary"></i> Members Directory</h1>
                    <div class="text-muted">
                        <?php echo $total_users; ?> members found (students, alumni, staff)
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search Members</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search name, job, company...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="">All Roles</option>
                                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                                    <option value="alumni" <?php echo $role_filter === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                                    <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="batch" class="form-label">Batch</label>
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
                            <div class="col-md-3">
                                <label for="company" class="form-label">Company</label>
                                <select class="form-select" id="company" name="company">
                                    <option value="">All Companies</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo htmlspecialchars($company['company']); ?>" 
                                                <?php echo $company_filter === $company['company'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['company']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                                <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department']); ?>
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

                <!-- Members Grid -->
                <?php if (empty($users)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-users text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-muted">No members found</h3>
                            <p class="text-muted">There are no members matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($users as $member): ?>
                            <div class="col-xxl-3 col-lg-4 col-md-6 mb-3">
                                <div class="card h-100 shadow-sm directory-card">
                                    <?php if ($member['profile_photo']): ?>
                                        <img class="card-img-top" src="<?php echo htmlspecialchars($member['profile_photo']); ?>" alt="Profile photo" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 140px;">
                                            <i class="fas fa-user-circle text-muted" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body py-2 px-3">
                                        <h5 class="card-title mb-1" style="font-size: 1rem;">
                                            <a href="profile.php?id=<?php echo $member['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </a>
                                        </h5>
                                        <?php if (!empty($member['bio'])): ?>
                                            <p class="card-text text-muted small mb-0">
                                                <?php echo htmlspecialchars(mb_strimwidth($member['bio'], 0, 80, '...')); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item py-2 px-3">
                                            <?php $role_colors = ['student'=>'primary','alumni'=>'success','staff'=>'warning']; ?>
                                            <span class="badge bg-<?php echo $role_colors[$member['role']] ?? 'secondary'; ?> me-2"><?php echo ucfirst($member['role']); ?></span>
                                            <?php if ($member['batch']): ?><span class="badge bg-info"><?php echo htmlspecialchars($member['batch']); ?></span><?php endif; ?>
                                            <?php if (!empty($member['department'])): ?><span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($member['department']); ?></span><?php endif; ?>
                                        </li>
                                        <?php if ($member['job_title']): ?>
                                            <li class="list-group-item py-2 px-3 small"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($member['job_title']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($member['company']): ?>
                                            <li class="list-group-item py-2 px-3 small"><i class="fas fa-building"></i> <?php echo htmlspecialchars($member['company']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($member['skills']): ?>
                                            <li class="list-group-item py-2 px-3">
                                                <?php $skills = array_slice(array_map('trim', explode(',', $member['skills'])), 0, 3); ?>
                                                <?php foreach ($skills as $skill): ?>
                                                    <span class="badge bg-light text-dark me-1 mb-1" style="font-size: 0.7rem;">&middot; <?php echo htmlspecialchars($skill); ?></span>
                                                <?php endforeach; ?>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                    <div class="card-body d-flex justify-content-between py-2 px-3">
                                        <a href="profile.php?id=<?php echo $member['id']; ?>" class="card-link small"><i class="fas fa-user"></i> Profile</a>
                                        <?php if ($member['id'] !== $user['id']): ?>
                                            <a href="message.php?user=<?php echo $member['id']; ?>" class="card-link small"><i class="fas fa-comments"></i> Message</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Members pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&batch=<?php echo urlencode($batch_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&batch=<?php echo urlencode($batch_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&batch=<?php echo urlencode($batch_filter); ?>&company=<?php echo urlencode($company_filter); ?>">
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

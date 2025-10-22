<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

// Filters
$status = sanitizeInput($_GET['status'] ?? '');
$approval = sanitizeInput($_GET['approval'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');
$tag = sanitizeInput($_GET['tag'] ?? '');

$where = ['p.approval_status IN ("approved", "published")'];
$params = [];

if ($status) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

if ($approval) {
    $where[] = 'p.approval_status = ?';
    $params[] = $approval;
}

if ($search) {
    $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($tag) {
    $where[] = 'p.tags LIKE ?';
    $params[] = "%$tag%";
}

// Only show public projects to students/alumni, staff/admin can see all
if (!Auth::hasAnyRole(['admin', 'staff'])) {
    $where[] = 'p.visibility = "public"';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$projects = db()->fetchAll(
    "SELECT p.*, u.name as owner_name, u.profile_photo as owner_photo,
            COUNT(DISTINCT CASE WHEN pa.is_approved = 1 THEN pa.id END) as attachment_count,
            COUNT(DISTINCT CASE WHEN pc.is_approved = 1 THEN pc.id END) as contribution_count,
            COUNT(DISTINCT ph.id) as helper_count
     FROM projects p
     LEFT JOIN users u ON p.owner_id = u.id
     LEFT JOIN project_attachments pa ON p.id = pa.project_id
     LEFT JOIN project_contributions pc ON p.id = pc.project_id
     LEFT JOIN project_helpers ph ON p.id = ph.project_id AND ph.status = 'accepted'
     $whereSql
     GROUP BY p.id
     ORDER BY p.created_at DESC",
    $params
);

// Get unique tags for filter
$all_tags = db()->fetchAll("SELECT DISTINCT tags FROM projects WHERE tags IS NOT NULL AND tags != ''");
$tag_list = [];
foreach ($all_tags as $t) {
    if ($t['tags']) {
        $tag_list = array_merge($tag_list, explode(',', $t['tags']));
    }
}
$tag_list = array_unique(array_map('trim', $tag_list));
sort($tag_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Hub - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="fas fa-code text-primary"></i> Projects Hub</h1>
                <p class="text-muted mb-0">Discover and collaborate on amazing projects</p>
            </div>
            <div class="d-flex gap-2">
                <a href="project_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Project
                </a>
                <?php if (Auth::hasAnyRole(['admin', 'staff'])): ?>
                <a href="admin/projects.php" class="btn btn-outline-secondary">
                    <i class="fas fa-cog"></i> Manage
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <?php
            $total_projects = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE approval_status IN ('approved', 'published')")['count'];
            $in_progress = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'in_progress' AND approval_status IN ('approved', 'published')")['count'];
            $completed = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'completed' AND approval_status IN ('approved', 'published')")['count'];
            $my_projects = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE owner_id = ?", [$user['id']])['count'];
            ?>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-code fa-2x text-primary me-3"></i>
                        <div>
                            <div class="text-muted">Total Projects</div>
                            <div class="h5 mb-0"><?php echo $total_projects; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-tasks fa-2x text-warning me-3"></i>
                        <div>
                            <div class="text-muted">In Progress</div>
                            <div class="h5 mb-0"><?php echo $in_progress; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                        <div>
                            <div class="text-muted">Completed</div>
                            <div class="h5 mb-0"><?php echo $completed; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-user fa-2x text-info me-3"></i>
                        <div>
                            <div class="text-muted">My Projects</div>
                            <div class="h5 mb-0"><?php echo $my_projects; ?></div>
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
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tag</label>
                        <select class="form-select" name="tag">
                            <option value="">All Tags</option>
                            <?php foreach ($tag_list as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $tag === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search projects...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="row g-4">
            <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-code fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No projects found</h5>
                            <p class="text-muted">Try adjusting your filters or create a new project.</p>
                            <a href="project_create.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create First Project
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 project-card">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">
                                            <a href="project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($project['title']); ?>
                                            </a>
                                        </h5>
                                        <div class="d-flex align-items-center mb-2">
                                            <a href="profile.php?id=<?php echo $project['owner_id']; ?>" class="text-decoration-none">
                                                <?php if ($project['owner_photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($project['owner_photo']); ?>" class="rounded-circle me-2" width="24" height="24">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle me-2"></i>
                                                <?php endif; ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($project['owner_name']); ?></small>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $project['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?php echo htmlspecialchars(mb_strimwidth($project['description'], 0, 120, '...')); ?>
                                </p>
                                
                                <?php if ($project['tags']): ?>
                                    <div class="mb-3">
                                        <?php foreach (explode(',', $project['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <div>
                                        <i class="fas fa-paperclip me-1"></i> <?php echo $project['attachment_count']; ?>
                                        <i class="fas fa-comments me-2 ms-2"></i> <?php echo $project['contribution_count']; ?>
                                        <i class="fas fa-users me-1 ms-2"></i> <?php echo $project['helper_count']; ?>
                                    </div>
                                    <small><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex gap-2">
                                    <a href="project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary flex-fill">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($project['github_url']): ?>
                                        <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                            <i class="fab fa-github"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="message.php?user=<?php echo $project['owner_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-comment"></i>
                                    </a>
                                    <?php if ($project['owner_id'] === $user['id'] || Auth::hasAnyRole(['admin','staff'])): ?>
                                        <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

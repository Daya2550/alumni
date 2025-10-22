<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

$user = Auth::getCurrentUser();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $project_id = sanitizeInput($_POST['project_id'] ?? '');
    
    if ($project_id) {
        try {
            switch ($action) {
                case 'approve':
                    db()->execute("UPDATE projects SET approval_status = 'published', published_at = NOW() WHERE id = ?", [$project_id]);
                    // Notify owner on approval
                    require_once __DIR__ . '/../../includes/notifications.php';
                    $row = db()->fetchOne("SELECT owner_id, title FROM projects WHERE id = ?", [$project_id]);
                    if ($row && $row['owner_id']) {
                        Notifications::notifyUsers([$row['owner_id']], 'Project approved', 'Your project "' . ($row['title'] ?? '') . '" was approved.', 'success', 'project', $project_id);
                    }
                    $success = 'Project approved and published';
                    break;
                case 'reject':
                    db()->execute("UPDATE projects SET approval_status = 'archived' WHERE id = ?", [$project_id]);
                    $success = 'Project rejected';
                    break;
                case 'delete':
                    db()->execute("DELETE FROM projects WHERE id = ?", [$project_id]);
                    $success = 'Project deleted';
                    break;
            }
        } catch (Exception $e) {
            error_log("Project action failed: " . $e->getMessage() . " | Action: " . $action . " | Project ID: " . $project_id);
            $error = 'Action failed: ' . $e->getMessage();
        }
    }
}

// Filters
$status = sanitizeInput($_GET['status'] ?? '');
$approval = sanitizeInput($_GET['approval'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

$where = [];
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
    $where[] = '(p.title LIKE ? OR p.description LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $projects = db()->fetchAll(
        "SELECT p.*, u.name as owner_name, u.email as owner_email,
                COUNT(DISTINCT pa.id) as attachment_count,
                COUNT(DISTINCT pc.id) as contribution_count,
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
} catch (Exception $e) {
    error_log("Failed to fetch projects: " . $e->getMessage());
    $projects = [];
    $error = 'Failed to load projects: ' . $e->getMessage();
}

// Statistics
try {
    $total_projects = db()->fetchOne("SELECT COUNT(*) as count FROM projects")['count'] ?? 0;
    $pending_approval = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE approval_status = 'pending'")['count'] ?? 0;
    $published_projects = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE approval_status = 'published'")['count'] ?? 0;
    $in_progress = db()->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'in_progress'")['count'] ?? 0;
} catch (Exception $e) {
    error_log("Failed to fetch statistics: " . $e->getMessage());
    $total_projects = $pending_approval = $published_projects = $in_progress = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="fas fa-code text-primary"></i> Projects Management</h1>
                <p class="text-muted mb-0">Manage and moderate all projects</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../projects.php" class="btn btn-outline-primary">
                    <i class="fas fa-eye"></i> View Public
                </a>
                <a href="project_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Project
                </a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
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
                        <i class="fas fa-clock fa-2x text-warning me-3"></i>
                        <div>
                            <div class="text-muted">Pending Approval</div>
                            <div class="h5 mb-0"><?php echo $pending_approval; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x text-success me-3"></i>
                        <div>
                            <div class="text-muted">Published</div>
                            <div class="h5 mb-0"><?php echo $published_projects; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-tasks fa-2x text-info me-3"></i>
                        <div>
                            <div class="text-muted">In Progress</div>
                            <div class="h5 mb-0"><?php echo $in_progress; ?></div>
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
                        <label class="form-label">Approval Status</label>
                        <select class="form-select" name="approval">
                            <option value="">All Approval</option>
                            <option value="pending" <?php echo $approval === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $approval === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="published" <?php echo $approval === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="archived" <?php echo $approval === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search projects, owners...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> All Projects</h5>
                <span class="badge bg-primary"><?php echo count($projects); ?> projects</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($projects)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-code fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No projects found</h5>
                        <p class="text-muted">No projects match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th>Approval</th>
                                    <th>Stats</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="../project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($project['title'] ?? ''); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($project['description'] ?? '', 0, 80, '...')); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($project['owner_name'] ?? ''); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($project['owner_email'] ?? ''); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $project['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $approval_colors = [
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'published' => 'success',
                                                'archived' => 'secondary'
                                            ];
                                            $color = $approval_colors[$project['approval_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($project['approval_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small text-muted">
                                                <i class="fas fa-paperclip me-1"></i> <?php echo $project['attachment_count']; ?>
                                                <i class="fas fa-comments me-2 ms-2"></i> <?php echo $project['contribution_count']; ?>
                                                <i class="fas fa-users me-1 ms-2"></i> <?php echo $project['helper_count']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($project['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="../project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="../project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($project['approval_status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-outline-success" onclick="approveProject('<?php echo $project['id']; ?>')" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="rejectProject('<?php echo $project['id']; ?>')" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteProject('<?php echo $project['id']; ?>')" title="Delete">
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
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveProject(projectId) {
            if (!confirm('Approve and publish this project?')) return;
            submitAction('approve', projectId);
        }
        
        function rejectProject(projectId) {
            if (!confirm('Reject this project? It will be archived.')) return;
            submitAction('reject', projectId);
        }
        
        function deleteProject(projectId) {
            if (!confirm('Delete this project permanently? This cannot be undone.')) return;
            submitAction('delete', projectId);
        }
        
        function submitAction(action, projectId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                <input type="hidden" name="project_id" value="${projectId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>

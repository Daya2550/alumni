<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$project_id = sanitizeInput($_GET['id'] ?? '');
if (!$project_id) {
    header('Location: projects.php');
    exit;
}

// Get project details
$project = db()->fetchOne(
    "SELECT p.*, u.name as owner_name, u.email as owner_email, u.profile_photo as owner_photo, u.batch as owner_batch
     FROM projects p
     LEFT JOIN users u ON p.owner_id = u.id
     WHERE p.id = ?",
    [$project_id]
);

if (!$project) {
    header('HTTP/1.1 404 Not Found');
    echo 'Project not found';
    exit;
}

// Only allow non-owners to view approved/published projects
$is_owner = $project['owner_id'] === $user['id'];
$is_privileged = Auth::hasAnyRole(['admin', 'staff']);
if (!$is_owner && !$is_privileged && !in_array($project['approval_status'], ['approved','published'], true)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'This project is not published yet.';
    exit;
}

// Check visibility permissions
if ($project['visibility'] === 'private' && $project['owner_id'] !== $user['id'] && !Auth::hasAnyRole(['admin', 'staff'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

if ($project['visibility'] === 'batch' && $project['owner_batch'] !== $user['batch'] && !Auth::hasAnyRole(['admin', 'staff'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit;
}

// Get project attachments
$attachments = db()->fetchAll(
    "SELECT pa.*, u.name as uploader_name
     FROM project_attachments pa
     LEFT JOIN users u ON pa.uploader_id = u.id
     WHERE pa.project_id = ? AND pa.is_approved = 1
     ORDER BY pa.created_at DESC",
    [$project_id]
);

// Get project contributions (approved only for public view)
$contributions = db()->fetchAll(
    "SELECT pc.*, u.name as contributor_name, u.profile_photo as contributor_photo
     FROM project_contributions pc
     LEFT JOIN users u ON pc.contributor_id = u.id
     WHERE pc.project_id = ? AND pc.is_approved = 1
     ORDER BY pc.created_at DESC
     LIMIT 20",
    [$project_id]
);

// Get pending contributions for owners/admins
$pending_contributions = [];
if ($is_owner || Auth::hasAnyRole(['admin', 'staff'])) {
    $pending_contributions = db()->fetchAll(
        "SELECT pc.*, u.name as contributor_name, u.profile_photo as contributor_photo, u.email as contributor_email
         FROM project_contributions pc
         LEFT JOIN users u ON pc.contributor_id = u.id
         WHERE pc.project_id = ? AND pc.is_approved = 0
         ORDER BY pc.created_at DESC",
        [$project_id]
    );
}

// Get project helpers
$helpers = db()->fetchAll(
    "SELECT ph.*, u.name as helper_name, u.profile_photo as helper_photo
     FROM project_helpers ph
     LEFT JOIN users u ON ph.user_id = u.id
     WHERE ph.project_id = ? AND ph.status = 'accepted'
     ORDER BY ph.created_at ASC",
    [$project_id]
);

// Get project activity
$activity = db()->fetchAll(
    "SELECT pa.*, u.name as actor_name, u.profile_photo as actor_photo
     FROM project_activity pa
     LEFT JOIN users u ON pa.actor_id = u.id
     WHERE pa.project_id = ?
     ORDER BY pa.created_at DESC
     LIMIT 10",
    [$project_id]
);

$is_owner = $project['owner_id'] === $user['id'];
$is_helper = false;
foreach ($helpers as $helper) {
    if ($helper['user_id'] === $user['id']) {
        $is_helper = true;
        break;
    }
}
// Allow contributions by default - any logged-in user can contribute
$settings = db()->fetchOne("SELECT require_file_approval, allow_public_contributions FROM project_settings WHERE project_id = ?", [$project_id]);
$allow_public_contributions = (int)($settings['allow_public_contributions'] ?? 1) === 1; // Default to 1 (enabled)
$can_contribute = $is_owner || $is_helper || Auth::hasAnyRole(['admin', 'staff']) || $allow_public_contributions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($project['title']); ?> - Projects Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <!-- Project Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h1 class="h3 mb-2"><?php echo htmlspecialchars($project['title']); ?></h1>
                        <div class="d-flex align-items-center gap-3">
                            <a href="profile.php?id=<?php echo $project['owner_id']; ?>" class="text-decoration-none">
                                <?php if ($project['owner_photo']): ?>
                                    <img src="<?php echo htmlspecialchars($project['owner_photo']); ?>" class="rounded-circle me-2" width="32" height="32">
                                <?php else: ?>
                                    <i class="fas fa-user-circle me-2"></i>
                                <?php endif; ?>
                                <span class="text-muted"><?php echo htmlspecialchars($project['owner_name']); ?></span>
                            </a>
                            <span class="badge bg-<?php echo $project['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                            </span>
                            <span class="badge bg-secondary"><?php echo ucfirst($project['visibility']); ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 project-header-actions d-none d-md-flex flex-wrap justify-content-end">
                        <?php if ($is_owner || Auth::hasAnyRole(['admin', 'staff'])): ?>
                            <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-outline-danger" onclick="deleteProject('<?php echo $project['id']; ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                        <a href="message.php?user=<?php echo $project['owner_id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-comment"></i> Message
                        </a>
                        <?php if ($project['github_url']): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" class="btn btn-dark">
                                <i class="fab fa-github"></i> GitHub
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($project['tags']): ?>
                    <div class="mb-3">
                        <?php foreach (explode(',', $project['tags']) as $tag): ?>
                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Project Description -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Description</h5>
                    </div>
                    <div class="card-body">
                        <div class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($project['description'])); ?></div>
                    </div>
                </div>

                <!-- Mobile Actions (below description) -->
                <div class="d-md-none mb-3">
                    <div class="d-grid gap-2">
                        <?php if ($is_owner || Auth::hasAnyRole(['admin', 'staff'])): ?>
                            <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-outline-danger" onclick="deleteProject('<?php echo $project['id']; ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                        <?php if ($project['github_url']): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" class="btn btn-dark">
                                <i class="fab fa-github"></i> GitHub
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attachments -->
                <?php if (!empty($attachments)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paperclip"></i> Attachments (<?php echo count($attachments); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-file fa-2x text-muted me-3"></i>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($attachment['file_name']); ?></h6>
                                                        <small class="text-muted"><?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">by <?php echo htmlspecialchars($attachment['uploader_name']); ?></small>
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <?php if ($is_owner || Auth::hasAnyRole(['admin','staff']) || $attachment['uploader_id'] === $user['id']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAttachment('<?php echo $attachment['id']; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Pending Contributions (Owner/Admin only) -->
                <?php if (!empty($pending_contributions) && ($is_owner || Auth::hasAnyRole(['admin', 'staff']))): ?>
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-clock"></i> Pending Contributions (<?php echo count($pending_contributions); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($pending_contributions as $pending): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-start">
                                            <?php if ($pending['contributor_photo']): ?>
                                                <img src="<?php echo htmlspecialchars($pending['contributor_photo']); ?>" class="rounded-circle me-3" width="40" height="40">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($pending['contributor_name']); ?></h6>
                                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($pending['created_at'])); ?></small>
                                                </div>
                                                <span class="badge bg-warning mb-2"><?php echo ucfirst($pending['type']); ?></span>
                                                <?php if ($pending['message']): ?>
                                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($pending['message'])); ?></p>
                                                <?php endif; ?>
                                                <?php if ($pending['attachment_id']): ?>
                                                    <div class="mb-2">
                                                        <i class="fas fa-paperclip"></i> File attached
                                                    </div>
                                                <?php endif; ?>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-success" onclick="approveContribution('<?php echo $pending['id']; ?>')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectContribution('<?php echo $pending['id']; ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Contributions -->
                <div class="card mb-4" id="contributionsCard">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0"><i class="fas fa-comments"></i> Contributions (<?php echo count($contributions); ?>)</h5>
                        <?php if ($can_contribute): ?>
                            <button class="btn btn-sm btn-primary" onclick="showContributeModal()">
                                <i class="fas fa-plus"></i> Add Contribution
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contributions)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <p>No contributions yet. Be the first to contribute!</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($contributions as $contrib): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-start">
                                            <?php if ($contrib['contributor_photo']): ?>
                                                <img src="<?php echo htmlspecialchars($contrib['contributor_photo']); ?>" class="rounded-circle me-3" width="40" height="40">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle fa-2x text-muted me-3"></i>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($contrib['contributor_name']); ?></h6>
                                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($contrib['created_at'])); ?></small>
                                                </div>
                                                <span class="badge bg-<?php echo $contrib['type'] === 'comment' ? 'primary' : ($contrib['type'] === 'feedback' ? 'warning' : 'info'); ?> mb-2">
                                                    <?php echo ucfirst($contrib['type']); ?>
                                                </span>
                                                <?php if ($contrib['message']): ?>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($contrib['message'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <?php if ($is_owner || Auth::hasAnyRole(['admin','staff'])): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Project Settings</h5>
                        <small class="text-muted">Owner only</small>
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="require_file_approval" name="require_file_approval" <?php echo ((int)($settings['require_file_approval'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_file_approval">Require approval for file contributions</label>
                                <div class="form-text">Files uploaded by contributors will need your approval</div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="allow_public_contributions" name="allow_public_contributions" <?php echo ((int)($settings['allow_public_contributions'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_public_contributions">Allow public contributions</label>
                                <div class="form-text">Anyone can contribute to this project</div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Settings</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Project Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Project Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 text-primary"><?php echo count($attachments); ?></div>
                                <small class="text-muted">Files</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 text-success"><?php echo count($contributions); ?></div>
                                <small class="text-muted">Contributions</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 text-info"><?php echo count($helpers); ?></div>
                                <small class="text-muted">Helpers</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Helpers -->
                <?php if (!empty($helpers)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Project Helpers</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($helpers as $helper): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center">
                                            <?php if ($helper['helper_photo']): ?>
                                                <img src="<?php echo htmlspecialchars($helper['helper_photo']); ?>" class="rounded-circle me-3" width="32" height="32">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle me-3"></i>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold"><?php echo htmlspecialchars($helper['helper_name']); ?></div>
                                                <small class="text-muted"><?php echo ucfirst($helper['role']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activity)): ?>
                            <div class="text-muted text-center py-3">No recent activity</div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($activity as $act): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-primary"></div>
                                        <div class="timeline-content">
                                            <div class="small">
                                                <strong><?php echo htmlspecialchars($act['actor_name']); ?></strong>
                                                <?php echo ucfirst(str_replace('_', ' ', $act['activity_type'])); ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('M j, g:i A', strtotime($act['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Contribution Modal -->
    <?php if ($can_contribute): ?>
    <div class="modal fade" id="contributeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Contribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="contributeForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="comment">Comment</option>
                                <option value="feedback">Feedback</option>
                                <option value="file">File Upload</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="4" placeholder="Share your thoughts or feedback..."></textarea>
                        </div>
                        <div class="mb-3" id="fileUploadDiv" style="display: none;">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Contribution</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <!-- Mobile Action Bar -->
    <div class="mobile-action-bar d-md-none">
        <a class="btn-action" href="message.php?user=<?php echo $project['owner_id']; ?>">
            <i class="fas fa-comment"></i>
            <span>Message</span>
        </a>
        <?php if ($can_contribute): ?>
        <button class="btn-action" type="button" onclick="showContributeModal()">
            <i class="fas fa-plus-circle"></i>
            <span>Contribute</span>
        </button>
        <?php endif; ?>
        <?php if (!empty($project['github_url'])): ?>
        <a class="btn-action" href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" rel="noreferrer">
            <i class="fab fa-github"></i>
            <span>GitHub</span>
        </a>
        <?php endif; ?>
        <?php if ($is_owner || Auth::hasAnyRole(['admin', 'staff'])): ?>
        <a class="btn-action" href="project_edit.php?id=<?php echo $project['id']; ?>">
            <i class="fas fa-edit"></i>
            <span>Edit</span>
        </a>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showContributeModal() {
            const modalElement = document.getElementById('contributeModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Contribute modal element not found');
            }
        }

        function deleteProject(projectId) {
            if (!confirm('Delete this project permanently?')) return;
            const form = new FormData();
            form.append('project_id', projectId);
            fetch('api/project_delete.php', { method: 'POST', body: form })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Project deleted successfully!');
                        setTimeout(() => window.location = 'projects.php', 1000);
                    } else {
                        showAlert('danger', data.message || 'Failed to delete project');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error. Please try again.');
                    console.error(error);
                });
        }

        function deleteAttachment(attachmentId) {
            console.log('deleteAttachment called with ID:', attachmentId);
            
            if (!attachmentId) {
                showAlert('danger', 'Invalid attachment ID');
                return;
            }
            
            if (!confirm('Delete this attachment?')) return;
            
            // Show loading state
            showAlert('info', 'Deleting attachment...');
            
            const form = new FormData();
            form.append('attachment_id', attachmentId);
            
            console.log('Sending delete request for attachment:', attachmentId);
            
            fetch('api/project_attachment_delete.php', { 
                method: 'POST', 
                body: form,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    console.log('Response received:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        showAlert('success', 'Attachment deleted successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', data.message || 'Failed to delete attachment');
                    }
                })
                .catch(error => {
                    console.error('Delete attachment error:', error);
                    showAlert('danger', 'Error deleting attachment: ' + error.message);
                });
        }

        function approveContribution(contributionId) {
            if (!confirm('Approve this contribution?')) return;
            const form = new FormData();
            form.append('contribution_id', contributionId);
            form.append('action', 'approve');
            
            fetch('api/project_contribution_approve.php', { method: 'POST', body: form })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Contribution approved successfully!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', data.message || 'Failed to approve contribution');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error. Please try again.');
                    console.error(error);
                });
        }

        function rejectContribution(contributionId) {
            if (!confirm('Reject this contribution? This action cannot be undone.')) return;
            const form = new FormData();
            form.append('contribution_id', contributionId);
            form.append('action', 'reject');
            
            fetch('api/project_contribution_approve.php', { method: 'POST', body: form })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Contribution rejected successfully!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', data.message || 'Failed to reject contribution');
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error. Please try again.');
                    console.error(error);
                });
        }

        const typeSelect = document.querySelector('select[name="type"]');
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                const fileDiv = document.getElementById('fileUploadDiv');
                if (!fileDiv) return;
                if (this.value === 'file') {
                    fileDiv.style.display = 'block';
                } else {
                    fileDiv.style.display = 'none';
                }
            });
        }

        const contributeForm = document.getElementById('contributeForm');
        if (contributeForm) {
            contributeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                submitBtn.disabled = true;
                
                fetch('api/project_contribute.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { 
                        if (data.success) { 
                            // Show success message
                            showAlert('success', data.message || 'Contribution submitted successfully!');
                            // Close modal properly
                            const modalElement = document.getElementById('contributeModal');
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            if (modal) {
                                modal.hide();
                            } else {
                                // If no instance exists, create one and hide it
                                new bootstrap.Modal(modalElement).hide();
                            }
                            // Reset form
                            this.reset();
                            // Hide file upload div
                            const fileDiv = document.getElementById('fileUploadDiv');
                            if (fileDiv) fileDiv.style.display = 'none';
                            // Reload after delay
                            setTimeout(() => location.reload(), 1000);
                        } else { 
                            // Show specific error message with styling
                            showAlert('danger', data.message || 'Failed to submit contribution', data.code);
                        }
                    })
                    .catch(error => { 
                        showAlert('danger', 'Network error. Please try again.');
                        console.error(error); 
                    })
                    .finally(() => {
                        // Restore button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
            });
        }
        
        // Function to show styled alerts
        function showAlert(type, message, code = null) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
                ${code ? `<br><small class="text-muted">Error Code: ${code}</small>` : ''}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert at top of main content
            const main = document.querySelector('main .container');
            if (main) {
                main.insertBefore(alertDiv, main.firstChild);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        }

        // Modal event listeners
        const contributeModal = document.getElementById('contributeModal');
        if (contributeModal) {
            contributeModal.addEventListener('hidden.bs.modal', function() {
                // Reset form when modal is closed
                const form = document.getElementById('contributeForm');
                if (form) {
                    form.reset();
                    // Hide file upload div
                    const fileDiv = document.getElementById('fileUploadDiv');
                    if (fileDiv) fileDiv.style.display = 'none';
                    // Reset submit button
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = 'Submit Contribution';
                        submitBtn.disabled = false;
                    }
                }
            });
        }

        // Settings save
        const settingsForm = document.getElementById('settingsForm');
        if (settingsForm) {
            settingsForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const formData = new FormData(settingsForm);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
                
                try {
                    const res = await fetch('api/project_settings_update.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        showAlert('success', 'Settings saved successfully!');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('danger', data.message || 'Failed to save settings');
                    }
                } catch (err) {
                    showAlert('danger', 'Network error. Please try again.');
                    console.error(err);
                } finally {
                    // Restore button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });
        }
    </script>
    
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 15px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
        }
        @media (max-width: 768px){
            .project-header-actions{ display:none; }
            body { padding-bottom: 72px; }
        }
        
        /* Mobile action bar */
        .mobile-action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-around;
            gap: 4px;
            padding: 10px 8px calc(10px + env(safe-area-inset-bottom));
            background: #ffffff;
            border-top: 1px solid #e9ecef;
            z-index: 1040;
        }
        .mobile-action-bar .btn-action {
            flex: 1;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 8px 6px;
            color: #124170;
            text-decoration: none;
            font-size: 12px;
            line-height: 1.1;
        }
        .mobile-action-bar .btn-action i {
            display: block;
            font-size: 16px;
            margin-bottom: 4px;
        }
        .mobile-action-bar .btn-action:hover { background: #eef2f7; }
        
        /* Make contributions header stack nicely on small screens */
        @media (max-width: 576px){
            #contributionsCard .card-header { flex-direction: column; align-items: stretch; }
            #contributionsCard .card-header .btn { width: 100%; }
        }
    </style>
</body>
</html>

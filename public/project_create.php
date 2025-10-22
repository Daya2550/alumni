<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $github_url = sanitizeInput($_POST['github_url'] ?? '');
    $tags = sanitizeInput($_POST['tags'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'in_progress');
    $visibility = sanitizeInput($_POST['visibility'] ?? 'public');
    
    if (empty($title) || empty($description)) {
        $error = 'Title and description are required';
    } else {
        try {
            $project_id = generateUUID();
            
            // Students/alumni need approval, staff/admin can publish directly
            $approval_status = in_array($user['role'], ['admin', 'staff']) ? 'published' : 'pending';
            
            db()->beginTransaction();
            
            // Create project
            db()->execute(
                "INSERT INTO projects (id, owner_id, title, description, github_url, tags, status, visibility, approval_status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $project_id,
                    $user['id'],
                    $title,
                    $description,
                    $github_url ?: null,
                    $tags ?: null,
                    $status,
                    $visibility,
                    $approval_status,
                    $approval_status === 'published' ? date('Y-m-d H:i:s') : null
                ]
            );
            
            // Create project settings
            db()->execute(
                "INSERT INTO project_settings (project_id, require_file_approval, allow_public_contributions) VALUES (?, ?, ?)",
                [
                    $project_id,
                    isset($_POST['require_file_approval']) ? 1 : 0,
                    isset($_POST['allow_public_contributions']) ? 1 : 0
                ]
            );
            
            // Log activity
            db()->execute(
                "INSERT INTO project_activity (id, project_id, actor_id, activity_type) VALUES (?, ?, ?, ?)",
                [generateUUID(), $project_id, $user['id'], 'created']
            );
            
            // Handle file uploads
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/projects/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                            'size' => $_FILES['attachments']['size'][$i]
                        ];
                        
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $file_name = 'proj_' . uniqid() . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $attachment_id = generateUUID();
                            db()->execute(
                                "INSERT INTO project_attachments (id, project_id, uploader_id, file_path, file_name, mime_type, file_size, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $attachment_id,
                                    $project_id,
                                    $user['id'],
                                    'uploads/projects/' . $file_name,
                                    $file['name'],
                                    $file['type'],
                                    $file['size'],
                                    1
                                ]
                            );
                        }
                    }
                }
            }
            
            db()->commit();
            
            if ($approval_status === 'published') {
                // Notify all active users except owner
                Notifications::notifyAllActiveUsersExcept($user['id'], 'New Project Published', substr($title, 0, 120), 'info', 'project', $project_id);
                $success = 'Project created and published successfully!';
            } else {
                $success = 'Project created and submitted for approval. You will be notified when it\'s reviewed.';
            }
            
            // Redirect to project page
            header('Location: project.php?id=' . $project_id);
            exit;
            
        } catch (Exception $e) {
            db()->rollback();
            $error = 'Failed to create project: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Project - Projects Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1"><i class="fas fa-plus text-primary"></i> Create New Project</h1>
                        <p class="text-muted mb-0">Share your project with the community</p>
                    </div>
                    <a href="projects.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="projectForm">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Project Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Project Title *</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control" name="description" rows="6" required placeholder="Describe your project, its goals, and what makes it special..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">GitHub Repository URL</label>
                                    <input type="url" class="form-control" name="github_url" value="<?php echo htmlspecialchars($_POST['github_url'] ?? ''); ?>" placeholder="https://github.com/username/repo">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tags</label>
                                    <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>" placeholder="php, web, api, mobile (comma separated)">
                                    <small class="form-text text-muted">Separate tags with commas</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="in_progress" <?php echo ($_POST['status'] ?? 'in_progress') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo ($_POST['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Visibility</label>
                                    <select class="form-select" name="visibility">
                                        <option value="public" <?php echo ($_POST['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
                                        <option value="batch" <?php echo ($_POST['visibility'] ?? '') === 'batch' ? 'selected' : ''; ?>>Batch Only</option>
                                        <option value="private" <?php echo ($_POST['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paperclip"></i> Attachments</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Project Files</label>
                                <input type="file" class="form-control" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                                <small class="form-text text-muted">Upload images, documents, or other project files (max 10MB each)</small>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-cog"></i> Project Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="require_file_approval" id="require_file_approval" <?php echo isset($_POST['require_file_approval']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_file_approval">
                                    Require approval for file contributions
                                </label>
                                <small class="form-text text-muted">Files uploaded by contributors will need your approval</small>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allow_public_contributions" id="allow_public_contributions" <?php echo isset($_POST['allow_public_contributions']) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="allow_public_contributions">
                                    Allow public contributions
                                </label>
                                <small class="form-text text-muted">Anyone can contribute to this project</small>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Ready to create your project?</h6>
                                    <small class="text-muted">
                                        <?php if (in_array($user['role'], ['admin', 'staff'])): ?>
                                            Your project will be published immediately.
                                        <?php else: ?>
                                            Your project will be submitted for staff approval before publishing.
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-rocket"></i> Create Project
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload validation
        document.querySelector('input[type="file"]').addEventListener('change', function() {
            const files = this.files;
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > maxSize) {
                    alert('File "' + files[i].name + '" is too large. Maximum size is 10MB.');
                    this.value = '';
                    return;
                }
            }
        });
        
        // Form validation
        document.getElementById('projectForm').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if (!title || !description) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                alert('Project title must be at least 5 characters long.');
                return;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                alert('Project description must be at least 20 characters long.');
                return;
            }
        });
    </script>
</body>
</html>

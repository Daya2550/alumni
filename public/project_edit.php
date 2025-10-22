<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$project_id = sanitizeInput($_GET['id'] ?? '');
if (!$project_id) { header('Location: projects.php'); exit; }

$project = db()->fetchOne("SELECT * FROM projects WHERE id = ?", [$project_id]);
if (!$project) { header('HTTP/1.1 404 Not Found'); echo 'Project not found'; exit; }

$isOwner = ($project['owner_id'] === $user['id']);
$isPrivileged = Auth::hasAnyRole(['admin','staff']);
if (!$isOwner && !$isPrivileged) { header('HTTP/1.1 403 Forbidden'); echo 'Access denied'; exit; }

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
            db()->execute(
                "UPDATE projects SET title = ?, description = ?, github_url = ?, tags = ?, status = ?, visibility = ?, updated_at = NOW() WHERE id = ?",
                [$title, $description, ($github_url ?: null), ($tags ?: null), $status, $visibility, $project_id]
            );
            $success = 'Project updated successfully';
            // Refresh project data
            $project = db()->fetchOne("SELECT * FROM projects WHERE id = ?", [$project_id]);
        } catch (Exception $e) {
            $error = 'Failed to update: ' . $e->getMessage();
        }
    }
}

$attachments = db()->fetchAll("SELECT * FROM project_attachments WHERE project_id = ? ORDER BY created_at DESC", [$project_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - Projects Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-edit text-primary"></i> Edit Project</h1>
            <p class="text-muted mb-0">Update details and manage attachments</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="project.php?id=<?php echo $project['id']; ?>"><i class="fas fa-arrow-left"></i> Back</a>
            <button class="btn btn-outline-danger" onclick="deleteProject('<?php echo $project['id']; ?>')"><i class="fas fa-trash"></i> Delete Project</button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle"></i> Project Information</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description *</label>
                    <textarea class="form-control" name="description" rows="6" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">GitHub URL</label>
                        <input type="url" class="form-control" name="github_url" value="<?php echo htmlspecialchars($project['github_url']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($project['tags']); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="in_progress" <?php echo $project['status']==='in_progress'?'selected':''; ?>>In Progress</option>
                            <option value="completed" <?php echo $project['status']==='completed'?'selected':''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Visibility</label>
                        <select class="form-select" name="visibility">
                            <option value="public" <?php echo $project['visibility']==='public'?'selected':''; ?>>Public</option>
                            <option value="batch" <?php echo $project['visibility']==='batch'?'selected':''; ?>>Batch Only</option>
                            <option value="private" <?php echo $project['visibility']==='private'?'selected':''; ?>>Private</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-end mb-4">
            <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-paperclip"></i> Attachments (<?php echo count($attachments); ?>)</h5>
            <div class="d-flex gap-2">
                <input type="file" id="newAttachment" class="form-control" style="max-width:300px" accept="image/*,.pdf,.doc,.docx,.txt,.zip,.rar">
                <button class="btn btn-outline-primary" onclick="uploadAttachment()"><i class="fas fa-upload"></i> Upload</button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($attachments)): ?>
                <div class="text-muted">No attachments yet.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($attachments as $a): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-file fa-2x text-muted me-3"></i>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small mb-1"><?php echo htmlspecialchars($a['file_name']); ?></div>
                                            <small class="text-muted"><?php echo number_format($a['file_size']/1024,1); ?> KB</small>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a target="_blank" class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($a['file_path']); ?>"><i class="fas fa-download"></i></a>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAttachment('<?php echo $a['id']; ?>')"><i class="fas fa-trash"></i></button>
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
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deleteProject(projectId){
    if(!confirm('Delete this project permanently?')) return;
    const fd=new FormData(); fd.append('project_id', projectId);
    fetch('api/project_delete.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){ window.location='projects.php'; } else { alert(d.message||'Failed'); }
    });
}
function uploadAttachment(){
    const inp=document.getElementById('newAttachment');
    if(!inp.files.length){ alert('Choose a file'); return; }
    const fd=new FormData();
    fd.append('project_id','<?php echo $project['id']; ?>');
    fd.append('file', inp.files[0]);
    fetch('api/project_attachment_upload.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){ location.reload(); } else { alert(d.message||'Upload failed'); }
    }).catch(()=>alert('Upload failed'));
}
function deleteAttachment(id){
    if(!confirm('Delete this attachment?')) return;
    const fd=new FormData(); fd.append('attachment_id', id);
    fetch('api/project_attachment_delete.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){ location.reload(); } else { alert(d.message||'Failed'); }
    });
}
</script>
</body>
</html>






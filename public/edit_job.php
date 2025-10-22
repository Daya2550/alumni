<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$job_id = intval($_GET['id'] ?? 0);
if ($job_id <= 0) { header('Location: jobs.php'); exit; }

$job = db()->fetchOne("SELECT * FROM job_opportunities WHERE id = ?", [$job_id]);
if (!$job) { header('Location: jobs.php'); exit; }

// Get existing attachments
$attachments = db()->fetchAll(
    "SELECT * FROM job_attachments WHERE job_id = ? ORDER BY created_at ASC",
    [$job_id]
);

$isPrivileged = Auth::hasAnyRole(['staff','admin']);
$isAuthor = ($job['posted_by'] === $user['id']);
if (!($isPrivileged || $isAuthor)) { header('HTTP/1.1 403 Forbidden'); exit('Access denied'); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['delete_attachment'])) {
        // Handle attachment deletion
        $attachment_id = trim($_POST['delete_attachment']);
        try {
            $attachment = db()->fetchOne("SELECT * FROM job_attachments WHERE id = ? AND job_id = ?", [$attachment_id, $job_id]);
            if ($attachment) {
                // Delete file from filesystem
                $filePath = __DIR__ . '/../' . $attachment['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                // Delete from database
                db()->execute("DELETE FROM job_attachments WHERE id = ?", [$attachment_id]);
                $message = 'Attachment deleted successfully.';
                // Refresh attachments list
                $attachments = db()->fetchAll("SELECT * FROM job_attachments WHERE job_id = ? ORDER BY created_at ASC", [$job_id]);
            } else {
                $error = 'Attachment not found.';
            }
        } catch (Exception $e) {
            $error = 'Failed to delete attachment.';
            error_log('Delete attachment error: ' . $e->getMessage());
        }
    } else {
        // Collect inputs (allow empty, we will fallback to existing values)
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $company = isset($_POST['company']) ? trim($_POST['company']) : '';
        $location = isset($_POST['location']) ? trim($_POST['location']) : '';
        $salary_range = isset($_POST['salary_range']) ? trim($_POST['salary_range']) : '';
        $skills = isset($_POST['skills']) ? trim($_POST['skills']) : '';
        $deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
        $application_link = isset($_POST['application_link']) ? trim($_POST['application_link']) : '';
        $application_email = isset($_POST['application_email']) ? trim($_POST['application_email']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $status = $job['status'];

        // Fallback to current values if left empty
        $title = $title !== '' ? $title : $job['title'];
        $company = $company !== '' ? $company : $job['company'];
        $location = $location !== '' ? $location : ($job['location'] ?? '');
        $salary_range = $salary_range !== '' ? $salary_range : ($job['salary_range'] ?? '');
        $skills = $skills !== '' ? $skills : ($job['skills'] ?? '');
        $deadline = $deadline !== '' ? $deadline : ($job['deadline'] ? substr($job['deadline'], 0, 10) : '');
        $application_link = $application_link !== '' ? $application_link : ($job['application_link'] ?? '');
        $application_email = $application_email !== '' ? $application_email : ($job['application_email'] ?? '');
        $description = $description !== '' ? $description : $job['description'];

        if ($title === '' || $company === '' || $description === '' || $deadline === '') {
            $error = 'Please fill all required fields.';
        } else {
            try {
                $logoUrl = $job['company_logo'];
                if (!empty($_FILES['company_logo']['name'])) {
                    $uploadDir = __DIR__ . '/../uploads/jobs/';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    $name = $_FILES['company_logo']['name'] ?? '';
                    $tmp = $_FILES['company_logo']['tmp_name'] ?? '';
                    $err = $_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE;
                    $size = $_FILES['company_logo']['size'] ?? 0;
                    if ($err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                        if ($size > UPLOAD_MAX_SIZE) throw new Exception('Logo too large.');
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, ALLOWED_IMAGE_TYPES, true)) throw new Exception('Unsupported logo type.');
                        $newName = uniqid('job_logo_', true) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($tmp, $dest)) {
                            // delete old logo if within uploads/jobs/
                            if (!empty($logoUrl) && strpos($logoUrl, 'uploads/jobs/') === 0) {
                                $old = __DIR__ . '/../' . $logoUrl;
                                if (file_exists($old)) { @unlink($old); }
                            }
                            $logoUrl = 'uploads/jobs/' . $newName;
                        }
                    }
                }

                // Only staff/admin can change status
                if ($isPrivileged && isset($_POST['status']) && in_array($_POST['status'], ['pending','approved','rejected'], true)) {
                    $status = $_POST['status'];
                }

                // Start transaction for job and attachments
                db()->beginTransaction();

                try {
                    db()->execute(
                        "UPDATE job_opportunities SET title=?, company=?, company_logo=?, description=?, location=?, salary_range=?, skills=?, deadline=?, application_link=?, application_email=?, status=?, updated_at=NOW() WHERE id=?",
                        [
                            sanitizeInput($title),
                            sanitizeInput($company),
                            $logoUrl,
                            sanitizeInput($description),
                            sanitizeInput($location),
                            sanitizeInput($salary_range),
                            sanitizeInput($skills),
                            $deadline,
                            $application_link,
                            $application_email,
                            $status,
                            $job_id
                        ]
                    );

                    // Handle new file attachments
                    if (!empty($_FILES['new_attachments']['name'][0])) {
                        $uploadDir = __DIR__ . '/../uploads/jobs/attachments/';
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

                        $fileCount = count($_FILES['new_attachments']['name']);
                        for ($i = 0; $i < $fileCount; $i++) {
                            $fileName = $_FILES['new_attachments']['name'][$i] ?? '';
                            $fileTmp = $_FILES['new_attachments']['tmp_name'][$i] ?? '';
                            $fileError = $_FILES['new_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                            $fileSize = $_FILES['new_attachments']['size'][$i] ?? 0;

                            if ($fileError === UPLOAD_ERR_OK && is_uploaded_file($fileTmp) && $fileName !== '') {
                                if ($fileSize > UPLOAD_MAX_SIZE) {
                                    throw new Exception('File "' . $fileName . '" is too large.');
                                }

                                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ['pdf', 'doc', 'docx', 'txt']);
                                if (!in_array($ext, $allowedTypes, true)) {
                                    throw new Exception('File type not allowed for "' . $fileName . '".');
                                }

                                $newFileName = uniqid('job_att_', true) . '.' . $ext;
                                $filePath = $uploadDir . $newFileName;

                                if (move_uploaded_file($fileTmp, $filePath)) {
                                    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
                                    
                                    db()->execute(
                                        "INSERT INTO job_attachments (id, job_id, uploader_id, file_path, file_name, mime_type, file_size) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                                        [
                                            generateUUID(),
                                            $job_id,
                                            $user['id'],
                                            'uploads/jobs/attachments/' . $newFileName,
                                            $fileName,
                                            $mimeType,
                                            $fileSize
                                        ]
                                    );
                                }
                            }
                        }
                    }

                    db()->commit();
                    $message = 'Updated successfully';
                    $job = db()->fetchOne("SELECT * FROM job_opportunities WHERE id = ?", [$job_id]);
                    // Refresh attachments list
                    $attachments = db()->fetchAll("SELECT * FROM job_attachments WHERE job_id = ? ORDER BY created_at ASC", [$job_id]);
                } catch (Exception $e) {
                    db()->rollback();
                    throw $e;
                }
            } catch (Exception $e) {
                error_log('Edit job error: ' . $e->getMessage());
                $error = 'Failed to update.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3"><i class="fas fa-edit"></i> Edit Job</h1>
            <a href="jobs.php" class="btn btn-outline-secondary"><i class="fas fa-briefcase"></i> Jobs</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Job Title *</label>
                            <input type="text" name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($job['title']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company *</label>
                            <input type="text" name="company" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($job['company']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($job['location']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salary Range</label>
                            <input type="text" name="salary_range" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($job['salary_range']); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Required Skills (comma-separated)</label>
                            <input type="text" name="skills" class="form-control" maxlength="500" value="<?php echo htmlspecialchars($job['skills']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Application Deadline *</label>
                            <input type="date" name="deadline" class="form-control" required value="<?php echo htmlspecialchars(substr($job['deadline'], 0, 10)); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Logo (optional)</label>
                            <input type="file" name="company_logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                            <?php if (!empty($job['company_logo'])): ?>
                                <div class="mt-2">
                                    <a href="../<?php echo htmlspecialchars($job['company_logo']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Current Logo</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Application Link (URL)</label>
                            <input type="url" name="application_link" class="form-control" value="<?php echo htmlspecialchars($job['application_link']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Application Email</label>
                            <input type="email" name="application_email" class="form-control" value="<?php echo htmlspecialchars($job['application_email']); ?>">
                        </div>
                        <?php if ($isPrivileged): ?>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
                                        <option value="<?php echo $k; ?>" <?php echo $job['status'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Existing Attachments -->
                        <?php if (!empty($attachments)): ?>
                        <div class="col-12">
                            <h5><i class="fas fa-paperclip"></i> Current Attachments</h5>
                            <div class="row g-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <?php 
                                                    $ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                    if ($isImage): ?>
                                                        <i class="fas fa-image text-primary me-2"></i>
                                                    <?php elseif ($ext === 'pdf'): ?>
                                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                                    <?php elseif (in_array($ext, ['doc', 'docx'])): ?>
                                                        <i class="fas fa-file-word text-info me-2"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file text-secondary me-2"></i>
                                                    <?php endif; ?>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold small"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
                                                        <div class="text-muted small"><?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB</div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                       class="btn btn-sm btn-outline-primary flex-grow-1" 
                                                       target="_blank">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                    <button type="submit" name="delete_attachment" value="<?php echo htmlspecialchars($attachment['id']); ?>" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this attachment?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Add New Attachments -->
                        <div class="col-12">
                            <label class="form-label">Add New Attachments (optional)</label>
                            <input type="file" name="new_attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                You can upload multiple files. Supported formats: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX, TXT). Max size per file: <?php echo number_format(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



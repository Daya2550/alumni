<?php
/**
 * Submit Job - Any logged-in user can submit. Staff/Admin auto-approve.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $salary_range = trim($_POST['salary_range'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $deadline = trim($_POST['deadline'] ?? '');
        $application_link = trim($_POST['application_link'] ?? '');
        $application_email = trim($_POST['application_email'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $company === '' || $description === '' || $deadline === '') {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Company logo upload (optional)
                $logoUrl = null;
                if (!empty($_FILES['company_logo']['name'])) {
                    $uploadDir = __DIR__ . '/../uploads/jobs/';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    $name = $_FILES['company_logo']['name'] ?? '';
                    $tmp = $_FILES['company_logo']['tmp_name'] ?? '';
                    $err = $_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE;
                    $size = $_FILES['company_logo']['size'] ?? 0;
                    if ($err === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                        if ($size > UPLOAD_MAX_SIZE) throw new Exception('Logo is too large.');
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, ALLOWED_IMAGE_TYPES, true)) throw new Exception('Unsupported logo type.');
                        $newName = uniqid('job_logo_', true) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($tmp, $dest)) {
                            $logoUrl = 'uploads/jobs/' . $newName;
                        }
                    }
                }

                $isPrivileged = Auth::hasAnyRole(['staff','admin']);
                $status = $isPrivileged ? 'approved' : 'pending';

                // Start transaction for job and attachments
                db()->beginTransaction();

                try {
                    db()->execute(
                        "INSERT INTO job_opportunities (title, company, company_logo, description, location, salary_range, skills, deadline, application_link, application_email, status, posted_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
                            $user['id']
                        ]
                    );

                    $jobId = db()->lastInsertId();

                    // Handle file attachments
                    if (!empty($_FILES['attachments']['name'][0])) {
                        $uploadDir = __DIR__ . '/../uploads/jobs/attachments/';
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

                        $fileCount = count($_FILES['attachments']['name']);
                        for ($i = 0; $i < $fileCount; $i++) {
                            $fileName = $_FILES['attachments']['name'][$i] ?? '';
                            $fileTmp = $_FILES['attachments']['tmp_name'][$i] ?? '';
                            $fileError = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                            $fileSize = $_FILES['attachments']['size'][$i] ?? 0;

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
                                            $jobId,
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
                } catch (Exception $e) {
                    db()->rollback();
                    throw $e;
                }

                if ($isPrivileged) {
                    Notifications::notifyAllActiveUsersExcept($user['id'], 'New Job Opportunity', substr($title . ' at ' . $company, 0, 120), 'info', 'job', $jobId);
                    header('Location: jobs.php');
                    exit;
                } else {
                    $message = 'Submitted! Your job will be visible once approved by staff.';
                }
            } catch (Exception $e) {
                error_log('Submit job error: ' . $e->getMessage());
                $error = 'Failed to submit job. Please try again.';
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
    <title>Submit Job - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3"><i class="fas fa-plus text-primary"></i> Submit Job</h1>
                    <a href="jobs.php" class="btn btn-outline-secondary"><i class="fas fa-briefcase"></i> Back to Jobs</a>
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
                                    <input type="text" name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company *</label>
                                    <input type="text" name="company" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Salary Range</label>
                                    <input type="text" name="salary_range" class="form-control" maxlength="255" placeholder="e.g., 5–8 LPA or $60k–$80k" value="<?php echo htmlspecialchars($_POST['salary_range'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Required Skills (comma-separated)</label>
                                    <input type="text" name="skills" class="form-control" maxlength="500" value="<?php echo htmlspecialchars($_POST['skills'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Deadline *</label>
                                    <input type="date" name="deadline" class="form-control" required value="<?php echo htmlspecialchars($_POST['deadline'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company Logo (optional)</label>
                                    <input type="file" name="company_logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Link (URL)</label>
                                    <input type="url" name="application_link" class="form-control" value="<?php echo htmlspecialchars($_POST['application_link'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Email</label>
                                    <input type="email" name="application_email" class="form-control" value="<?php echo htmlspecialchars($_POST['application_email'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Job Description *</label>
                                    <textarea name="description" class="form-control" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Job Attachments (optional)</label>
                                    <input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Upload job-related documents like job descriptions, company brochures, etc. Supported formats: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX, TXT). Max size per file: <?php echo number_format(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>










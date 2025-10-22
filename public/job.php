<?php
/**
 * Individual Job View
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$job_id = intval($_GET['id'] ?? 0);

if (!$job_id) {
    header('Location: jobs.php');
    exit;
}

// Get job details
$job = db()->fetchOne(
    "SELECT jo.*, u.name as posted_by_name, u.email as posted_by_email
     FROM job_opportunities jo 
     JOIN users u ON jo.posted_by = u.id 
     WHERE jo.id = ? AND jo.status = 'approved'",
    [$job_id]
);

if (!$job) {
    header('HTTP/1.1 404 Not Found');
    echo 'Job not found';
    exit;
}

// Get job attachments
$attachments = db()->fetchAll(
    "SELECT * FROM job_attachments WHERE job_id = ? ORDER BY created_at ASC",
    [$job_id]
);

// Check if user already applied
$user_application = db()->fetchOne(
    "SELECT * FROM job_applications WHERE job_id = ? AND user_id = ?",
    [$job_id, $user['id']]
);

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if (!$user_application) {
        try {
            db()->execute(
                "INSERT INTO job_applications (job_id, user_id, status) VALUES (?, ?, 'applied')",
                [$job_id, $user['id']]
            );
            
            // Increment applications count
            db()->execute(
                "UPDATE job_opportunities SET applications_count = applications_count + 1 WHERE id = ?",
                [$job_id]
            );
            
            $success_message = 'Application submitted successfully!';
            $user_application = ['status' => 'applied'];
            
        } catch (Exception $e) {
            $error_message = 'Failed to submit application. Please try again.';
            error_log("Job application error: " . $e->getMessage());
        }
    } else {
        $error_message = 'You have already applied for this job.';
    }
}

// Increment view count
db()->execute(
    "UPDATE job_opportunities SET views_count = views_count + 1 WHERE id = ?",
    [$job_id]
);

// Get skills array
$skills = $job['skills'] ? explode(',', $job['skills']) : [];

// Get related jobs
$related_jobs = db()->fetchAll(
    "SELECT jo.id, jo.title, jo.company, jo.location, jo.deadline
     FROM job_opportunities jo 
     WHERE jo.id != ? AND jo.status = 'approved' AND jo.deadline >= CURDATE()
     ORDER BY jo.created_at DESC 
     LIMIT 4",
    [$job_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Job Details -->
                <article class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="h3 mb-2"><?php echo htmlspecialchars($job['title']); ?></h1>
                                <div class="d-flex align-items-center mb-2">
                                    <h4 class="text-muted mb-0 me-3">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company']); ?>
                                    </h4>
                                    <?php if ($job['company_logo']): ?>
                                        <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                             class="rounded" width="50" height="50" style="object-fit: cover;">
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted">
                                    <i class="fas fa-user"></i> Posted by <?php echo htmlspecialchars($job['posted_by_name']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y', strtotime($job['created_at'])); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($user_application): ?>
                                    <span class="badge bg-success fs-6">Applied</span>
                                <?php else: ?>
                                    <span class="badge bg-primary fs-6">Open</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Job Information -->
                        <div class="row mb-4">
                            <?php if ($job['location']): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-map-marker-alt text-primary me-3"></i>
                                        <div>
                                            <strong>Location</strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($job['location']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($job['salary_range']): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-dollar-sign text-success me-3"></i>
                                        <div>
                                            <strong>Salary</strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock text-warning me-3"></i>
                                    <div>
                                        <strong>Application Deadline</strong><br>
                                        <span class="text-muted"><?php echo date('F j, Y', strtotime($job['deadline'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-eye text-info me-3"></i>
                                    <div>
                                        <strong>Views</strong><br>
                                        <span class="text-muted"><?php echo $job['views_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Skills Required -->
                        <?php if (!empty($skills)): ?>
                            <div class="mb-4">
                                <h5><i class="fas fa-tools"></i> Required Skills</h5>
                                <div>
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Job Description -->
                        <div class="mb-4">
                            <h5><i class="fas fa-file-text"></i> Job Description</h5>
                            <div class="job-description">
                                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                            </div>
                        </div>

                        <?php if (!empty($attachments)): ?>
                        <div class="mb-4">
                            <h5><i class="fas fa-paperclip"></i> Job Attachments</h5>
                            <div class="row g-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card h-100">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center">
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
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                       class="btn btn-sm btn-outline-primary w-100" 
                                                       target="_blank">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Application Actions -->
                        <?php if ($job['deadline'] >= date('Y-m-d')): ?>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <?php if ($user_application): ?>
                                    <button class="btn btn-success" disabled>
                                        <i class="fas fa-check"></i> Already Applied
                                    </button>
                                <?php else: ?>
                                    <?php if ($job['application_link']): ?>
                                        <a href="<?php echo htmlspecialchars($job['application_link']); ?>" 
                                           class="btn btn-primary" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> Apply via Link
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($job['application_email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($job['application_email']); ?>?subject=Application for <?php echo urlencode($job['title']); ?>" 
                                           class="btn btn-primary">
                                            <i class="fas fa-envelope"></i> Apply via Email
                                        </a>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="apply" class="btn btn-success">
                                            <i class="fas fa-paper-plane"></i> Mark as Applied
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                This job posting has expired.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="text-muted">
                                <i class="fas fa-users"></i> <?php echo $job['applications_count']; ?> applications
                            </div>
                            <div class="d-flex gap-2">
                                <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
                                    <a href="admin/job_applicants_export.php?job_id=<?php echo $job_id; ?>" class="btn btn-success">
                                        <i class="fas fa-file-excel"></i> Download Applicants (CSV)
                                    </a>
                                <?php endif; ?>
                                <a href="jobs.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Jobs
                                </a>
                                <button onclick="window.print()" class="btn btn-outline-primary">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            
            <div class="col-lg-4">
                <!-- Related Jobs -->
                <?php if (!empty($related_jobs)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-briefcase"></i> Related Jobs</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($related_jobs as $related): ?>
                                    <a href="job.php?id=<?php echo $related['id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="fw-bold"><?php echo htmlspecialchars($related['title']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($related['company']); ?>
                                            <?php if ($related['location']): ?>
                                                <br><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($related['location']); ?>
                                            <?php endif; ?>
                                            <br><i class="fas fa-clock"></i> Deadline: <?php echo date('M j, Y', strtotime($related['deadline'])); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="jobs.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> All Jobs
                            </a>
                            <button onclick="shareJob()" class="btn btn-outline-secondary">
                                <i class="fas fa-share"></i> Share Job
                            </button>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function shareJob() {
            const url = window.location.href;
            const title = document.querySelector('h1').textContent;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Job link copied to clipboard!');
                });
            }
        }
    </script>
    
    <?php if (isset($success_message)): ?>
        <script>
            showAlert('success', '<?php echo addslashes($success_message); ?>');
        </script>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <script>
            showAlert('danger', '<?php echo addslashes($error_message); ?>');
        </script>
    <?php endif; ?>
</body>
</html>

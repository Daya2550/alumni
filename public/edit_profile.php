<?php
/**
 * Edit Profile Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $bio = sanitizeInput($_POST['bio'] ?? '');
    $job_title = sanitizeInput($_POST['job_title'] ?? '');
    $company = sanitizeInput($_POST['company'] ?? '');
    $skills = sanitizeInput($_POST['skills'] ?? '');
    $batch = sanitizeInput($_POST['batch'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    
    // Handle profile photo upload/removal
    $profile_photo = $user['profile_photo'];

    // Remove current photo (set to default)
    if (!empty($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        if ($profile_photo && file_exists($profile_photo)) { @unlink($profile_photo); }
        $profile_photo = null;
    }

    // Upload new photo
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = UPLOAD_PATH . 'profiles/';
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }
        $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($file_extension, ALLOWED_IMAGE_TYPES)) {
            $filename = $user['id'] . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $file_path)) {
                // Delete old profile photo if exists
                if ($profile_photo && file_exists($profile_photo)) { @unlink($profile_photo); }
                $profile_photo = 'uploads/profiles/' . $filename;
            }
        } else {
            $error_message = 'Invalid file type. Please upload a valid image file.';
        }
    }
    
    if (empty($name)) {
        $error_message = 'Name is required.';
    } elseif (!$error_message) {
        try {
            db()->execute(
                "UPDATE users SET name = ?, bio = ?, job_title = ?, company = ?, skills = ?, batch = ?, department = ?, mobile = ?, profile_photo = ?, updated_at = NOW() WHERE id = ?",
                [$name, $bio, $job_title, $company, $skills, $batch, $department, $mobile, $profile_photo, $user['id']]
            );
            $success_message = 'Profile updated successfully!';
            // Refresh user data
            $user = Auth::getCurrentUser();
        } catch (Exception $e) {
            $error_message = 'Failed to update profile. Please try again.';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h1><i class="fas fa-user-edit text-primary"></i> Edit Profile</h1>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Profile Photo -->
                            <div class="text-center mb-4">
                                <div class="profile-photo-container">
                                    <?php if ($user['profile_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                             class="rounded-circle" id="profilePhotoPreview" width="150" height="150" style="object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle text-muted" id="profilePhotoPreview" style="font-size: 8rem;"></i>
                                    <?php endif; ?>
                                </div>
<div class="mt-3">
                                    <input type="file" class="form-control" id="profile_photo" name="profile_photo" 
                                           accept="image/*" onchange="previewPhoto(this)">
                                    <small class="form-text text-muted">Upload a profile photo (JPG, PNG, GIF - Max 5MB)</small>
                                </div>
                                <?php if ($user['profile_photo']): ?>
                                <div class="mt-2">
                                    <button type="submit" name="remove_photo" value="1" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove your profile photo?');">
                                        <i class="fas fa-trash"></i> Remove photo
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Basic Information -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    <div class="invalid-feedback">
                                        Please provide your full name.
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="batch" class="form-label">Batch/Year</label>
                                    <input type="text" class="form-control" id="batch" name="batch" 
                                           value="<?php echo htmlspecialchars($user['batch'] ?? ''); ?>" 
                                           placeholder="e.g., 2020-2024">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Field of Study / Department</label>
                                    <input type="text" class="form-control" id="department" name="department"
                                           value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"
                                           placeholder="e.g., Computer Science, Mechanical Engineering">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="mobile" class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-control" id="mobile" name="mobile"
                                           value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>"
                                           placeholder="e.g., +91 98765 43210">
                                </div>
                            </div>
                            
                            <!-- Bio -->
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" 
                                          placeholder="Tell us about yourself, your interests, achievements..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Professional Information -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="job_title" class="form-label">Job Title</label>
                                    <input type="text" class="form-control" id="job_title" name="job_title" 
                                           value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>" 
                                           placeholder="e.g., Software Engineer">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="company" class="form-label">Company</label>
                                    <input type="text" class="form-control" id="company" name="company" 
                                           value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>" 
                                           placeholder="e.g., Tech Corp">
                                </div>
                            </div>
                            
                            <!-- Skills -->
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills & Expertise</label>
                                <input type="text" class="form-control" id="skills" name="skills" 
                                       value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>"
                                       placeholder="Separate skills with commas">
                                <small class="form-text text-muted">e.g., PHP, JavaScript, MySQL, Project Management, Leadership</small>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="profile.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Profile Completion -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Profile Completion</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $completion_score = 0;
                        $total_fields = 6;
                        
                        if (!empty($user['name'])) $completion_score++;
                        if (!empty($user['bio'])) $completion_score++;
                        if (!empty($user['job_title'])) $completion_score++;
                        if (!empty($user['company'])) $completion_score++;
                        if (!empty($user['skills'])) $completion_score++;
                        if (!empty($user['profile_photo'])) $completion_score++;
                        
                        $completion_percentage = round(($completion_score / $total_fields) * 100);
                        ?>
                        
                        <div class="text-center mb-3">
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $completion_percentage; ?>%">
                                    <?php echo $completion_percentage; ?>%
                                </div>
                            </div>
                            <small class="text-muted">Complete your profile to increase visibility</small>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Profile Photo</span>
                                <?php if ($user['profile_photo']): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Bio</span>
                                <?php if (!empty($user['bio'])): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Job Title</span>
                                <?php if (!empty($user['job_title'])): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Company</span>
                                <?php if (!empty($user['company'])): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Skills</span>
                                <?php if (!empty($user['skills'])): ?>
                                    <i class="fas fa-check text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-times text-muted"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tips -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Profile Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Use a professional profile photo</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Write a compelling bio</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>List relevant skills</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Keep information up-to-date</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePhotoPreview');
                    preview.style.display = 'block';
                    preview.src = e.target.result;
                    preview.style.fontSize = '';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                const forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>

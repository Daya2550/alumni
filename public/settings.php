<?php
/**
 * User Settings Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$userId = (int)($user['id'] ?? 0);

// Load extended profile data (social links, resume)
$extras = [];
try {
    $extras = db()->fetchOne(
        "SELECT user_id, instagram, linkedin, github, website, resume_path FROM user_profile_extras WHERE user_id = ?",
        [$userId]
    ) ?: [];
} catch (Exception $e) {
    $extras = [];
}

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $job_title = sanitizeInput($_POST['job_title'] ?? '');
        $company = sanitizeInput($_POST['company'] ?? '');
        $skills = sanitizeInput($_POST['skills'] ?? '');
        $mobile = sanitizeInput($_POST['mobile'] ?? '');
        
        if (empty($name)) {
            $error_message = 'Name is required.';
        } else {
            try {
                db()->execute(
                    "UPDATE users SET name = ?, bio = ?, job_title = ?, company = ?, skills = ?, mobile = ?, updated_at = NOW() WHERE id = ?",
                    [$name, $bio, $job_title, $company, $skills, $mobile, $user['id']]
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
    
    elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } elseif (!verifyPassword($current_password, $user['password_hash'])) {
            $error_message = 'Current password is incorrect.';
        } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            $error_message = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } else {
            try {
                db()->execute(
                    "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                    [hashPassword($new_password), $user['id']]
                );
                $success_message = 'Password updated successfully!';
            } catch (Exception $e) {
                $error_message = 'Failed to update password. Please try again.';
                error_log("Password update error: " . $e->getMessage());
            }
        }
    }
    
    elseif (isset($_POST['update_privacy'])) {
        $privacy_settings = [
            'profile_visibility' => $_POST['profile_visibility'] ?? 'public',
            'email_visibility' => $_POST['email_visibility'] ?? 'private',
            'show_online_status' => isset($_POST['show_online_status']),
            'allow_messages' => $_POST['allow_messages'] ?? 'everyone'
        ];
        
        try {
            db()->execute(
                "UPDATE users SET privacy_settings = ?, updated_at = NOW() WHERE id = ?",
                [json_encode($privacy_settings), $user['id']]
            );
            $success_message = 'Privacy settings updated successfully!';
            // Refresh user data so the UI reflects the latest privacy values immediately
            $user = Auth::getCurrentUser();
        } catch (Exception $e) {
            $error_message = 'Failed to update privacy settings. Please try again.';
            error_log("Privacy settings error: " . $e->getMessage());
        }
    }
}

// Handle Social & Resume form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_social'])) {
    $instagram = sanitizeInput($_POST['instagram'] ?? '');
    $linkedin  = sanitizeInput($_POST['linkedin'] ?? '');
    $github    = sanitizeInput($_POST['github'] ?? '');
    $website   = sanitizeInput($_POST['website'] ?? '');

    // Normalize empty strings to null
    $instagram = $instagram !== '' ? $instagram : null;
    $linkedin  = $linkedin  !== '' ? $linkedin  : null;
    $github    = $github    !== '' ? $github    : null;
    $website   = $website   !== '' ? $website   : null;

    // Begin with existing resume_path if any
    $new_resume_path = $extras['resume_path'] ?? null;

    // Handle resume upload
    if (!empty($_FILES['resume_file']) && isset($_FILES['resume_file']['error']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_exts = ['pdf','doc','docx'];
        $original_name = $_FILES['resume_file']['name'];
        $tmp_path = $_FILES['resume_file']['tmp_name'];
        $size_bytes = (int)$_FILES['resume_file']['size'];
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts, true)) {
            $error_message = 'Invalid resume file type. Allowed: PDF, DOC, DOCX';
        } elseif ($size_bytes > 10 * 1024 * 1024) { // 10 MB
            $error_message = 'Resume file is too large (max 10 MB).';
        } else {
            $uploads_dir = __DIR__ . '/../uploads/profiles';
            if (!is_dir($uploads_dir)) {
                @mkdir($uploads_dir, 0775, true);
            }
            $safe_filename = 'resume_' . $userId . '_' . time() . '.' . $ext;
            $dest_path = $uploads_dir . '/' . $safe_filename;
            if (@move_uploaded_file($tmp_path, $dest_path)) {
                // Public path relative to web root
                $new_resume_path = 'uploads/profiles/' . $safe_filename;
            } else {
                $error_message = 'Failed to upload resume file.';
            }
        }
    }

    if ($error_message === '') {
        try {
            // Upsert into user_profile_extras
            db()->execute(
                "INSERT INTO user_profile_extras (user_id, instagram, linkedin, github, website, resume_path)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   instagram = VALUES(instagram),
                   linkedin = VALUES(linkedin),
                   github = VALUES(github),
                   website = VALUES(website),
                   resume_path = VALUES(resume_path),
                   updated_at = CURRENT_TIMESTAMP",
                [$userId, $instagram, $linkedin, $github, $website, $new_resume_path]
            );

            $success_message = 'Social links and resume saved successfully!';
            // Refresh $extras for immediate display
            $extras = db()->fetchOne(
                "SELECT user_id, instagram, linkedin, github, website, resume_path FROM user_profile_extras WHERE user_id = ?",
                [$userId]
            ) ?: [];
        } catch (Exception $e) {
            $error_message = 'Failed to save social links. Please try again.';
            error_log('Save social links error: ' . $e->getMessage());
        }
    }
}

    if (isset($_POST['update_notifications'])) {
        // Store notification preferences inside privacy_settings JSON
        $privacy_settings = $user['privacy_settings'] ? json_decode($user['privacy_settings'], true) : [];
        $privacy_settings['notify_messages'] = isset($_POST['notify_messages']);
        $privacy_settings['notify_jobs'] = isset($_POST['notify_jobs']);
        $privacy_settings['notify_events'] = isset($_POST['notify_events']);
        $privacy_settings['notify_news'] = isset($_POST['notify_news']);
        try {
            db()->execute(
                "UPDATE users SET privacy_settings = ?, updated_at = NOW() WHERE id = ?",
                [json_encode($privacy_settings), $userId]
            );
            $success_message = 'Notification preferences updated!';
            $user = Auth::getCurrentUser();
        } catch (Exception $e) {
            $error_message = 'Failed to update notification preferences.';
        }
    }

// Get privacy settings
$privacy_settings = $user['privacy_settings'] ? json_decode($user['privacy_settings'], true) : [
    'profile_visibility' => 'public',
    'email_visibility' => 'private',
    'show_online_status' => true,
    'allow_messages' => 'everyone'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-cog text-primary"></i> Settings</h1>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Settings Tabs -->
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                            <i class="fas fa-user"></i> Profile
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                            <i class="fas fa-lock"></i> Password
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                            <i class="fas fa-shield-alt"></i> Privacy
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                            <i class="fas fa-bell"></i> Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab">
                            <i class="fas fa-share-nodes"></i> Social & Resume
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabContent">
                    <!-- Profile Settings -->
                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-user"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                            <small class="form-text text-muted">Email cannot be changed</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="mobile" class="form-label">Mobile Number</label>
                                            <input type="tel" class="form-control" id="mobile" name="mobile"
                                                   value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>"
                                                   placeholder="e.g., +91 98765 43210">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bio" class="form-label">Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4" 
                                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="job_title" class="form-label">Job Title</label>
                                            <input type="text" class="form-control" id="job_title" name="job_title" 
                                                   value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="company" class="form-label">Company</label>
                                            <input type="text" class="form-control" id="company" name="company" 
                                                   value="<?php echo htmlspecialchars($user['company'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="skills" class="form-label">Skills</label>
                                        <input type="text" class="form-control" id="skills" name="skills" 
                                               value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>"
                                               placeholder="Separate skills with commas">
                                        <small class="form-text text-muted">e.g., PHP, JavaScript, MySQL, Project Management</small>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Settings -->
                    <div class="tab-pane fade" id="password" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-lock"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        <small class="form-text text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                    </div>
                                    
                                    <button type="submit" name="update_password" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Privacy Settings -->
                    <div class="tab-pane fade" id="privacy" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt"></i> Privacy Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="profile_visibility" class="form-label">Profile Visibility</label>
                                        <select class="form-select" id="profile_visibility" name="profile_visibility">
                                            <option value="public" <?php echo $privacy_settings['profile_visibility'] === 'public' ? 'selected' : ''; ?>>Public - Everyone can see my profile</option>
                                            <option value="batch" <?php echo $privacy_settings['profile_visibility'] === 'batch' ? 'selected' : ''; ?>>Batch Only - Only my batch can see my profile</option>
                                            <option value="private" <?php echo $privacy_settings['profile_visibility'] === 'private' ? 'selected' : ''; ?>>Private - Only I can see my profile</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email_visibility" class="form-label">Email Visibility</label>
                                        <select class="form-select" id="email_visibility" name="email_visibility">
                                            <option value="public" <?php echo $privacy_settings['email_visibility'] === 'public' ? 'selected' : ''; ?>>Public - Everyone can see my email</option>
                                            <option value="batch" <?php echo $privacy_settings['email_visibility'] === 'batch' ? 'selected' : ''; ?>>Batch Only - Only my batch can see my email</option>
                                            <option value="private" <?php echo $privacy_settings['email_visibility'] === 'private' ? 'selected' : ''; ?>>Private - Only I can see my email</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="allow_messages" class="form-label">Who can send me messages?</label>
                                        <select class="form-select" id="allow_messages" name="allow_messages">
                                            <option value="everyone" <?php echo $privacy_settings['allow_messages'] === 'everyone' ? 'selected' : ''; ?>>Everyone</option>
                                            <option value="batch" <?php echo $privacy_settings['allow_messages'] === 'batch' ? 'selected' : ''; ?>>Batch members only</option>
                                            <option value="none" <?php echo $privacy_settings['allow_messages'] === 'none' ? 'selected' : ''; ?>>No one</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="show_online_status" name="show_online_status" 
                                               <?php echo $privacy_settings['show_online_status'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_online_status">
                                            Show online status to others
                                        </label>
                                    </div>
                                    
                                    <button type="submit" name="update_privacy" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Privacy Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notification Settings -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bell"></i> Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <?php $ps = $privacy_settings; ?>
                                <form method="POST">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_messages" name="notify_messages" <?php echo !empty($ps['notify_messages']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notify_messages">
                                            Email notifications for new messages
                                        </label>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_jobs" name="notify_jobs" <?php echo !empty($ps['notify_jobs']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notify_jobs">
                                            Email notifications for new job postings
                                        </label>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_events" name="notify_events" <?php echo !empty($ps['notify_events']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notify_events">
                                            Email notifications for upcoming events
                                        </label>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="notify_news" name="notify_news" <?php echo !empty($ps['notify_news']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notify_news">
                                            Email notifications for news updates
                                        </label>
                                    </div>
                                    
                                    <button type="submit" name="update_notifications" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Notification Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Social & Resume -->
                    <div class="tab-pane fade" id="social" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-share-nodes"></i> Social Links & Resume</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="instagram" class="form-label">Instagram URL</label>
                                            <input type="url" class="form-control" id="instagram" name="instagram" value="<?php echo htmlspecialchars($extras['instagram'] ?? ''); ?>" placeholder="https://instagram.com/username">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="linkedin" class="form-label">LinkedIn URL</label>
                                            <input type="url" class="form-control" id="linkedin" name="linkedin" value="<?php echo htmlspecialchars($extras['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="github" class="form-label">GitHub URL</label>
                                            <input type="url" class="form-control" id="github" name="github" value="<?php echo htmlspecialchars($extras['github'] ?? ''); ?>" placeholder="https://github.com/username">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($extras['website'] ?? ''); ?>" placeholder="https://example.com">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="resume_file" class="form-label">Upload Resume (PDF/DOC/DOCX)</label>
                                        <input type="file" class="form-control" id="resume_file" name="resume_file" accept=".pdf,.doc,.docx">
                                        <?php if (!empty($extras['resume_path'])): ?>
                                            <div class="mt-2">
                                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($extras['resume_path']); ?>" target="_blank" rel="noopener">
                                                    <i class="fas fa-file"></i> View/Download current resume
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <button type="submit" name="update_social" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Social & Resume
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Account Information -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Member Since</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Last Updated</strong><br>
                            <span class="text-muted"><?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Account Status</strong><br>
                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="profile.php" class="btn btn-outline-primary">
                                <i class="fas fa-user"></i> View Profile
                            </a>
                            <button onclick="exportData()" class="btn btn-outline-secondary">
                                <i class="fas fa-download"></i> Export Data
                            </button>
                            <button onclick="deleteAccount()" class="btn btn-outline-danger">
                                <i class="fas fa-trash"></i> Delete Account
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
        function exportData() {
            if (!confirm('Export your personal data as JSON?')) return;
            fetch('api/user_export.php', {credentials:'same-origin'})
            .then(r=>r.ok?r.json():Promise.reject())
            .then(data=>{
                const blob = new Blob([JSON.stringify(data, null, 2)], {type:'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'my_data.json'; a.click();
                URL.revokeObjectURL(url);
            }).catch(()=> alert('Failed to export.'));
        }
        
        function deleteAccount() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                const second = prompt('Type DELETE to confirm account deletion:');
                if (second !== 'DELETE') { return; }
                fetch('api/user_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: 'self=1'
                }).then(r => r.json()).then(resp => {
                    if (resp && resp.success) {
                        alert('Your account has been deleted.');
                        window.location.href = 'index.php';
                    } else {
                        alert(resp.message || 'Failed to delete account.');
                    }
                }).catch(() => alert('Failed to delete account.'));
            }
        }
        
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Activate Social tab when URL has #social or after saving that section
        (function() {
            function activateSocialTabIfNeeded() {
                if (location.hash === '#social') {
                    var tabTrigger = document.querySelector('#social-tab');
                    var tabPane = document.querySelector('#social');
                    if (tabTrigger && tabPane) {
                        var trigger = new bootstrap.Tab(tabTrigger);
                        trigger.show();
                    }
                }
            }
            window.addEventListener('hashchange', activateSocialTabIfNeeded);
            document.addEventListener('DOMContentLoaded', activateSocialTabIfNeeded);
            // If this request saved social form, force hash so user stays on Social tab
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_social'])): ?>
            if (location.hash !== '#social') {
                history.replaceState(null, '', '#social');
            }
            <?php endif; ?>
        })();
    </script>
</body>
</html>

                                <h5><i class="fas fa-lock"></i> Change Password</h5>

                            </div>

                            <div class="card-body">

                                <form method="POST">

                                    <div class="mb-3">

                                        <label for="current_password" class="form-label">Current Password *</label>

                                        <input type="password" class="form-control" id="current_password" name="current_password" required>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <label for="new_password" class="form-label">New Password *</label>

                                        <input type="password" class="form-control" id="new_password" name="new_password" 

                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>

                                        <small class="form-text text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>

                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 

                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>

                                    </div>

                                    

                                    <button type="submit" name="update_password" class="btn btn-primary">

                                        <i class="fas fa-key"></i> Update Password

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                    

                    <!-- Privacy Settings -->

                    <div class="tab-pane fade" id="privacy" role="tabpanel">

                        <div class="card">

                            <div class="card-header">

                                <h5><i class="fas fa-shield-alt"></i> Privacy Settings</h5>

                            </div>

                            <div class="card-body">

                                <form method="POST">

                                    <div class="mb-3">

                                        <label for="profile_visibility" class="form-label">Profile Visibility</label>

                                        <select class="form-select" id="profile_visibility" name="profile_visibility">

                                            <option value="public" <?php echo $privacy_settings['profile_visibility'] === 'public' ? 'selected' : ''; ?>>Public - Everyone can see my profile</option>

                                            <option value="batch" <?php echo $privacy_settings['profile_visibility'] === 'batch' ? 'selected' : ''; ?>>Batch Only - Only my batch can see my profile</option>

                                            <option value="private" <?php echo $privacy_settings['profile_visibility'] === 'private' ? 'selected' : ''; ?>>Private - Only I can see my profile</option>

                                        </select>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <label for="email_visibility" class="form-label">Email Visibility</label>

                                        <select class="form-select" id="email_visibility" name="email_visibility">

                                            <option value="public" <?php echo $privacy_settings['email_visibility'] === 'public' ? 'selected' : ''; ?>>Public - Everyone can see my email</option>

                                            <option value="batch" <?php echo $privacy_settings['email_visibility'] === 'batch' ? 'selected' : ''; ?>>Batch Only - Only my batch can see my email</option>

                                            <option value="private" <?php echo $privacy_settings['email_visibility'] === 'private' ? 'selected' : ''; ?>>Private - Only I can see my email</option>

                                        </select>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <label for="allow_messages" class="form-label">Who can send me messages?</label>

                                        <select class="form-select" id="allow_messages" name="allow_messages">

                                            <option value="everyone" <?php echo $privacy_settings['allow_messages'] === 'everyone' ? 'selected' : ''; ?>>Everyone</option>

                                            <option value="batch" <?php echo $privacy_settings['allow_messages'] === 'batch' ? 'selected' : ''; ?>>Batch members only</option>

                                            <option value="none" <?php echo $privacy_settings['allow_messages'] === 'none' ? 'selected' : ''; ?>>No one</option>

                                        </select>

                                    </div>

                                    

                                    <div class="mb-3 form-check">

                                        <input type="checkbox" class="form-check-input" id="show_online_status" name="show_online_status" 

                                               <?php echo $privacy_settings['show_online_status'] ? 'checked' : ''; ?>>

                                        <label class="form-check-label" for="show_online_status">

                                            Show online status to others

                                        </label>

                                    </div>

                                    

                                    <button type="submit" name="update_privacy" class="btn btn-primary">

                                        <i class="fas fa-save"></i> Update Privacy Settings

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                    

                    <!-- Notification Settings -->

                    <div class="tab-pane fade" id="notifications" role="tabpanel">

                        <div class="card">

                            <div class="card-header">

                                <h5><i class="fas fa-bell"></i> Notification Preferences</h5>

                            </div>

                            <div class="card-body">

                                <form>

                                    <div class="mb-3 form-check">

                                        <input type="checkbox" class="form-check-input" id="email_notifications" checked>

                                        <label class="form-check-label" for="email_notifications">

                                            Email notifications for new messages

                                        </label>

                                    </div>

                                    

                                    <div class="mb-3 form-check">

                                        <input type="checkbox" class="form-check-input" id="job_notifications" checked>

                                        <label class="form-check-label" for="job_notifications">

                                            Email notifications for new job postings

                                        </label>

                                    </div>

                                    

                                    <div class="mb-3 form-check">

                                        <input type="checkbox" class="form-check-input" id="event_notifications" checked>

                                        <label class="form-check-label" for="event_notifications">

                                            Email notifications for upcoming events

                                        </label>

                                    </div>

                                    

                                    <div class="mb-3 form-check">

                                        <input type="checkbox" class="form-check-input" id="news_notifications" checked>

                                        <label class="form-check-label" for="news_notifications">

                                            Email notifications for news updates

                                        </label>

                                    </div>

                                    

                                    <button type="submit" class="btn btn-primary">

                                        <i class="fas fa-save"></i> Update Notification Preferences

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            

            <div class="col-lg-4">

                <!-- Account Information -->

                <div class="card">

                    <div class="card-header">

                        <h5><i class="fas fa-info-circle"></i> Account Information</h5>

                    </div>

                    <div class="card-body">

                        <div class="mb-3">

                            <strong>Member Since</strong><br>

                            <span class="text-muted"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>

                        </div>

                        

                        <div class="mb-3">

                            <strong>Last Updated</strong><br>

                            <span class="text-muted"><?php echo date('F j, Y g:i A', strtotime($user['updated_at'])); ?></span>

                        </div>

                        

                        <div class="mb-3">

                            <strong>Account Status</strong><br>

                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">

                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>

                            </span>

                        </div>

                        

                        <div class="mb-3">

                            <strong>Email Verification</strong><br>

                            <span class="badge bg-<?php echo $user['email_verified'] ? 'success' : 'warning'; ?>">

                                <?php echo $user['email_verified'] ? 'Verified' : 'Pending'; ?>

                            </span>

                        </div>

                    </div>

                </div>

                

                <!-- Quick Actions -->

                <div class="card mt-3">

                    <div class="card-header">

                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>

                    </div>

                    <div class="card-body">

                        <div class="d-grid gap-2">

                            <a href="profile.php" class="btn btn-outline-primary">

                                <i class="fas fa-user"></i> View Profile

                            </a>

                            <button onclick="exportData()" class="btn btn-outline-secondary">

                                <i class="fas fa-download"></i> Export Data

                            </button>

                            <button onclick="deleteAccount()" class="btn btn-outline-danger">

                                <i class="fas fa-trash"></i> Delete Account

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

        function exportData() {

            if (confirm('Export your personal data? This will download a JSON file with your profile information.')) {

                // TODO: Implement data export

                alert('Data export functionality coming soon!');

            }

        }

        

        function deleteAccount() {

            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {

                if (confirm('This will permanently delete your account and all associated data. Type "DELETE" to confirm.')) {

                    // TODO: Implement account deletion

                    alert('Account deletion functionality coming soon!');

                }

            }

        }

        

        // Password confirmation validation

        document.getElementById('confirm_password')?.addEventListener('input', function() {

            const newPassword = document.getElementById('new_password').value;

            const confirmPassword = this.value;

            

            if (newPassword !== confirmPassword) {

                this.setCustomValidity('Passwords do not match');

            } else {

                this.setCustomValidity('');

            }

        });

    </script>

</body>

</html>



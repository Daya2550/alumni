<?php
/**
 * Profile View Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$profile_id = $_GET['id'] ?? $user['id']; // Default to current user if no ID provided

// Get profile user data
$profile_user = db()->fetchOne(
    "SELECT * FROM users WHERE id = ? AND is_active = 1",
    [$profile_id]
);

if (!$profile_user) {
    header('HTTP/1.1 404 Not Found');
    echo 'Profile not found';
    exit;
}

$is_own_profile = $profile_id === $user['id'];

// Get user's recent activity
$recent_activity = [];

// Get recent news posts by this user
$recent_posts = db()->fetchAll(
    "SELECT id, title, published_at FROM news_posts 
     WHERE author_id = ? AND status = 'published' 
     ORDER BY published_at DESC LIMIT 5",
    [$profile_id]
);

// Get recent job applications (if viewing own profile)
if ($is_own_profile) {
    $recent_applications = db()->fetchAll(
        "SELECT ja.applied_at, jo.title as job_title, jo.company, ja.status
         FROM job_applications ja
         JOIN job_opportunities jo ON ja.job_id = jo.id
         WHERE ja.user_id = ?
         ORDER BY ja.applied_at DESC LIMIT 5",
        [$profile_id]
    );
}

// Get recent event RSVPs
$recent_rsvps = db()->fetchAll(
    "SELECT er.status, er.rsvped_at, e.title as event_title, e.event_date
     FROM event_rsvps er
     JOIN events e ON er.event_id = e.id
     WHERE er.user_id = ?
     ORDER BY er.rsvped_at DESC LIMIT 5",
    [$profile_id]
);

// Get skills array
$skills = $profile_user['skills'] ? explode(',', $profile_user['skills']) : [];

// Get privacy settings
$privacy_settings = $profile_user['privacy_settings'] ? json_decode($profile_user['privacy_settings'], true) : [];

// Enforce profile visibility
$profileVisibility = $privacy_settings['profile_visibility'] ?? 'public';
if (!$is_own_profile) {
    if ($profileVisibility === 'private') {
        header('HTTP/1.1 403 Forbidden');
        echo 'This profile is private.';
        exit;
    }
    if ($profileVisibility === 'batch' && !empty($profile_user['batch']) && ($user['batch'] ?? null) !== $profile_user['batch'] && !Auth::hasAnyRole(['admin','staff'])) {
        header('HTTP/1.1 403 Forbidden');
        echo 'This profile is visible only to batch members.';
        exit;
    }
}
// Load extras for social links and resume (tolerate missing table)
$extras = [];
try {
    $extras = db()->fetchOne(
        "SELECT instagram, linkedin, github, website, resume_path FROM user_profile_extras WHERE user_id = ?",
        [$profile_id]
    ) ?: [];
} catch (Exception $e) {
    $extras = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['name']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <!-- Profile Header -->
        <div class="row">
            <div class="col-12">
                <div class="profile-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if ($profile_user['profile_photo']): ?>
                                    <img src="<?php echo htmlspecialchars($profile_user['profile_photo']); ?>" 
                                         class="profile-avatar" alt="Profile Photo">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-white" style="font-size: 8rem;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h1 class="text-white mb-2"><?php echo htmlspecialchars($profile_user['name']); ?></h1>
                                <div class="mb-2">
                                    <?php
                                    $role_colors = [
                                        'student' => 'light',
                                        'alumni' => 'success',
                                        'staff' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $role_colors[$profile_user['role']] ?? 'secondary'; ?> me-2">
                                        <?php echo ucfirst($profile_user['role']); ?>
                                    </span>
                                    
                                    <?php if ($profile_user['batch']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($profile_user['batch']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($profile_user['department'])): ?>
                                        <span class="badge bg-secondary ms-2"><i class="fas fa-building-columns"></i> <?php echo htmlspecialchars($profile_user['department']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($profile_user['job_title'] || $profile_user['company']): ?>
                                    <div class="text-light mb-2">
                                        <?php if ($profile_user['job_title']): ?>
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($profile_user['job_title']); ?>
                                        <?php endif; ?>
                                        <?php if ($profile_user['company']): ?>
                                            <?php if ($profile_user['job_title']): ?> at <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($profile_user['company']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($extras)): ?>
                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                        <?php if (!empty($extras['instagram'])): ?>
                                            <a class="btn btn-sm btn-light" style="opacity:.95" href="<?php echo htmlspecialchars($extras['instagram']); ?>" target="_blank" rel="noopener" title="Instagram">
                                                <i class="fab fa-instagram"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($extras['linkedin'])): ?>
                                            <a class="btn btn-sm btn-light" style="opacity:.95" href="<?php echo htmlspecialchars($extras['linkedin']); ?>" target="_blank" rel="noopener" title="LinkedIn">
                                                <i class="fab fa-linkedin"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($extras['github'])): ?>
                                            <a class="btn btn-sm btn-light" style="opacity:.95" href="<?php echo htmlspecialchars($extras['github']); ?>" target="_blank" rel="noopener" title="GitHub">
                                                <i class="fab fa-github"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($extras['website'])): ?>
                                            <a class="btn btn-sm btn-light" style="opacity:.95" href="<?php echo htmlspecialchars($extras['website']); ?>" target="_blank" rel="noopener" title="Website">
                                                <i class="fas fa-globe"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($extras['resume_path'])): ?>
<a class="btn btn-sm btn-light" href="<?php echo htmlspecialchars($extras['resume_path']); ?>" download="<?php echo htmlspecialchars(basename($extras['resume_path'])); ?>" rel="noopener" title="Download Resume">
                                                <i class="fas fa-file-arrow-down me-1"></i> Download Resume
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="d-grid gap-2">
                                    <?php if ($is_own_profile): ?>
                                        <a href="settings.php#social" class="btn btn-light">
                                            <i class="fas fa-share-nodes"></i> Social & Resume
                                        </a>
                                        <a href="edit_profile.php" class="btn btn-outline-light">
                                            <i class="fas fa-edit"></i> Edit Profile
                                        </a>
                                    <?php else: ?>
                                        <a href="message.php?user=<?php echo $profile_user['id']; ?>" class="btn btn-light">
                                            <i class="fas fa-comments"></i> Send Message
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button onclick="shareProfile()" class="btn btn-outline-light">
                                        <i class="fas fa-share"></i> Share Profile
                                    </button>
                                    <?php if (Auth::hasAnyRole(['admin','staff']) && $profile_user['role'] !== 'admin'): ?>
                                        <button class="btn btn-danger" onclick="deleteUser('<?php echo $profile_user['id']; ?>')">
                                            <i class="fas fa-user-slash"></i> Delete Account
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-8">
                <!-- About Section -->
                <?php if ($profile_user['bio']): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-user"></i> About</h5>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Skills Section -->
                <?php if (!empty($skills)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5><i class="fas fa-tools"></i> Skills & Expertise</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($skills as $skill): ?>
                                <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars(trim($skill)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_posts) && empty($recent_rsvps) && ($is_own_profile ? empty($recent_applications) : true)): ?>
                            <p class="text-muted">No recent activity.</p>
                        <?php else: ?>
                            <div class="timeline">
                                <!-- Recent Posts -->
                                <?php foreach ($recent_posts as $post): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-primary"></div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1">
                                                <a href="news_post.php?id=<?php echo $post['id']; ?>" class="text-decoration-none">
                                                    Posted: <?php echo htmlspecialchars($post['title']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($post['published_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Recent RSVPs -->
                                <?php foreach ($recent_rsvps as $rsvp): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1">
                                                <?php
                                                $status_icons = [
                                                    'attending' => 'fas fa-check text-success',
                                                    'maybe' => 'fas fa-question text-warning',
                                                    'not_attending' => 'fas fa-times text-danger'
                                                ];
                                                ?>
                                                <i class="<?php echo $status_icons[$rsvp['status']] ?? 'fas fa-calendar'; ?>"></i>
                                                RSVP'd for: <?php echo htmlspecialchars($rsvp['event_title']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($rsvp['rsvped_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Recent Job Applications (own profile only) -->
                                <?php if ($is_own_profile && !empty($recent_applications)): ?>
                                    <?php foreach ($recent_applications as $app): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker bg-warning"></div>
                                            <div class="timeline-content">
                                                <h6 class="mb-1">
                                                    Applied for: <?php echo htmlspecialchars($app['job_title']); ?>
                                                    at <?php echo htmlspecialchars($app['company']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                                    <span class="badge bg-<?php echo $app['status'] === 'applied' ? 'primary' : ($app['status'] === 'shortlisted' ? 'success' : 'danger'); ?> ms-2">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Contact Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-address-card"></i> Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong><i class="fas fa-envelope"></i> Email</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($profile_user['email']); ?></span>
                        </div>
                        <?php if (!empty($profile_user['mobile'])): ?>
                            <div class="mb-3">
                                <strong><i class="fas fa-phone"></i> Mobile</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($profile_user['mobile']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($profile_user['job_title']): ?>
                            <div class="mb-3">
                                <strong><i class="fas fa-briefcase"></i> Job Title</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($profile_user['job_title']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($profile_user['company']): ?>
                            <div class="mb-3">
                                <strong><i class="fas fa-building"></i> Company</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($profile_user['company']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($profile_user['batch']): ?>
                            <div class="mb-3">
                                <strong><i class="fas fa-graduation-cap"></i> Batch</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($profile_user['batch']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($profile_user['department'])): ?>
                            <div class="mb-3">
                                <strong><i class="fas fa-building-columns"></i> Department</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($profile_user['department']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <strong><i class="fas fa-calendar"></i> Member Since</strong><br>
                            <span class="text-muted"><?php echo date('F Y', strtotime($profile_user['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($extras['instagram']) || !empty($extras['linkedin']) || !empty($extras['github']) || !empty($extras['website']) || !empty($extras['resume_path'])): ?>
                            <hr>
                            <div class="mb-2">
                                <strong><i class="fas fa-share-nodes"></i> Links</strong>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($extras['instagram'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($extras['instagram']); ?>" target="_blank" rel="noopener"><i class="fab fa-instagram"></i> Instagram</a>
                                <?php endif; ?>
                                <?php if (!empty($extras['linkedin'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($extras['linkedin']); ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin"></i> LinkedIn</a>
                                <?php endif; ?>
                                <?php if (!empty($extras['github'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($extras['github']); ?>" target="_blank" rel="noopener"><i class="fab fa-github"></i> GitHub</a>
                                <?php endif; ?>
                                <?php if (!empty($extras['website'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($extras['website']); ?>" target="_blank" rel="noopener"><i class="fas fa-globe"></i> Website</a>
                                <?php endif; ?>
                                <?php if (!empty($extras['resume_path'])): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($extras['resume_path']); ?>" download="<?php echo htmlspecialchars(basename($extras['resume_path'])); ?>" rel="noopener"><i class="fas fa-download"></i> Download Resume</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (!$is_own_profile): ?>
                                <a href="message.php?user=<?php echo $profile_user['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-comments"></i> Send Message
                                </a>
                            <?php endif; ?>
                            
                            <a href="directory.php" class="btn btn-outline-primary">
                                <i class="fas fa-users"></i> Browse Directory
                            </a>
                            
                            <button onclick="shareProfile()" class="btn btn-outline-secondary">
                                <i class="fas fa-share"></i> Share Profile
                            </button>
                            
                            <?php if ($is_own_profile): ?>
                                <a href="settings.php" class="btn btn-outline-warning">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                            <?php endif; ?>
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
        function shareProfile() {
            const url = window.location.href;
            const title = '<?php echo addslashes($profile_user['name']); ?> - Alumni Profile';
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Profile link copied to clipboard!');
                });
            }
        }

        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this account? This action cannot be undone.')) return;
            fetch('api/user_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: 'target_id=' + encodeURIComponent(userId)
            }).then(r => r.json()).then(resp => {
                if (resp && resp.success) {
                    alert('Account deleted successfully.');
                    window.location.href = 'directory.php';
                } else {
                    alert(resp.message || 'Failed to delete account.');
                }
            }).catch(() => alert('Failed to delete account.'));
        }
    </script>
    
    <style>
        /* Improve contrast and readability in profile header */
        .profile-header .text-white-50, .profile-header .text-light { color: rgba(255,255,255,0.95) !important; }
        .profile-header .btn-outline-light { color:#fff; border-color: rgba(255,255,255,0.9); }
        .profile-header .btn-outline-light:hover { background: rgba(255,255,255,0.15); }
        .profile-header .btn-light { background:#ffffff; color:#124170; border-color:#ffffff; }
        .profile-header .badge { box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
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
            margin-bottom: 20px;
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
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #007bff;
        }
    </style>
</body>
</html>

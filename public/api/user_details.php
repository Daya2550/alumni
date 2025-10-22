<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user = Auth::getCurrentUser();
if (!Auth::hasAnyRole(['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$user_id = $_GET['id'] ?? '';
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Get user details
    $user = db()->fetchOne(
        "SELECT id, name, email, mobile, role, batch, department, profile_photo, created_at, updated_at, is_active
         FROM users 
         WHERE id = ?",
        [$user_id]
    );

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Get additional user statistics (with error handling for missing tables)
    $stats = [
        'notices_created' => 0,
        'news_posts' => 0,
        'job_posts' => 0,
        'event_posts' => 0,
        'gallery_uploads' => 0,
        'messages_sent' => 0,
        'last_login' => null
    ];

    // Try to get statistics from existing tables
    try {
        $notices_result = db()->fetchOne("SELECT COUNT(*) as count FROM notices WHERE created_by = ?", [$user_id]);
        if ($notices_result) $stats['notices_created'] = $notices_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $news_result = db()->fetchOne("SELECT COUNT(*) as count FROM news WHERE created_by = ?", [$user_id]);
        if ($news_result) $stats['news_posts'] = $news_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $jobs_result = db()->fetchOne("SELECT COUNT(*) as count FROM jobs WHERE created_by = ?", [$user_id]);
        if ($jobs_result) $stats['job_posts'] = $jobs_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $events_result = db()->fetchOne("SELECT COUNT(*) as count FROM events WHERE created_by = ?", [$user_id]);
        if ($events_result) $stats['event_posts'] = $events_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $gallery_result = db()->fetchOne("SELECT COUNT(*) as count FROM gallery WHERE uploaded_by = ?", [$user_id]);
        if ($gallery_result) $stats['gallery_uploads'] = $gallery_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $messages_result = db()->fetchOne("SELECT COUNT(*) as count FROM messages WHERE sender_id = ?", [$user_id]);
        if ($messages_result) $stats['messages_sent'] = $messages_result['count'];
    } catch (Exception $e) { /* Table might not exist */ }

    try {
        $sessions_result = db()->fetchOne("SELECT MAX(created_at) as last_login FROM user_sessions WHERE user_id = ?", [$user_id]);
        if ($sessions_result) $stats['last_login'] = $sessions_result['last_login'];
    } catch (Exception $e) { /* Table might not exist */ }

    // Get recent activity (with error handling for missing tables)
    $recent_activity = [];
    
    try {
        $recent_activity = db()->fetchAll(
            "SELECT 'notice' as type, title, created_at FROM notices WHERE created_by = ? 
             UNION ALL
             SELECT 'news' as type, title, created_at FROM news WHERE created_by = ?
             UNION ALL
             SELECT 'job' as type, title, created_at FROM jobs WHERE created_by = ?
             UNION ALL
             SELECT 'event' as type, title, created_at FROM events WHERE created_by = ?
             ORDER BY created_at DESC LIMIT 10",
            [$user_id, $user_id, $user_id, $user_id]
        );
    } catch (Exception $e) {
        // If tables don't exist, just return empty array
        $recent_activity = [];
    }

    // Generate HTML
    ob_start();
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="text-center mb-4">
                <?php if ($user['profile_photo']): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                         class="rounded-circle mb-3" width="120" height="120" style="object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 120px; height: 120px;">
                        <i class="fas fa-user fa-3x text-white"></i>
                    </div>
                <?php endif; ?>
                <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'dark' : ($user['role'] === 'staff' ? 'secondary' : ($user['role'] === 'alumni' ? 'warning' : 'info')); ?> fs-6">
                    <?php echo ucfirst($user['role']); ?>
                </span>
                <div class="mt-2">
                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Basic Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <?php if ($user['mobile']): ?>
                        <tr>
                            <td><strong>Mobile:</strong></td>
                            <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($user['batch']): ?>
                        <tr>
                            <td><strong>Batch:</strong></td>
                            <td><?php echo htmlspecialchars($user['batch']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($user['department']): ?>
                        <tr>
                            <td><strong>Department:</strong></td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Joined:</strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></td>
                        </tr>
                        <?php if ($stats['last_login']): ?>
                        <tr>
                            <td><strong>Last Login:</strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($stats['last_login'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-bullhorn fa-2x text-primary mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['notices_created']; ?></h5>
                            <small class="text-muted">Notices</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-newspaper fa-2x text-info mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['news_posts']; ?></h5>
                            <small class="text-muted">News Posts</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-briefcase fa-2x text-success mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['job_posts']; ?></h5>
                            <small class="text-muted">Job Posts</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['event_posts']; ?></h5>
                            <small class="text-muted">Events</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-images fa-2x text-secondary mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['gallery_uploads']; ?></h5>
                            <small class="text-muted">Gallery Uploads</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-comments fa-2x text-primary mb-2"></i>
                            <h5 class="mb-0"><?php echo $stats['messages_sent']; ?></h5>
                            <small class="text-muted">Messages Sent</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($recent_activity)): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-<?php echo $activity['type'] === 'notice' ? 'bullhorn' : ($activity['type'] === 'news' ? 'newspaper' : ($activity['type'] === 'job' ? 'briefcase' : 'calendar')); ?> text-<?php echo $activity['type'] === 'notice' ? 'primary' : ($activity['type'] === 'news' ? 'info' : ($activity['type'] === 'job' ? 'success' : 'warning')); ?> me-2"></i>
                                    <span class="fw-bold"><?php echo htmlspecialchars($activity['title']); ?></span>
                                    <span class="badge bg-light text-dark ms-2"><?php echo ucfirst($activity['type']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    error_log('User details error: ' . $e->getMessage());
    error_log('User details error trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Failed to load user details: ' . $e->getMessage()]);
}
?>

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
    // Get basic user details only
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

    // Generate simple HTML
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
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info"></i> User Information</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">This user is registered in the system with the role of <strong><?php echo ucfirst($user['role']); ?></strong>.</p>
                    <p class="text-muted">Account status: <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></p>
                    <?php if ($user['batch']): ?>
                        <p class="text-muted">Batch: <strong><?php echo htmlspecialchars($user['batch']); ?></strong></p>
                    <?php endif; ?>
                    <?php if ($user['department']): ?>
                        <p class="text-muted">Department: <strong><?php echo htmlspecialchars($user['department']); ?></strong></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    error_log('User details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load user details: ' . $e->getMessage()]);
}
?>





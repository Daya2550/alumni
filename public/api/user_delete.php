<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current = Auth::getCurrentUser();
$target_id = $_POST['target_id'] ?? '';
$self_delete = isset($_POST['self']) && $_POST['self'] === '1';

if ($self_delete) {
    // Only allow self for student, alumni, staff
    if (!in_array($current['role'], ['student','alumni','staff'])) {
        echo json_encode(['success' => false, 'message' => 'Not allowed']);
        exit;
    }
    $target_id = $current['id'];
} else {
    // Admin/staff may delete other accounts (not admins)
    if (!Auth::hasAnyRole(['admin','staff'])) {
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
    if ($target_id === '' ) {
        echo json_encode(['success' => false, 'message' => 'Missing target']);
        exit;
    }
}

// Fetch target user
$target = db()->fetchOne("SELECT id, role FROM users WHERE id = ? AND is_active = 1", [$target_id]);
if (!$target) {
    echo json_encode(['success' => false, 'message' => 'User not found or already inactive']);
    exit;
}

// Prevent deleting admins except self-admin via higher process
if ($target['role'] === 'admin' && !$self_delete) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete admin accounts']);
    exit;
}

try {
    db()->beginTransaction();

    // Clean up user files (optional - only if doing hard delete)
    // For soft delete, files are preserved but can be cleaned up later if needed
    // $files_deleted = FileCleanup::cleanupUserFiles($target_id);

    // Soft delete: set is_active = 0 and scrub PII minimal
    db()->execute(
        "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?",
        [$target_id]
    );

    // Optional: cascade cleanups (messages, sessions)
    db()->execute("DELETE FROM user_sessions WHERE user_id = ?", [$target_id]);

    // Keep historical references intact (posts, RSVPs, etc.)

    db()->commit();
    
    error_log("User {$target_id} deactivated (soft delete).");

    // If self-deleted, log out
    if ($self_delete) {
        Auth::logout();
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    db()->rollback();
    error_log('Delete user failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Deletion failed']);
}
?>



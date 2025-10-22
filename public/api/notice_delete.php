    <?php
/**
 * Delete Notice API
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Require login and admin/staff role
Auth::requireLogin();
if (!Auth::hasAnyRole(['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['notice_id']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Verify CSRF token
if (!Auth::verifyCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$notice_id = intval($input['notice_id']);
$user = Auth::getCurrentUser();

try {
    // Check if notice exists and user has permission to delete it
    $notice = db()->fetchOne(
        "SELECT id, posted_by, attachments FROM notices WHERE id = ?",
        [$notice_id]
    );
    
    if (!$notice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notice not found']);
        exit;
    }
    
    // Check if user can delete this notice (admin can delete any, staff can delete their own)
    if ($user['role'] !== 'admin' && $notice['posted_by'] !== $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own notices']);
        exit;
    }
    
    // Clean up associated files using centralized system
    $files_deleted = FileCleanup::cleanupNoticeFiles($notice_id);
    
    // Delete notice reads
    db()->execute("DELETE FROM notice_reads WHERE notice_id = ?", [$notice_id]);
    
    // Delete the notice
    db()->execute("DELETE FROM notices WHERE id = ?", [$notice_id]);
    
    error_log("Notice {$notice_id} deleted. Cleaned up {$files_deleted} files.");
    
    echo json_encode(['success' => true, 'message' => 'Notice deleted successfully']);
    
} catch (Exception $e) {
    error_log("Notice deletion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete notice']);
}
?>


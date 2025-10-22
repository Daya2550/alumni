<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

$job_id = intval($input['job_id'] ?? 0);
$csrf = $input['csrf_token'] ?? '';

if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Job ID required']);
    exit;
}

if (!Auth::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $job = db()->fetchOne("SELECT id, posted_by, company_logo FROM job_opportunities WHERE id = ?", [$job_id]);
    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job not found']);
        exit;
    }

    $isPrivileged = Auth::hasAnyRole(['staff','admin']);
    $isAuthor = ($job['posted_by'] === $user['id']);
    if (!($isPrivileged || $isAuthor)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own job']);
        exit;
    }

    // Clean up associated files using centralized system
    $files_deleted = FileCleanup::cleanupJobFiles($job_id);

    // Delete job (this will cascade delete attachments via FK)
    db()->execute("DELETE FROM job_opportunities WHERE id = ?", [$job_id]);
    
    error_log("Job {$job_id} deleted. Cleaned up {$files_deleted} files.");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Job delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>











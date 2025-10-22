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

$event_id = intval($input['event_id'] ?? 0);
$csrf = $input['csrf_token'] ?? '';

if ($event_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Event ID required']);
	exit;
}

if (!Auth::verifyCSRFToken($csrf)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
	exit;
}

try {
	$event = db()->fetchOne("SELECT id, created_by FROM events WHERE id = ?", [$event_id]);
	if (!$event) {
		http_response_code(404);
		echo json_encode(['success' => false, 'message' => 'Event not found']);
		exit;
	}

	$isPrivileged = Auth::hasAnyRole(['staff','admin']);
	$isAuthor = ($event['created_by'] === $user['id']);
	if (!($isPrivileged || $isAuthor)) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'You can only delete your own event']);
		exit;
	}

	// Clean up associated files using centralized system
	$files_deleted = FileCleanup::cleanupEventFiles($event_id);
	
	// Delete event (this will cascade delete attachments via FK)
	db()->execute("DELETE FROM events WHERE id = ?", [$event_id]);
	
	error_log("Event {$event_id} deleted. Cleaned up {$files_deleted} files.");

	echo json_encode(['success' => true]);
} catch (Exception $e) {
	error_log('Event delete error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>










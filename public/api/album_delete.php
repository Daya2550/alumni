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

$album_id = intval($input['album_id'] ?? 0);
$csrf = $input['csrf_token'] ?? '';

if ($album_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Album ID required']);
	exit;
}

if (!Auth::verifyCSRFToken($csrf)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
	exit;
}

try {
	$album = db()->fetchOne("SELECT id, created_by FROM gallery_albums WHERE id = ?", [$album_id]);
	if (!$album) {
		http_response_code(404);
		echo json_encode(['success' => false, 'message' => 'Album not found']);
		exit;
	}

	$isPrivileged = Auth::hasAnyRole(['staff','admin']);
	$isOwner = ($album['created_by'] === $user['id']);
	if (!($isPrivileged || $isOwner)) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'You can only delete your own album']);
		exit;
	}

	// Clean up associated files using centralized system
	$files_deleted = FileCleanup::cleanupAlbumFiles($album_id);

	// Delete album (cascade removes media rows)
	db()->execute("DELETE FROM gallery_albums WHERE id = ?", [$album_id]);
	
	error_log("Album {$album_id} deleted. Cleaned up {$files_deleted} files.");

	echo json_encode(['success' => true]);
} catch (Exception $e) {
	error_log('Album delete error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>










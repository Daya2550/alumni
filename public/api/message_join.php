<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

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
$room_id = $input['room_id'] ?? '';
$csrf = $input['csrf_token'] ?? '';

if (!$room_id) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Room ID required']);
	exit;
}

if (!Auth::verifyCSRFToken($csrf)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
	exit;
}

$room = db()->fetchOne("SELECT * FROM message_rooms WHERE id = ? AND is_active = 1", [$room_id]);
if (!$room) {
	http_response_code(404);
	echo json_encode(['success' => false, 'message' => 'Room not found']);
	exit;
}

// Already a participant?
$existing = db()->fetchOne("SELECT id FROM message_participants WHERE room_id = ? AND user_id = ?", [$room_id, $user['id']]);
if ($existing) {
	echo json_encode(['success' => true]);
	exit;
}

$canJoin = false;
if ($room['type'] === 'group') {
	$canJoin = true;
} elseif ($room['type'] === 'batch') {
	$canJoin = ($room['batch'] && $room['batch'] === ($user['batch'] ?? ''));
} elseif ($room['type'] === 'private') {
	// Allow join if staff/admin or room creator
	$canJoin = (Auth::hasAnyRole(['staff','admin']) || $room['created_by'] === $user['id']);
}

if (!$canJoin) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'You cannot join this room.']);
	exit;
}

try {
	db()->execute("INSERT INTO message_participants (room_id, user_id) VALUES (?, ?)", [$room_id, $user['id']]);
	echo json_encode(['success' => true]);
} catch (Exception $e) {
	error_log('Message join error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to join room']);
}
?>










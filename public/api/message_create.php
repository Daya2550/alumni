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
$type = ($input['type'] ?? 'group');
$name = trim($input['name'] ?? '');
$csrf = $input['csrf_token'] ?? '';

if (!Auth::verifyCSRFToken($csrf)) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
	exit;
}

if (!in_array($type, ['group','batch'], true)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid message type']);
	exit;
}

try {
	$roomId = generateUUID();
	$roomName = $name !== '' ? $name : ($type === 'batch' ? ('Batch ' . ($user['batch'] ?? 'Message')) : 'Group Message');
	$batch = $type === 'batch' ? ($user['batch'] ?? null) : null;

	db()->beginTransaction();
	db()->execute(
		"INSERT INTO message_rooms (id, name, type, batch, created_by, is_active) VALUES (?, ?, ?, ?, ?, 1)",
		[$roomId, $roomName, $type, $batch, $user['id']]
	);
	db()->execute("INSERT INTO message_participants (room_id, user_id, joined_at) VALUES (?, ?, NOW())", [$roomId, $user['id']]);
	db()->commit();

	echo json_encode(['success' => true, 'room_id' => $roomId]);
} catch (Exception $e) {
	db()->rollback();
	error_log('message_create error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to create message']);
}
?>










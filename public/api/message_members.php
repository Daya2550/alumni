<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit;
}

if (!Auth::isLoggedIn()) { http_response_code(401); echo json_encode(['success'=>false]); exit; }

$user = Auth::getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
	$room_id = $_GET['room_id'] ?? '';
	if (!$room_id) { http_response_code(400); echo json_encode(['success'=>false]); exit; }
	$members = db()->fetchAll(
		"SELECT u.id, u.name, u.email, u.batch FROM message_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.room_id = ? ORDER BY u.name",
		[$room_id]
	);
	echo json_encode(['success'=>true, 'members'=>$members]);
	exit;
}

if ($method === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$room_id = $input['room_id'] ?? '';
	$target_user_id = $input['user_id'] ?? '';
	$action = $input['action'] ?? '';
	$csrf = $input['csrf_token'] ?? '';
	if (!Auth::verifyCSRFToken($csrf)) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }
	if (!$room_id || !$target_user_id || !in_array($action, ['add','remove'], true)) { http_response_code(400); echo json_encode(['success'=>false]); exit; }

	$room = db()->fetchOne("SELECT created_by, type FROM message_rooms WHERE id = ?", [$room_id]);
	if (!$room) { http_response_code(404); echo json_encode(['success'=>false]); exit; }
	$canManage = ($room['created_by'] === $user['id']) || Auth::hasAnyRole(['staff','admin']);
	if (!$canManage) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit; }

	try {
		if ($action === 'add') {
			db()->execute("INSERT INTO message_participants (room_id, user_id, joined_at) VALUES (?, ?, NOW())", [$room_id, $target_user_id]);
		} else {
			db()->execute("DELETE FROM message_participants WHERE room_id = ? AND user_id = ?", [$room_id, $target_user_id]);
		}
		echo json_encode(['success'=>true]);
	} catch (Exception $e) {
		error_log('message_members error: ' . $e->getMessage());
		http_response_code(500);
		echo json_encode(['success'=>false,'message'=>'Operation failed']);
	}
	exit;
}

http_response_code(405);
echo json_encode(['success'=>false,'message'=>'Method not allowed']);
?>










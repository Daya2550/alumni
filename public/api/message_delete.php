<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

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
$action = $input['action'] ?? 'leave'; // 'leave' | 'delete_for_all'
$csrf = $input['csrf_token'] ?? '';

// Debug logging
error_log("Message delete API called - Room ID: $room_id, Action: $action, User ID: " . ($user['id'] ?? 'none'));

if (!$room_id) {
    error_log("Message delete API error: Room ID required");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room ID required']);
    exit;
}

if (!Auth::verifyCSRFToken($csrf)) {
    error_log("Message delete API error: Invalid CSRF token");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$room = db()->fetchOne("SELECT id, type, created_by, is_active FROM message_rooms WHERE id = ?", [$room_id]);
if (!$room || !$room['is_active']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Room not found']);
    exit;
}

// Ensure user is participant to perform actions
$is_participant = db()->fetchOne("SELECT id FROM message_participants WHERE room_id = ? AND user_id = ?", [$room_id, $user['id']]);
if (!$is_participant) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not a participant']);
    exit;
}

if ($action === 'leave') {
    try {
        // Remove the user from participants
        db()->execute("DELETE FROM message_participants WHERE room_id = ? AND user_id = ?", [$room_id, $user['id']]);

        // If no participants remain, deactivate the room
        $remaining = db()->fetchOne("SELECT COUNT(*) AS c FROM message_participants WHERE room_id = ?", [$room_id]);
        if (($remaining['c'] ?? 0) == 0) {
            db()->execute("UPDATE message_rooms SET is_active = 0 WHERE id = ?", [$room_id]);
        }
        echo json_encode(['success' => true, 'status' => 'left']);
    } catch (Exception $e) {
        error_log('message_delete leave error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Operation failed']);
    }
    exit;
}

if ($action === 'delete_for_all') {
    // Permissions: allow creator/staff/admin to delete any; for private messages, allow any participant
    $canDeleteForAll = ($room['type'] === 'private') || ($room['created_by'] === $user['id']) || Auth::hasAnyRole(['staff','admin']);
    
    error_log("Delete for all permission check - Room type: " . $room['type'] . ", Created by: " . $room['created_by'] . ", User ID: " . $user['id'] . ", Can delete: " . ($canDeleteForAll ? 'yes' : 'no'));
    
    if (!$canDeleteForAll) {
        error_log("Message delete API error: Forbidden - user cannot delete for all");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    try {
        db()->beginTransaction();
        // Clean up message files before deactivating room (use correct table)
        $messages = db()->fetchAll("SELECT id FROM message_messages WHERE room_id = ? AND file_path IS NOT NULL", [$room_id]);
        $files_deleted = 0;
        foreach ($messages as $message) {
            $files_deleted += FileCleanup::cleanupMessageFiles($message['id']);
        }
        // Mark all messages as deleted
        db()->execute("UPDATE message_messages SET deleted_at = NOW() WHERE room_id = ? AND deleted_at IS NULL", [$room_id]);
        db()->execute("UPDATE message_rooms SET is_active = 0 WHERE id = ?", [$room_id]);
        // Optional: clean up participants (not strictly necessary once inactive)
        db()->execute("DELETE FROM message_participants WHERE room_id = ?", [$room_id]);
        db()->commit();
        error_log("Message room {$room_id} deleted. Cleaned up {$files_deleted} files.");
        echo json_encode(['success' => true, 'status' => 'deleted']);
    } catch (Exception $e) {
        // Attempt rollback if supported; ignore errors
        try { db()->rollback(); } catch (Exception $ignored) {}
        error_log('message_delete delete_for_all error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Operation failed']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>



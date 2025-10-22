<?php
/**
 * Message Messages API - Get messages for a message room
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$room_id = $_GET['room_id'] ?? '';

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room ID required']);
    exit;
}

// Check if user is participant in the room
$is_participant = db()->fetchOne(
    "SELECT id FROM message_participants WHERE room_id = ? AND user_id = ?",
    [$room_id, $user['id']]
);

if (!$is_participant) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Get messages
    $messages = db()->fetchAll(
        "SELECT cm.*, u.name as sender_name, u.profile_photo as sender_photo
         FROM message_messages cm
         JOIN users u ON cm.sender_id = u.id
         WHERE cm.room_id = ? AND cm.deleted_at IS NULL
         ORDER BY cm.created_at ASC
         LIMIT 100",
        [$room_id]
    );
    
    // Update last read timestamp
    db()->execute(
        "UPDATE message_participants SET last_read_at = NOW() WHERE room_id = ? AND user_id = ?",
        [$room_id, $user['id']]
    );
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log("Message messages error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

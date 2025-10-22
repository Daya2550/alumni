<?php
/**
 * Message Send API - Send a message to a message room
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$room_id = $_POST['room_id'] ?? '';
$message = trim($_POST['message'] ?? '');
$csrf = $_POST['csrf_token'] ?? '';

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room ID required']);
    exit;
}

// CSRF check
if (!Auth::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check rate limiting (if available)
$CHAT_RATE_LIMIT = defined('CHAT_RATE_LIMIT') ? CHAT_RATE_LIMIT : 60; // messages per minute default
if (class_exists('RateLimiter')) {
    if (!RateLimiter::checkLimit('message_message', $user['id'], $CHAT_RATE_LIMIT, 60)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please slow down.']);
        exit;
    }
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

// Validate message length
$CHAT_MESSAGE_LIMIT = defined('CHAT_MESSAGE_LIMIT') ? CHAT_MESSAGE_LIMIT : 1000;
if ($message && strlen($message) > $CHAT_MESSAGE_LIMIT) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message too long']);
    exit;
}

try {
    $insertId = null;
    // Handle optional file upload
    if (!empty($_FILES['file']) && isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../uploads/message/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp','pdf','mp4','mov','avi','mkv'];
        if (!in_array($ext, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
            exit;
        }
        $newName = uniqid('message_', true) . '.' . $ext;
        $dest = $uploadDir . $newName;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
            exit;
        }
        $publicPath = 'uploads/message/' . $newName;
        $type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
        db()->execute(
            "INSERT INTO message_messages (room_id, sender_id, message, message_type, file_path) VALUES (?, ?, ?, ?, ?)",
            [$room_id, $user['id'], sanitizeInput($message), $type, $publicPath]
        );
        $insertId = db()->lastInsertId();
    } else {
        if (!$message) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Message or file required']);
            exit;
        }
        db()->execute(
            "INSERT INTO message_messages (room_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')",
            [$room_id, $user['id'], sanitizeInput($message)]
        );
        $insertId = db()->lastInsertId();
    }
    
    // Notify other participants
    Notifications::notifyMessageParticipants($room_id, $user['id'], 'New message', substr($message ?: 'Sent a file', 0, 120));
    
    // Get the inserted message with sender info
    $new_message = db()->fetchOne(
        "SELECT cm.*, u.name as sender_name, u.profile_photo as sender_photo
         FROM message_messages cm
         JOIN users u ON cm.sender_id = u.id
         WHERE cm.id = ?",
        [$insertId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => $new_message
    ]);
    
} catch (Exception $e) {
    error_log("Message send error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

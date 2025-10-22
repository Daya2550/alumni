<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Create or find a test group room
$roomId = generateUUID();
$roomName = 'Test Group Chat ' . substr($roomId, 0, 8);

try {
	// Create a group room
	db()->execute(
		"INSERT INTO message_rooms (id, name, type, created_by, is_active) VALUES (?, ?, 'group', ?, 1)",
		[$roomId, $roomName, $user['id']]
	);
	// Add current user as participant
	db()->execute(
		"INSERT INTO message_participants (room_id, user_id, joined_at) VALUES (?, ?, NOW())",
		[$roomId, $user['id']]
	);
	// Seed a welcome message
	db()->execute(
		"INSERT INTO message_messages (room_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')",
		[$roomId, $user['id'], 'Welcome to the test room!']
	);

	// Redirect to the new room
	header('Location: message.php?room_id=' . urlencode($roomId));
	exit;
} catch (Exception $e) {
	echo 'Failed to create test chat: ' . htmlspecialchars($e->getMessage());
}










<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

// Delete recently created test group rooms (last 1 hour) with name prefix
try {
	$deleted = db()->execute(
		"DELETE FROM message_rooms WHERE type='group' AND name LIKE 'Test Group Chat %' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
	);
	echo 'Test chat cleanup requested.';
} catch (Exception $e) {
	echo 'Cleanup failed: ' . htmlspecialchars($e->getMessage());
}










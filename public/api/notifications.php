<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
	echo json_encode(['success' => false, 'count' => 0]);
	exit;
}

$user = Auth::getCurrentUser();

$action = $_GET['action'] ?? 'check';

if ($action === 'check') {
	$count = db()->fetchOne("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0", [$user['id']])['cnt'] ?? 0;
	echo json_encode(['success' => true, 'count' => (int)$count]);
	exit;
}

echo json_encode(['success' => false]);
?>










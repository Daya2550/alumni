<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
	echo json_encode(['success' => false, 'users' => []]);
	exit;
}

$q = trim($_GET['q'] ?? '');
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

if ($q === '') {
	echo json_encode(['success' => true, 'users' => []]);
	exit;
}

$like = "%$q%";
$users = db()->fetchAll(
    "SELECT id, name, email, batch, department FROM users
     WHERE name LIKE ? OR email LIKE ? OR batch LIKE ? OR department LIKE ?
     ORDER BY name ASC LIMIT ?",
    [$like, $like, $like, $like, $limit]
);

echo json_encode(['success' => true, 'users' => $users]);
?>










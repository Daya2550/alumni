<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireAnyRole(['admin','staff']);

$user_id = $_POST['user_id'] ?? '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;

if (!$user_id || $is_active === null) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    db()->execute("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?", [$is_active, $user_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}
?>









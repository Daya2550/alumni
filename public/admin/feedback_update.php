<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireAnyRole(['admin','staff']);

$id = sanitizeInput($_POST['id'] ?? '');
$status = sanitizeInput($_POST['status'] ?? '');

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

if (!in_array($status, ['new', 'in_review', 'resolved', 'closed'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    db()->execute("UPDATE feedback SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $id]);
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} catch (Exception $e) {
    error_log("Feedback update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>






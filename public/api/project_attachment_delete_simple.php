<?php
/**
 * Simplified attachment delete for testing
 * This version removes some checks to isolate the issue
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
    exit;
}

$attachment_id = $_POST['attachment_id'] ?? '';

if (empty($attachment_id)) {
    echo json_encode(['success' => false, 'message' => 'Attachment ID required']);
    exit;
}

try {
    // Get attachment details
    $attachment = db()->fetchOne(
        "SELECT pa.*, p.owner_id 
         FROM project_attachments pa 
         LEFT JOIN projects p ON pa.project_id = p.id 
         WHERE pa.id = ?",
        [$attachment_id]
    );
    
    if (!$attachment) {
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit;
    }
    
    // Simplified permission check - allow if user is uploader or project owner
    $can_delete = ($attachment['uploader_id'] === $user['id']) || 
                  ($attachment['owner_id'] === $user['id']);
    
    if (!$can_delete) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Try to delete from database first
    db()->execute("DELETE FROM project_attachments WHERE id = ?", [$attachment_id]);
    
    // Try to delete file (don't fail if file doesn't exist)
    $file_path = '../../' . $attachment['file_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    echo json_encode(['success' => true, 'message' => 'Attachment deleted successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>



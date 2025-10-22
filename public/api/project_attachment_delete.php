<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Enable error logging for debugging
error_log("=== ATTACHMENT DELETE API CALLED ===");
error_log("POST data: " . print_r($_POST, true));
error_log("SERVER REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

try {
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    error_log("User authenticated successfully: " . $user['id']);
} catch (Exception $e) {
    error_log("Authentication failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()]);
    exit;
}

$attachment_id = trim($_POST['attachment_id'] ?? '');
error_log("Raw attachment ID: '" . $attachment_id . "'");

if (empty($attachment_id)) {
    error_log("Attachment ID is empty or null");
    echo json_encode(['success' => false, 'message' => 'Attachment ID is required']);
    exit;
}

try {
    // Test database connection first
    $test_query = db()->fetchOne("SELECT 1 as test");
    error_log("Database connection test: OK");
    
    // Get attachment details with better error handling
    error_log("Querying attachment with ID: " . $attachment_id);
    $attachment = db()->fetchOne(
        "SELECT pa.*, p.owner_id 
         FROM project_attachments pa 
         LEFT JOIN projects p ON pa.project_id = p.id 
         WHERE pa.id = ?",
        [$attachment_id]
    );
    
    error_log("Attachment query result: " . print_r($attachment, true));
    
    if (!$attachment) {
        error_log("Attachment not found in database for ID: " . $attachment_id);
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit;
    }
    
    // Check permissions - owner of project, uploader, or admin/staff
    $is_uploader = ($attachment['uploader_id'] === $user['id']);
    $is_owner = ($attachment['owner_id'] === $user['id']);
    $is_admin = Auth::hasAnyRole(['admin', 'staff']);
    $can_delete = $is_uploader || $is_owner || $is_admin;
    
    error_log("Permission check:");
    error_log("  User ID: " . $user['id']);
    error_log("  Uploader ID: " . $attachment['uploader_id']);
    error_log("  Owner ID: " . $attachment['owner_id']);
    error_log("  Is uploader: " . ($is_uploader ? 'true' : 'false'));
    error_log("  Is owner: " . ($is_owner ? 'true' : 'false'));
    error_log("  Is admin/staff: " . ($is_admin ? 'true' : 'false'));
    error_log("  Can delete: " . ($can_delete ? 'true' : 'false'));
    
    if (!$can_delete) {
        error_log("Access denied for user " . $user['id']);
        echo json_encode(['success' => false, 'message' => 'Access denied - you can only delete your own attachments or attachments from your projects']);
        exit;
    }
    
    db()->beginTransaction();
    error_log("Transaction started");
    
    // Delete from database first (in case file deletion fails)
    error_log("Deleting attachment from database with ID: " . $attachment_id);
    $delete_result = db()->execute("DELETE FROM project_attachments WHERE id = ?", [$attachment_id]);
    error_log("Database deletion result: " . ($delete_result ? 'success' : 'failed'));
    
    // Delete file from filesystem
    $file_path = '../../' . $attachment['file_path'];
    error_log("Attempting to delete file: " . $file_path);
    error_log("File exists: " . (file_exists($file_path) ? 'true' : 'false'));
    
    if (file_exists($file_path)) {
        $unlink_result = unlink($file_path);
        error_log("File unlink result: " . ($unlink_result ? 'success' : 'failed'));
        if (!$unlink_result) {
            error_log("Warning: Failed to delete file from filesystem, but database record was deleted");
        }
    } else {
        error_log("File does not exist, skipping file deletion");
    }
    
    // Log activity (optional - don't fail if this doesn't work)
    try {
        db()->execute(
            "INSERT INTO project_activity (id, project_id, actor_id, activity_type, meta_json) VALUES (?, ?, ?, ?, ?)",
            [
                generateUUID(),
                $attachment['project_id'],
                $user['id'],
                'attachment_removed',
                json_encode(['attachment_name' => $attachment['file_name']])
            ]
        );
        error_log("Activity logged successfully");
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
    
    db()->commit();
    error_log("Transaction committed successfully");
    echo json_encode(['success' => true, 'message' => 'Attachment deleted successfully']);
    
} catch (Exception $e) {
    db()->rollback();
    error_log("Attachment deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete attachment']);
}
?>

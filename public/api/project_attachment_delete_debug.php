<?php
/**
 * Debug version of project_attachment_delete.php
 * This version has extensive logging to help debug the deletion issue
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Log everything
error_log("=== ATTACHMENT DELETE DEBUG START ===");
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("SERVER data: " . print_r($_SERVER, true));

try {
    Auth::requireLogin();
    $user = Auth::getCurrentUser();
    error_log("User authenticated: " . print_r($user, true));
} catch (Exception $e) {
    error_log("Authentication failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()]);
    exit;
}

$attachment_id = $_POST['attachment_id'] ?? '';
error_log("Raw attachment ID: " . $attachment_id);

if (empty($attachment_id)) {
    error_log("Attachment ID is empty");
    echo json_encode(['success' => false, 'message' => 'Attachment ID required']);
    exit;
}

// Test database connection
try {
    $test_query = db()->fetchOne("SELECT 1 as test");
    error_log("Database connection test: OK");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Get attachment details
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
        error_log("Attachment not found in database");
        echo json_encode(['success' => false, 'message' => 'Attachment not found']);
        exit;
    }
    
    // Check permissions
    $can_delete = ($attachment['uploader_id'] === $user['id']) || 
                  ($attachment['owner_id'] === $user['id']) || 
                  Auth::hasAnyRole(['admin', 'staff']);
    
    error_log("Permission check:");
    error_log("  User ID: " . $user['id']);
    error_log("  Uploader ID: " . $attachment['uploader_id']);
    error_log("  Owner ID: " . $attachment['owner_id']);
    error_log("  User is uploader: " . ($attachment['uploader_id'] === $user['id'] ? 'true' : 'false'));
    error_log("  User is owner: " . ($attachment['owner_id'] === $user['id'] ? 'true' : 'false'));
    error_log("  User has admin/staff role: " . (Auth::hasAnyRole(['admin', 'staff']) ? 'true' : 'false'));
    error_log("  Can delete: " . ($can_delete ? 'true' : 'false'));
    
    if (!$can_delete) {
        error_log("Access denied for user " . $user['id']);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Test file path
    $file_path = '../../' . $attachment['file_path'];
    error_log("File path: " . $file_path);
    error_log("File exists: " . (file_exists($file_path) ? 'true' : 'false'));
    error_log("File readable: " . (is_readable($file_path) ? 'true' : 'false'));
    error_log("File writable: " . (is_writable($file_path) ? 'true' : 'false'));
    
    // Test directory permissions
    $dir_path = dirname($file_path);
    error_log("Directory path: " . $dir_path);
    error_log("Directory exists: " . (is_dir($dir_path) ? 'true' : 'false'));
    error_log("Directory writable: " . (is_writable($dir_path) ? 'true' : 'false'));
    
    db()->beginTransaction();
    error_log("Transaction started");
    
    // Delete file from filesystem
    if (file_exists($file_path)) {
        $unlink_result = unlink($file_path);
        error_log("File unlink result: " . ($unlink_result ? 'success' : 'failed'));
        if (!$unlink_result) {
            error_log("Failed to delete file: " . $file_path);
        }
    } else {
        error_log("File does not exist, skipping file deletion");
    }
    
    // Delete from database
    error_log("Deleting attachment from database with ID: " . $attachment_id);
    $delete_result = db()->execute("DELETE FROM project_attachments WHERE id = ?", [$attachment_id]);
    error_log("Database deletion result: " . ($delete_result ? 'success' : 'failed'));
    
    // Log activity
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
    error_log("Error trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Failed to delete attachment: ' . $e->getMessage()]);
}

error_log("=== ATTACHMENT DELETE DEBUG END ===");
?>



<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_cleanup.php';

header('Content-Type: application/json');

Auth::requireLogin();

$project_id = sanitizeInput($_POST['project_id'] ?? '');

if (empty($project_id)) {
    echo json_encode(['success' => false, 'message' => 'Project ID required']);
    exit;
}

try {
    // Check if project exists
    $project = db()->fetchOne("SELECT id, owner_id FROM projects WHERE id = ?", [$project_id]);
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
        exit;
    }
    
    // Owner can delete their project; admin/staff can delete any
    $user = Auth::getCurrentUser();
    $isOwner = ($project['owner_id'] === $user['id']);
    $isPrivileged = Auth::hasAnyRole(['admin','staff']);
    if (!$isOwner && !$isPrivileged) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    db()->beginTransaction();
    
    // Clean up associated files using centralized system
    $files_deleted = FileCleanup::cleanupProjectFiles($project_id);
    
    // Delete related records (cascade should handle most, but let's be explicit)
    db()->execute("DELETE FROM project_activity WHERE project_id = ?", [$project_id]);
    db()->execute("DELETE FROM project_settings WHERE project_id = ?", [$project_id]);
    db()->execute("DELETE FROM project_helpers WHERE project_id = ?", [$project_id]);
    db()->execute("DELETE FROM project_contributions WHERE project_id = ?", [$project_id]);
    db()->execute("DELETE FROM project_attachments WHERE project_id = ?", [$project_id]);
    db()->execute("DELETE FROM projects WHERE id = ?", [$project_id]);
    
    db()->commit();
    
    error_log("Project {$project_id} deleted. Cleaned up {$files_deleted} files.");
    echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    
} catch (Exception $e) {
    db()->rollback();
    error_log("Project deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete project']);
}
?>

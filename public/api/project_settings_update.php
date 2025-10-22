<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::getCurrentUser();

$project_id = sanitizeInput($_POST['project_id'] ?? '');
$require_file_approval = isset($_POST['require_file_approval']) ? 1 : 0;
$allow_public_contributions = isset($_POST['allow_public_contributions']) ? 1 : 0;

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Missing project id']);
    exit;
}

// Permissions: only owner, admin, or staff may update settings
$project = db()->fetchOne("SELECT owner_id FROM projects WHERE id = ?", [$project_id]);
if (!$project) {
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}
if (!($project['owner_id'] === $user['id'] || Auth::hasAnyRole(['admin','staff']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Upsert settings
    $existing = db()->fetchOne("SELECT project_id FROM project_settings WHERE project_id = ?", [$project_id]);
    if ($existing) {
        db()->execute(
            "UPDATE project_settings SET require_file_approval = ?, allow_public_contributions = ? WHERE project_id = ?",
            [$require_file_approval, $allow_public_contributions, $project_id]
        );
    } else {
        // Default to allowing public contributions if not specified
        $default_allow_public = $allow_public_contributions ?: 1;
        db()->execute(
            "INSERT INTO project_settings (project_id, require_file_approval, allow_public_contributions) VALUES (?, ?, ?)",
            [$project_id, $require_file_approval, $default_allow_public]
        );
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('project_settings_update error: '.$e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save']);
}
?>



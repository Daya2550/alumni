<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::getCurrentUser();

$project_id = sanitizeInput($_POST['project_id'] ?? '');
if (!$project_id) { echo json_encode(['success'=>false,'message'=>'Missing project id']); exit; }

$project = db()->fetchOne("SELECT owner_id FROM projects WHERE id = ?", [$project_id]);
if (!$project) { echo json_encode(['success'=>false,'message'=>'Project not found']); exit; }

$isOwner = ($project['owner_id'] === $user['id']);
$isPrivileged = Auth::hasAnyRole(['admin','staff']);
if (!$isOwner && !$isPrivileged) { echo json_encode(['success'=>false,'message'=>'Access denied']); exit; }

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'No file uploaded']);
    exit;
}

try {
    $file = $_FILES['file'];
    $upload_dir = '../../uploads/projects/';
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_name = 'proj_' . uniqid() . '.' . $ext;
    $dest = $upload_dir . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success'=>false,'message'=>'Move failed']);
        exit;
    }

    $attachment_id = generateUUID();
    db()->execute(
        "INSERT INTO project_attachments (id, project_id, uploader_id, file_path, file_name, mime_type, file_size, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
        [
            $attachment_id,
            $project_id,
            $user['id'],
            'uploads/projects/' . $new_name,
            $file['name'],
            $file['type'] ?: 'application/octet-stream',
            $file['size']
        ]
    );

    db()->execute(
        "INSERT INTO project_activity (id, project_id, actor_id, activity_type, meta_json) VALUES (?, ?, ?, 'attachment_added', ?)",
        [generateUUID(), $project_id, $user['id'], json_encode(['file'=>$file['name']])]
    );

    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    error_log('Attachment upload error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error']);
}






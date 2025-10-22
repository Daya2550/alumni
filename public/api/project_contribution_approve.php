<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

Auth::requireLogin();
$user = Auth::getCurrentUser();

$contribution_id = sanitizeInput($_POST['contribution_id'] ?? '');
$action = sanitizeInput($_POST['action'] ?? '');

if (!$contribution_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    // Get contribution details and check permissions
    $contribution = db()->fetchOne(
        "SELECT pc.*, p.owner_id, p.title as project_title
         FROM project_contributions pc
         LEFT JOIN projects p ON pc.project_id = p.id
         WHERE pc.id = ?",
        [$contribution_id]
    );

    if (!$contribution) {
        echo json_encode(['success' => false, 'message' => 'Contribution not found']);
        exit;
    }

    // Check if user can approve (owner, admin, or staff)
    $can_approve = ($contribution['owner_id'] === $user['id']) || Auth::hasAnyRole(['admin', 'staff']);
    if (!$can_approve) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    db()->beginTransaction();

    if ($action === 'approve') {
        // Approve the contribution
        db()->execute(
            "UPDATE project_contributions SET is_approved = 1 WHERE id = ?",
            [$contribution_id]
        );

        // If it's a file contribution, also approve the attachment
        if ($contribution['attachment_id']) {
            db()->execute(
                "UPDATE project_attachments SET is_approved = 1 WHERE id = ?",
                [$contribution['attachment_id']]
            );
        }

        // Log activity
        db()->execute(
            "INSERT INTO project_activity (id, project_id, actor_id, activity_type, meta_json) VALUES (?, ?, ?, ?, ?)",
            [
                generateUUID(),
                $contribution['project_id'],
                $user['id'],
                'contribution_approved',
                json_encode(['contribution_id' => $contribution_id])
            ]
        );

        // Notify the contributor
        require_once __DIR__ . '/../../includes/notifications.php';
        Notifications::notifyUsers(
            [$contribution['contributor_id']],
            'Contribution Approved',
            'Your contribution to "' . $contribution['project_title'] . '" has been approved.',
            'success',
            'project',
            $contribution['project_id']
        );

    } else { // reject
        // Delete the contribution and its attachment
        if ($contribution['attachment_id']) {
            // Get attachment file path to delete the file
            $attachment = db()->fetchOne("SELECT file_path FROM project_attachments WHERE id = ?", [$contribution['attachment_id']]);
            if ($attachment && file_exists('../../' . $attachment['file_path'])) {
                unlink('../../' . $attachment['file_path']);
            }
            db()->execute("DELETE FROM project_attachments WHERE id = ?", [$contribution['attachment_id']]);
        }

        db()->execute("DELETE FROM project_contributions WHERE id = ?", [$contribution_id]);

        // Log activity
        db()->execute(
            "INSERT INTO project_activity (id, project_id, actor_id, activity_type, meta_json) VALUES (?, ?, ?, ?, ?)",
            [
                generateUUID(),
                $contribution['project_id'],
                $user['id'],
                'contribution_rejected',
                json_encode(['contribution_id' => $contribution_id])
            ]
        );

        // Notify the contributor
        require_once __DIR__ . '/../../includes/notifications.php';
        Notifications::notifyUsers(
            [$contribution['contributor_id']],
            'Contribution Rejected',
            'Your contribution to "' . $contribution['project_title'] . '" has been rejected.',
            'warning',
            'project',
            $contribution['project_id']
        );
    }

    db()->commit();
    echo json_encode(['success' => true, 'message' => 'Contribution ' . $action . 'd successfully']);

} catch (Exception $e) {
    db()->rollback();
    error_log("Project contribution approval error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to ' . $action . ' contribution']);
}
?>

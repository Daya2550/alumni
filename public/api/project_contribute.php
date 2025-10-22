<?php
// Disable all output buffering and error display
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set up error handler to catch any PHP errors and return JSON
set_error_handler(function($severity, $message, $file, $line) {
    ob_clean(); // Clear any output
    error_log("PHP Error: $message in $file on line $line");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $message]);
    exit;
});

// Catch any fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean(); // Clear any output
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal server error: ' . $error['message']]);
        exit;
    }
});

// Wrap everything in a try-catch to ensure JSON output
try {
    require_once __DIR__ . '/../../includes/database.php';
    require_once __DIR__ . '/../../includes/auth.php';

    header('Content-Type: application/json');

    // Log the request for debugging
    error_log("Contribution API called with POST data: " . print_r($_POST, true));
    
    // Clean up any lingering transactions from previous requests
    db()->cleanupTransactions();
    
    // Set a timeout to prevent long-running transactions
    set_time_limit(30);

    try {
        Auth::requireLogin();
        $user = Auth::getCurrentUser();
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Authentication required: ' . $e->getMessage()]);
        exit;
    }

$project_id = sanitizeInput($_POST['project_id'] ?? '');
$type = sanitizeInput($_POST['type'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$project_id || !$type) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields', 'code' => 'MISSING_FIELDS']);
    exit;
}

if (!in_array($type, ['comment', 'feedback', 'file'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid contribution type', 'code' => 'INVALID_TYPE']);
    exit;
}

// Validate message for non-file contributions
if ($type !== 'file' && empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required for ' . $type . ' contributions', 'code' => 'MISSING_MESSAGE']);
    exit;
}

// Validate file upload for file contributions
if ($type === 'file' && (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload', 'code' => 'MISSING_FILE']);
    exit;
}

// Check if project exists and user can contribute
$project = db()->fetchOne(
    "SELECT p.*, u.batch as owner_batch 
     FROM projects p 
     LEFT JOIN users u ON p.owner_id = u.id 
     WHERE p.id = ? AND p.approval_status IN ('approved', 'published')",
    [$project_id]
);

if (!$project) {
    echo json_encode(['success' => false, 'message' => 'Project not found']);
    exit;
}

// Check permissions - allow contributions by default
// Fetch project settings
$settings = db()->fetchOne(
    "SELECT require_file_approval, allow_public_contributions FROM project_settings WHERE project_id = ?",
    [$project_id]
);
$require_file_approval = (int)($settings['require_file_approval'] ?? 0) === 1;
$allow_public_contributions = (int)($settings['allow_public_contributions'] ?? 1) === 1; // Default to 1 (enabled)

$is_owner = $project['owner_id'] === $user['id'];
$is_helper = db()->fetchOne(
    "SELECT id FROM project_helpers WHERE project_id = ? AND user_id = ? AND status = 'accepted'",
    [$project_id, $user['id']]
);
$can_contribute = $is_owner || $is_helper || Auth::hasAnyRole(['admin', 'staff']) || $allow_public_contributions;

if (!$can_contribute) {
    echo json_encode(['success' => false, 'message' => 'Access denied - contributions are not allowed for this project']);
    exit;
}

try {
    // Check if required tables exist
    $tables_check = [
        'project_contributions' => 'project_contributions',
        'project_attachments' => 'project_attachments', 
        'project_activity' => 'project_activity',
        'project_settings' => 'project_settings'
    ];
    
    foreach ($tables_check as $table_name => $table) {
        try {
            db()->fetchOne("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            error_log("Table $table_name does not exist: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => "Database table $table_name is missing. Please run the database setup."]);
            exit;
        }
    }
    
    // Check if transaction is already active
    $transaction_active = false;
    try {
        // Check if we're already in a transaction
        $pdo = db()->getConnection();
        if ($pdo->inTransaction()) {
            $transaction_active = true;
            error_log("Transaction already active, skipping beginTransaction");
        } else {
            db()->beginTransaction();
            $transaction_active = true;
        }
    } catch (Exception $e) {
        error_log("Transaction check failed: " . $e->getMessage());
        // Continue without transaction if check fails
    }
    
    $contribution_id = generateUUID();
    $attachment_id = null;
    
    // Handle file upload if type is 'file'
    if ($type === 'file' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        // Validate file size (10MB limit)
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit', 'code' => 'FILE_TOO_LARGE']);
            exit;
        }
        
        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types), 'code' => 'INVALID_FILE_TYPE']);
            exit;
        }
        
        $upload_dir = __DIR__ . '/../../uploads/projects/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = 'proj_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $attachment_id = generateUUID();
            db()->execute(
                "INSERT INTO project_attachments (id, project_id, uploader_id, file_path, file_name, mime_type, file_size, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $attachment_id,
                    $project_id,
                    $user['id'],
                    'uploads/projects/' . $file_name,
                    $file['name'],
                    $file['type'],
                    $file['size'],
                    $require_file_approval ? 0 : 1
                ]
            );
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload file', 'code' => 'UPLOAD_FAILED']);
            exit;
        }
    }
    
    // Create contribution
    db()->execute(
        "INSERT INTO project_contributions (id, project_id, contributor_id, type, message, attachment_id, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $contribution_id,
            $project_id,
            $user['id'],
            $type,
            $message ?: null,
            $attachment_id,
            ($type === 'file' && $require_file_approval) ? 0 : 1
        ]
    );
    
    // Log activity
    db()->execute(
        "INSERT INTO project_activity (id, project_id, actor_id, activity_type, meta_json) VALUES (?, ?, ?, ?, ?)",
        [
            generateUUID(),
            $project_id,
            $user['id'],
            'contribution_added',
            json_encode(['contribution_type' => $type])
        ]
    );

    // Note: Mail notifications removed as requested
    
    if ($transaction_active) {
        db()->commit();
    }
    echo json_encode(['success' => true, 'message' => 'Contribution added successfully']);
    
} catch (Exception $e) {
    if ($transaction_active) {
        try {
            db()->rollback();
        } catch (Exception $rollback_e) {
            error_log("Rollback failed: " . $rollback_e->getMessage());
        }
    }
    error_log("Project contribution error: " . $e->getMessage() . " | Project ID: " . $project_id . " | Type: " . $type . " | User ID: " . $user['id']);
    echo json_encode(['success' => false, 'message' => 'Failed to add contribution: ' . $e->getMessage()]);
}

} catch (Exception $e) {
    // Catch any errors in the entire script
    ob_clean();
    error_log("Script error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Script error: ' . $e->getMessage()]);
}
?>

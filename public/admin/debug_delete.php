<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user = Auth::getCurrentUser();
if (!Auth::hasAnyRole(['admin', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$user_id = $_GET['id'] ?? '';
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Check if user exists
    $user = db()->fetchOne("SELECT id, name, role FROM users WHERE id = ?", [$user_id]);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $debug_info = [
        'user_id' => $user_id,
        'user_name' => $user['name'],
        'user_role' => $user['role'],
        'constraints' => []
    ];
    
    // Check for foreign key constraints
    $tables_to_check = [
        'user_sessions' => 'user_id',
        'notifications' => 'user_id',
        'messages' => 'sender_id',
        'messages' => 'receiver_id',
        'notices' => 'created_by',
        'news' => 'created_by',
        'jobs' => 'created_by',
        'events' => 'created_by',
        'gallery' => 'uploaded_by',
        'feed' => 'created_by',
        'surveys' => 'owner_id',
        'survey_responses' => 'user_id',
        'feedback' => 'user_id'
    ];
    
    foreach ($tables_to_check as $table => $column) {
        try {
            if ($column === 'sender_id' || $column === 'receiver_id') {
                $count = db()->fetchOne("SELECT COUNT(*) as count FROM $table WHERE $column = ?", [$user_id])['count'];
            } else {
                $count = db()->fetchOne("SELECT COUNT(*) as count FROM $table WHERE $column = ?", [$user_id])['count'];
            }
            
            if ($count > 0) {
                $debug_info['constraints'][] = [
                    'table' => $table,
                    'column' => $column,
                    'count' => $count
                ];
            }
        } catch (Exception $e) {
            $debug_info['constraints'][] = [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode(['success' => true, 'debug_info' => $debug_info]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Debug failed: ' . $e->getMessage()]);
}
?>





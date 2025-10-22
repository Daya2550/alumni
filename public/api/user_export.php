<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!Auth::isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$current_user = Auth::getCurrentUser();
if (!Auth::hasAnyRole(['admin', 'staff'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Get filters from query parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$batch_filter = $_GET['batch'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build query
$where_conditions = ['1=1'];
$params = [];

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = (int)$status_filter;
}

if ($batch_filter) {
    $where_conditions[] = "batch = ?";
    $params[] = $batch_filter;
}

$where_sql = implode(' AND ', $where_conditions);

// Get users data
$users = db()->fetchAll(
    "SELECT id, name, email, mobile, role, batch, department, created_at, updated_at, is_active, email_verified
     FROM users 
     WHERE $where_sql 
     ORDER BY created_at DESC",
    $params
);

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    fputcsv($output, [
        'ID',
        'Name',
        'Email',
        'Mobile',
        'Role',
        'Batch',
        'Department',
        'Status',
        'Email Verified',
        'Created At',
        'Updated At'
    ]);
    
    // CSV data
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['mobile'] ?? '',
            $user['role'],
            $user['batch'] ?? '',
            $user['department'] ?? '',
            $user['is_active'] ? 'Active' : 'Inactive',
            $user['email_verified'] ? 'Yes' : 'No',
            date('Y-m-d H:i:s', strtotime($user['created_at'])),
            date('Y-m-d H:i:s', strtotime($user['updated_at']))
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.xlsx"');
    
    // Simple Excel-like format (CSV with Excel MIME type)
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'ID',
        'Name',
        'Email',
        'Mobile',
        'Role',
        'Batch',
        'Department',
        'Status',
        'Email Verified',
        'Created At',
        'Updated At'
    ]);
    
    // Data
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['mobile'] ?? '',
            $user['role'],
            $user['batch'] ?? '',
            $user['department'] ?? '',
            $user['is_active'] ? 'Active' : 'Inactive',
            $user['email_verified'] ? 'Yes' : 'No',
            date('Y-m-d H:i:s', strtotime($user['created_at'])),
            date('Y-m-d H:i:s', strtotime($user['updated_at']))
        ]);
    }
    
    fclose($output);
    
} else {
    // JSON format
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.json"');
    
    $export_data = [
        'export_info' => [
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => $current_user['name'],
            'total_users' => count($users),
            'filters' => [
                'search' => $search,
                'role' => $role_filter,
                'status' => $status_filter,
                'batch' => $batch_filter
            ]
        ],
        'users' => $users
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT);
}
?>
<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

// Get filters from URL
$status = sanitizeInput($_GET['status'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');

$where = [];
$params = [];
if ($status) { $where[] = 'f.status = ?'; $params[] = $status; }
if ($category) { $where[] = 'f.category = ?'; $params[] = $category; }
if ($date_from) { $where[] = 'DATE(f.created_at) >= ?'; $params[] = $date_from; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = db()->fetchAll(
    "SELECT f.*, u.name as user_name, u.email as user_email
     FROM feedback f
     LEFT JOIN users u ON u.id = f.user_id
     $whereSql
     ORDER BY f.created_at DESC",
    $params
);

// Set headers for CSV download
$filename = 'feedback_export_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'ID',
    'User Name',
    'User Email', 
    'Category',
    'Subject',
    'Message',
    'Target Type',
    'Target ID',
    'Status',
    'Created At',
    'Updated At'
]);

// Write data rows
foreach ($rows as $row) {
    fputcsv($output, [
        $row['id'],
        $row['user_name'] ?: 'Unknown',
        $row['user_email'] ?: '',
        $row['category'],
        $row['subject'],
        $row['message'],
        $row['target_type'] ?: 'None',
        $row['target_id'] ?: '',
        $row['status'],
        $row['created_at'],
        $row['updated_at'] ?: ''
    ]);
}

fclose($output);
exit;
?>






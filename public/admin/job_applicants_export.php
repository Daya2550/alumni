<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

$job_id = intval($_GET['job_id'] ?? 0);
if (!$job_id) {
    http_response_code(400);
    echo 'job_id required';
    exit;
}

$job = db()->fetchOne("SELECT id, title FROM job_opportunities WHERE id = ?", [$job_id]);
if (!$job) {
    http_response_code(404);
    echo 'Job not found';
    exit;
}

$rows = db()->fetchAll(
    "SELECT ja.id as application_id, u.id as user_id, u.name, u.email, u.batch, ja.status, ja.applied_at
     FROM job_applications ja
     JOIN users u ON u.id = ja.user_id
     WHERE ja.job_id = ?
     ORDER BY ja.applied_at ASC",
    [$job_id]
);

$filename = 'job_' . $job_id . '_applicants_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
fputcsv($out, ['Application ID', 'User ID', 'Name', 'Email', 'Batch', 'Status', 'Applied At']);
foreach ($rows as $r) {
    fputcsv($out, [$r['application_id'], $r['user_id'], $r['name'], $r['email'], $r['batch'], $r['status'], $r['applied_at']]);
}
fclose($out);
exit;
?>



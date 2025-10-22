<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
if (!$user || !in_array($user['role'], ['student', 'alumni'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

header('Location: student-dashboard.php');
exit;
?>



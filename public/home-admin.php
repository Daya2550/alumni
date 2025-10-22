<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireRole('admin');
header('Location: admin-dashboard.php');
exit;
?>




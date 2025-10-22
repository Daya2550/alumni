<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAnyRole(['staff']);
header('Location: staff-dashboard.php');
exit;
?>



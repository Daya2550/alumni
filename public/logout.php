<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Logout user
Auth::logout();

// Redirect to home page
header('Location: index.php');
exit;
?>

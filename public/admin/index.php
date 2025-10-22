<?php
/**
 * Admin Index - Redirect to User Management
 * This page redirects to the user management system
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check if user has admin/staff role
Auth::requireAnyRole(['admin', 'staff']);

// Redirect to user management page
header('Location: user_management.php');
exit;
?>


<?php
/**
 * LinkedIn OAuth Callback - Root Level Redirect
 * This file redirects to the actual callback handler in the public directory
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to the actual callback handler
header('Location: public/linkedin_callback.php?' . http_build_query($_GET));
exit;
?>
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Ensure user has proper access
Auth::requireAnyRole(['admin', 'staff']);

// Make sure errors don't break JSON responses
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function get_pdo(): PDO {
    // Use the existing database connection from the main project
    return db()->getConnection();
}

function json_response($data, int $status = 200): void {
    // Ensure no stray output corrupts JSON
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($status);
    }
    echo json_encode($data);
    exit;
}
?>

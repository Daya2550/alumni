<?php
// Simple test endpoint that always returns JSON
header('Content-Type: application/json');

// Test if we can include the required files
try {
    require_once __DIR__ . '/../../includes/database.php';
    echo json_encode(['success' => true, 'message' => 'Database file included successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database file error: ' . $e->getMessage()]);
}
?>

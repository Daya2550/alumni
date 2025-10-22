<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/database.php';
    
    // Test database connection
    $result = db()->fetchOne("SELECT 1 as test");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Database connection working',
        'test_result' => $result
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

<?php
/**
 * Test Message API Endpoints
 * This file helps debug API issues when hosted
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Test if user is logged in
if (!Auth::isLoggedIn()) {
    echo "❌ User not logged in\n";
    exit;
}

$user = Auth::getCurrentUser();
echo "✅ User logged in: " . $user['name'] . "\n";

// Test database connection
try {
    $test = db()->fetchOne("SELECT 1 as test");
    echo "✅ Database connection working\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit;
}

// Test if message tables exist
$tables = ['message_rooms', 'message_participants', 'message_messages'];
foreach ($tables as $table) {
    try {
        $result = db()->fetchOne("SELECT COUNT(*) as count FROM $table");
        echo "✅ Table $table exists (rows: {$result['count']})\n";
    } catch (Exception $e) {
        echo "❌ Table $table error: " . $e->getMessage() . "\n";
    }
}

// Test CSRF token
$csrf = Auth::generateCSRFToken();
echo "✅ CSRF token generated: " . substr($csrf, 0, 10) . "...\n";

// Test API endpoints
$endpoints = [
    'message_create.php' => 'POST',
    'message_members.php' => 'GET',
    'message_join.php' => 'POST',
    'message_send.php' => 'POST',
    'message_messages.php' => 'GET',
    'message_delete.php' => 'POST'
];

echo "\n🔍 Testing API endpoints:\n";
foreach ($endpoints as $endpoint => $method) {
    $path = __DIR__ . '/api/' . $endpoint;
    if (file_exists($path)) {
        echo "✅ $endpoint exists\n";
    } else {
        echo "❌ $endpoint missing\n";
    }
}

echo "\n📋 Test completed. Check for any ❌ errors above.\n";
?>



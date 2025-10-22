<?php
/**
 * Setup Message System Database Tables
 * Run this script to create the required database tables for the message system
 */

require_once __DIR__ . '/includes/database.php';

echo "Setting up Message System Database Tables...\n\n";

try {
    // Read the migration file
    $migrationFile = __DIR__ . '/migrations/20250103_create_message_system.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (!$sql) {
        throw new Exception("Could not read migration file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $db = db();
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $db->execute($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            $successCount++;
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "  Statement: " . substr($statement, 0, 100) . "...\n";
            $errorCount++;
        }
    }
    
    echo "\n";
    echo "Migration completed!\n";
    echo "Successful statements: $successCount\n";
    echo "Failed statements: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n✓ Message system database tables created successfully!\n";
        echo "You can now use the chat functionality in the header.\n";
    } else {
        echo "\n⚠ Some statements failed. Please check the errors above.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

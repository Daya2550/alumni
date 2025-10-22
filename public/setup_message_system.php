<?php
/**
 * Setup Message System Database Tables (Web Interface)
 * Run this script through your web browser to create the required database tables
 */

require_once __DIR__ . '/../includes/database.php';

// Simple authentication check
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Setup Message System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Setup Message System Database</h4>
                        </div>
                        <div class="card-body">
                            <p>This will create the required database tables for the message system.</p>
                            <p><strong>Warning:</strong> This will create new tables. Make sure you have a database backup.</p>
                            <a href="?confirm=yes" class="btn btn-primary">Proceed with Setup</a>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

echo "<!DOCTYPE html><html><head><title>Setup Message System</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "</head><body><div class='container mt-5'><div class='card'><div class='card-body'>";

echo "<h4>Setting up Message System Database Tables...</h4><br>";

try {
    // Read the migration file
    $migrationFile = __DIR__ . '/../migrations/20250103_create_message_system.sql';
    
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
            echo "<div class='alert alert-success'>✓ Executed: " . htmlspecialchars(substr($statement, 0, 50)) . "...</div>";
            $successCount++;
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='alert alert-warning'>Statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</div>";
            $errorCount++;
        }
    }
    
    echo "<br>";
    echo "<h5>Migration completed!</h5>";
    echo "<p>Successful statements: <span class='badge bg-success'>$successCount</span></p>";
    echo "<p>Failed statements: <span class='badge bg-danger'>$errorCount</span></p>";
    
    if ($errorCount === 0) {
        echo "<div class='alert alert-success'>";
        echo "<h5>✓ Message system database tables created successfully!</h5>";
        echo "<p>You can now use the chat functionality in the header.</p>";
        echo "<a href='message.php' class='btn btn-primary'>Go to Message System</a>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<h5>⚠ Some statements failed. Please check the errors above.</h5>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>Error: " . htmlspecialchars($e->getMessage()) . "</h5>";
    echo "</div>";
}

echo "</div></div></div></body></html>";
?>

<?php
/**
 * Debug Notice - Check notice data and attachments
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Get notice ID from URL
$notice_id = intval($_GET['id'] ?? 1);

echo "<h2>Debug Notice ID: $notice_id</h2>";

try {
    // Get notice details
    $notice = db()->fetchOne(
        "SELECT * FROM notices WHERE id = ?",
        [$notice_id]
    );
    
    if (!$notice) {
        echo "<p>No notice found with ID $notice_id</p>";
        exit;
    }
    
    echo "<h3>Notice Details:</h3>";
    echo "<p><strong>Title:</strong> " . htmlspecialchars($notice['title']) . "</p>";
    echo "<p><strong>Content:</strong> " . htmlspecialchars(substr($notice['content'], 0, 100)) . "...</p>";
    echo "<p><strong>Attachments (Raw):</strong> " . ($notice['attachments'] ?: 'NULL') . "</p>";
    
    if ($notice['attachments']) {
        $attachments = json_decode($notice['attachments'], true);
        if ($attachments) {
            echo "<h3>Decoded Attachments:</h3>";
            echo "<pre>" . print_r($attachments, true) . "</pre>";
            
            echo "<h3>Image Display Test:</h3>";
            foreach ($attachments as $index => $attachment) {
                echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                echo "<h4>Attachment " . ($index + 1) . ":</h4>";
                echo "<p><strong>Name:</strong> " . htmlspecialchars($attachment['name']) . "</p>";
                echo "<p><strong>URL:</strong> " . htmlspecialchars($attachment['url']) . "</p>";
                echo "<p><strong>Path:</strong> " . htmlspecialchars($attachment['path']) . "</p>";
                
                // Check if file exists
                $file_path = __DIR__ . '/../' . $attachment['url'];
                echo "<p><strong>File exists:</strong> " . (file_exists($file_path) ? 'YES' : 'NO') . "</p>";
                echo "<p><strong>Full path:</strong> " . $file_path . "</p>";
                
                // Try to display image
                $file_ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                
                if ($is_image) {
                    echo "<p><strong>Is Image:</strong> YES</p>";
                    echo "<img src='" . htmlspecialchars($attachment['url']) . "' style='max-width: 300px; max-height: 200px;' alt='Test Image'>";
                } else {
                    echo "<p><strong>Is Image:</strong> NO</p>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>Failed to decode attachments JSON</p>";
        }
    } else {
        echo "<p>No attachments found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>


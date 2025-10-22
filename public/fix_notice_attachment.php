<?php
/**
 * Fix Notice Attachment - Add proper attachment data to existing notice
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$notice_id = 1;

try {
    // Get the notice
    $notice = db()->fetchOne("SELECT * FROM notices WHERE id = ?", [$notice_id]);
    
    if (!$notice) {
        echo "Notice not found";
        exit;
    }
    
    echo "<h2>Fixing Notice ID: $notice_id</h2>";
    echo "<p>Current attachments: " . ($notice['attachments'] ?: 'NULL') . "</p>";
    
    // Check if the image file exists
    $image_file = "68d22d1ab5c71_20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg";
    $image_path = __DIR__ . "/../uploads/notices/" . $image_file;
    
    if (file_exists($image_path)) {
        echo "<p>Image file exists: YES</p>";
        
        // Get file size
        $file_size = filesize($image_path);
        echo "<p>File size: " . number_format($file_size) . " bytes (" . round($file_size / 1024 / 1024, 2) . " MB)</p>";
        
        // Create proper attachment data
        $attachments = [
            [
                'name' => '20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg',
                'path' => $image_path,
                'url' => 'uploads/notices/' . $image_file,
                'size' => $file_size,
                'type' => 'image/jpeg'
            ]
        ];
        
        $attachments_json = json_encode($attachments);
        echo "<p>New attachments JSON: " . $attachments_json . "</p>";
        
        // Update the notice
        db()->execute(
            "UPDATE notices SET attachments = ? WHERE id = ?",
            [$attachments_json, $notice_id]
        );
        
        echo "<p><strong>Notice updated successfully!</strong></p>";
        echo "<p><a href='notice.php?id=$notice_id'>View the notice</a></p>";
        
    } else {
        echo "<p>Image file does not exist at: $image_path</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>









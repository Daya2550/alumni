<?php
/**
 * Fix Notice Images - Comprehensive solution for image display issues
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$notice_id = 1;

echo "<h1>Fix Notice Images - Notice ID: $notice_id</h1>";

try {
    // Get the notice
    $notice = db()->fetchOne("SELECT * FROM notices WHERE id = ?", [$notice_id]);
    
    if (!$notice) {
        echo "<p style='color: red;'>Notice not found with ID $notice_id</p>";
        exit;
    }
    
    echo "<h2>Current Notice Data:</h2>";
    echo "<p><strong>Title:</strong> " . htmlspecialchars($notice['title']) . "</p>";
    echo "<p><strong>Attachments (Raw):</strong> " . ($notice['attachments'] ?: 'NULL') . "</p>";
    
    // Check if attachments exist in the database
    $attachments = [];
    if ($notice['attachments']) {
        $attachments = json_decode($notice['attachments'], true);
        if ($attachments) {
            echo "<p><strong>Decoded Attachments:</strong></p>";
            echo "<pre>" . print_r($attachments, true) . "</pre>";
        } else {
            echo "<p style='color: orange;'>Failed to decode attachments JSON</p>";
        }
    } else {
        echo "<p style='color: orange;'>No attachments in database</p>";
    }
    
    // Check for image files in the uploads/notices directory
    $uploads_dir = __DIR__ . "/../uploads/notices/";
    $image_files = glob($uploads_dir . "*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
    
    echo "<h2>Image Files Found in uploads/notices/:</h2>";
    if (empty($image_files)) {
        echo "<p style='color: red;'>No image files found in uploads/notices/</p>";
    } else {
        foreach ($image_files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            echo "<p><strong>File:</strong> $filename</p>";
            echo "<p><strong>Size:</strong> " . number_format($filesize) . " bytes (" . round($filesize / 1024 / 1024, 2) . " MB)</p>";
            echo "<p><strong>URL:</strong> uploads/notices/$filename</p>";
            
            // Test image display
            echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
            echo "<h4>Image Preview:</h4>";
            echo "<img src='uploads/notices/$filename' style='max-width: 400px; max-height: 300px; border: 1px solid #ddd;' alt='$filename'>";
            echo "</div>";
        }
    }
    
    // If no attachments in database but images exist, create proper attachment data
    if (empty($attachments) && !empty($image_files)) {
        echo "<h2>Creating Attachment Data:</h2>";
        
        $new_attachments = [];
        foreach ($image_files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $new_attachments[] = [
                'name' => $filename,
                'path' => $file,
                'url' => 'uploads/notices/' . $filename,
                'size' => $filesize,
                'type' => 'image/' . $file_ext
            ];
        }
        
        $attachments_json = json_encode($new_attachments);
        echo "<p><strong>New Attachments JSON:</strong></p>";
        echo "<pre>" . htmlspecialchars($attachments_json) . "</pre>";
        
        // Update the notice
        db()->execute(
            "UPDATE notices SET attachments = ? WHERE id = ?",
            [$attachments_json, $notice_id]
        );
        
        echo "<p style='color: green;'><strong>Notice updated successfully!</strong></p>";
        
        // Refresh notice data
        $notice = db()->fetchOne("SELECT * FROM notices WHERE id = ?", [$notice_id]);
        $attachments = json_decode($notice['attachments'], true);
    }
    
    // Test the notice display
    if (!empty($attachments)) {
        echo "<h2>Testing Notice Display:</h2>";
        echo "<div style='border: 2px solid #007bff; margin: 20px 0; padding: 20px;'>";
        echo "<h3>Notice: " . htmlspecialchars($notice['title']) . "</h3>";
        echo "<p>" . htmlspecialchars(substr($notice['content'], 0, 200)) . "...</p>";
        
        echo "<h4>Attachments:</h4>";
        echo "<div class='row'>";
        foreach ($attachments as $index => $attachment) {
            $file_ext = strtolower(pathinfo($attachment['name'], PATHINFO_EXTENSION));
            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            
            echo "<div class='col-md-6 col-lg-4 mb-3'>";
            echo "<div class='card'>";
            echo "<div class='card-body'>";
            
            if ($is_image) {
                echo "<div class='text-center mb-2'>";
                echo "<img src='" . htmlspecialchars($attachment['url']) . "' class='img-fluid rounded' style='max-height: 200px; object-fit: cover;' alt='" . htmlspecialchars($attachment['name']) . "'>";
                echo "</div>";
            } else {
                echo "<div class='text-center mb-2'>";
                echo "<i class='fas fa-file fa-3x text-muted'></i>";
                echo "</div>";
            }
            
            echo "<h6 class='card-title'>" . htmlspecialchars($attachment['name']) . "</h6>";
            echo "<small class='text-muted'>" . number_format($attachment['size']) . " bytes</small>";
            
            echo "<div class='mt-2'>";
            echo "<a href='" . htmlspecialchars($attachment['url']) . "' class='btn btn-sm btn-outline-primary w-100' target='_blank'>";
            echo "<i class='fas fa-" . ($is_image ? 'eye' : 'download') . "'></i> " . ($is_image ? 'View' : 'Download');
            echo "</a>";
            echo "</div>";
            
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    echo "<h2>Next Steps:</h2>";
    echo "<p><a href='notice.php?id=$notice_id' class='btn btn-primary'>View the actual notice page</a></p>";
    echo "<p><a href='notices.php' class='btn btn-secondary'>Back to notices list</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>









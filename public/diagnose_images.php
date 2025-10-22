<?php
/**
 * Diagnose Image Loading Issues
 */

echo "<h1>Image Loading Diagnosis</h1>";

// Check if the image file exists
$image_file = "68d22d1ab5c71_20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg";
$image_path = __DIR__ . "/../uploads/notices/" . $image_file;
$image_url = "uploads/notices/" . $image_file;

echo "<h2>File System Check:</h2>";
echo "<p><strong>Image file:</strong> $image_file</p>";
echo "<p><strong>Full path:</strong> $image_path</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($image_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($image_path)) {
    $file_size = filesize($image_path);
    echo "<p><strong>File size:</strong> " . number_format($file_size) . " bytes (" . round($file_size / 1024 / 1024, 2) . " MB)</p>";
    
    // Test direct image access
    echo "<h2>Direct Image Access Test:</h2>";
    echo "<p><strong>URL:</strong> <a href='$image_url' target='_blank'>$image_url</a></p>";
    echo "<img src='$image_url' style='max-width: 300px; max-height: 200px; border: 1px solid #ccc;' alt='Test Image'>";
}

// Check database connection and notice data
echo "<h2>Database Check:</h2>";
try {
    require_once __DIR__ . '/../includes/database.php';
    
    $notice = db()->fetchOne("SELECT * FROM notices WHERE id = 1");
    if ($notice) {
        echo "<p><strong>Notice found:</strong> YES</p>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($notice['title']) . "</p>";
        echo "<p><strong>Attachments:</strong> " . ($notice['attachments'] ?: 'NULL') . "</p>";
        
        if ($notice['attachments']) {
            $attachments = json_decode($notice['attachments'], true);
            if ($attachments) {
                echo "<p><strong>Decoded attachments:</strong></p>";
                echo "<pre>" . print_r($attachments, true) . "</pre>";
            } else {
                echo "<p style='color: red;'><strong>Failed to decode attachments JSON</strong></p>";
            }
        }
    } else {
        echo "<p style='color: red;'><strong>No notice found with ID 1</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Database error:</strong> " . $e->getMessage() . "</p>";
}

// Test different URL formats
echo "<h2>URL Format Tests:</h2>";
$test_urls = [
    "uploads/notices/$image_file",
    "../uploads/notices/$image_file", 
    "/uploads/notices/$image_file",
    "./uploads/notices/$image_file"
];

foreach ($test_urls as $test_url) {
    echo "<p><strong>Testing:</strong> $test_url</p>";
    echo "<img src='$test_url' style='max-width: 150px; max-height: 100px; border: 1px solid #ddd; margin: 5px;' alt='Test' onerror='this.style.border=\"2px solid red\"; this.alt=\"FAILED: $test_url\";'>";
    echo "<br>";
}
?>









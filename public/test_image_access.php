<?php
/**
 * Test Image Access - Check if images are accessible from web
 */

echo "<h1>Image Access Test</h1>";

$image_file = "68d22d1ab5c71_20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg";
$image_path = __DIR__ . "/../uploads/notices/" . $image_file;

echo "<h2>File System Check:</h2>";
echo "<p><strong>Image file:</strong> $image_file</p>";
echo "<p><strong>Full path:</strong> $image_path</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($image_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($image_path)) {
    $file_size = filesize($image_path);
    echo "<p><strong>File size:</strong> " . number_format($file_size) . " bytes (" . round($file_size / 1024 / 1024, 2) . " MB)</p>";
    
    // Test different URL formats
    $test_urls = [
        "uploads/notices/$image_file",
        "../uploads/notices/$image_file",
        "/uploads/notices/$image_file",
        "./uploads/notices/$image_file"
    ];
    
    echo "<h2>URL Format Tests:</h2>";
    foreach ($test_urls as $index => $test_url) {
        echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
        echo "<h3>Test " . ($index + 1) . ": $test_url</h3>";
        echo "<p><a href='$test_url' target='_blank'>Direct Link</a></p>";
        echo "<img src='$test_url' style='max-width: 200px; max-height: 150px; border: 1px solid #ddd;' alt='Test $index' onerror='this.style.border=\"3px solid red\"; this.alt=\"FAILED: $test_url\";'>";
        echo "</div>";
    }
    
    // Test with absolute URL
    $absolute_url = "http://localhost/uploads/notices/$image_file";
    echo "<h2>Absolute URL Test:</h2>";
    echo "<p><strong>Absolute URL:</strong> <a href='$absolute_url' target='_blank'>$absolute_url</a></p>";
    echo "<img src='$absolute_url' style='max-width: 200px; max-height: 150px; border: 1px solid #ddd;' alt='Absolute URL Test' onerror='this.style.border=\"3px solid red\"; this.alt=\"FAILED: $absolute_url\";'>";
    
} else {
    echo "<p style='color: red;'>Image file not found!</p>";
}

// Check if uploads directory is accessible
echo "<h2>Directory Access Test:</h2>";
$uploads_dir = __DIR__ . "/../uploads/notices/";
echo "<p><strong>Uploads directory:</strong> $uploads_dir</p>";
echo "<p><strong>Directory exists:</strong> " . (is_dir($uploads_dir) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Directory readable:</strong> " . (is_readable($uploads_dir) ? 'YES' : 'NO') . "</p>";

if (is_dir($uploads_dir)) {
    $files = scandir($uploads_dir);
    echo "<p><strong>Files in directory:</strong></p>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
}
?>









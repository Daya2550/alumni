<?php
/**
 * Test Image Access
 */

$image_file = "68d22d1ab5c71_20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg";
$image_path = __DIR__ . "/../uploads/notices/" . $image_file;
$image_url = "uploads/notices/" . $image_file;

echo "<h2>Image Access Test</h2>";
echo "<p><strong>Image file:</strong> $image_file</p>";
echo "<p><strong>Full path:</strong> $image_path</p>";
echo "<p><strong>URL path:</strong> $image_url</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($image_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($image_path)) {
    $file_size = filesize($image_path);
    echo "<p><strong>File size:</strong> " . number_format($file_size) . " bytes (" . round($file_size / 1024 / 1024, 2) . " MB)</p>";
    
    echo "<h3>Image Display Test:</h3>";
    echo "<img src='$image_url' style='max-width: 500px; max-height: 300px; border: 1px solid #ccc;' alt='Test Image'>";
    
    echo "<h3>Direct Link Test:</h3>";
    echo "<p><a href='$image_url' target='_blank'>Open image in new tab</a></p>";
}
?>









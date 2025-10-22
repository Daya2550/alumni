<?php
/**
 * Test Fix - Verify image access after .htaccess fix
 */

echo "<h1>Image Access Test After Fix</h1>";

$image_file = "68d22d1ab5c71_20250923_OHR.ToucanForest_EN-IN2300582458_UHD_bing.jpg";
$image_url = "uploads/notices/$image_file";
$absolute_url = "http://localhost/uploads/notices/$image_file";

echo "<h2>Testing Image Access:</h2>";
echo "<p><strong>Relative URL:</strong> $image_url</p>";
echo "<p><strong>Absolute URL:</strong> <a href='$absolute_url' target='_blank'>$absolute_url</a></p>";

echo "<h3>Image Display Test:</h3>";
echo "<img src='$image_url' style='max-width: 400px; max-height: 300px; border: 2px solid #007bff;' alt='Test Image' onerror='this.style.border=\"3px solid red\"; this.alt=\"FAILED: $image_url\";'>";

echo "<h3>Direct Link Test:</h3>";
echo "<p><a href='$image_url' target='_blank' class='btn btn-primary'>Open Image in New Tab</a></p>";

echo "<h2>Next Steps:</h2>";
echo "<p><a href='notices.php' class='btn btn-success'>Test Notices Page</a></p>";
echo "<p><a href='fix_database.php' class='btn btn-warning'>Fix Database (if needed)</a></p>";
?>









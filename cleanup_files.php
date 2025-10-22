<?php
/**
 * File Cleanup Utility Script
 * Run this script periodically to clean up orphaned files
 * 
 * Usage:
 * php cleanup_files.php [--dry-run] [--verbose] [--force]
 */

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/file_cleanup.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'verbose', 'force', 'help']);

if (isset($options['help'])) {
    echo "File Cleanup Utility\n";
    echo "Usage: php cleanup_files.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run    Show what would be deleted without actually deleting\n";
    echo "  --verbose    Show detailed output\n";
    echo "  --force      Force cleanup of all files in cleanup log\n";
    echo "  --help       Show this help message\n\n";
    exit(0);
}

$dry_run = isset($options['dry-run']);
$verbose = isset($options['verbose']);
$force = isset($options['force']);

echo "=== Alumni Portal File Cleanup Utility ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

if ($dry_run) {
    echo "DRY RUN MODE - No files will actually be deleted\n";
}

echo "\n";

try {
    // Get cleanup statistics
    $stats = FileCleanup::getCleanupStats();
    
    echo "=== Cleanup Statistics ===\n";
    echo "Pending cleanup files: " . $stats['pending_cleanup'] . "\n";
    echo "Total upload size: " . formatBytes($stats['total_upload_size']) . "\n";
    
    if ($verbose && !empty($stats['upload_directories'])) {
        echo "\nUpload directories:\n";
        foreach ($stats['upload_directories'] as $dir => $info) {
            echo "  {$dir}/: {$info['file_count']} files (" . formatBytes($info['size']) . ")\n";
        }
    }
    
    echo "\n=== Processing Cleanup Log ===\n";
    
    // Get files from cleanup log
    $cleanup_files = db()->fetchAll(
        $force ? 
        "SELECT file_path, deleted_at FROM attachment_cleanup_log ORDER BY deleted_at" :
        "SELECT file_path, deleted_at FROM attachment_cleanup_log WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY deleted_at"
    );
    
    if (empty($cleanup_files)) {
        echo "No files to clean up.\n";
    } else {
        echo "Found " . count($cleanup_files) . " files to clean up:\n\n";
        
        $deleted_count = 0;
        $failed_count = 0;
        $total_size_freed = 0;
        
        foreach ($cleanup_files as $file) {
            $file_path = $file['file_path'];
            $deleted_at = $file['deleted_at'];
            
            // Get full path
            $full_path = __DIR__ . '/' . $file_path;
            $full_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_path);
            
            // Check if file exists and get size
            $file_size = 0;
            $file_exists = file_exists($full_path);
            if ($file_exists) {
                $file_size = filesize($full_path);
            }
            
            if ($verbose || $dry_run) {
                $status = $file_exists ? "EXISTS" : "MISSING";
                $size_str = $file_exists ? "(" . formatBytes($file_size) . ")" : "";
                echo "  [{$status}] {$file_path} {$size_str} (marked for deletion: {$deleted_at})\n";
            }
            
            if (!$dry_run) {
                if ($file_exists) {
                    // Security check - ensure file is within uploads directory
                    $uploads_dir = realpath(__DIR__ . '/uploads/');
                    $file_real_path = realpath($full_path);
                    
                    if ($file_real_path && $uploads_dir && strpos($file_real_path, $uploads_dir) === 0) {
                        if (@unlink($file_real_path)) {
                            $deleted_count++;
                            $total_size_freed += $file_size;
                            if ($verbose) {
                                echo "    ✓ Deleted successfully\n";
                            }
                        } else {
                            $failed_count++;
                            if ($verbose) {
                                echo "    ✗ Failed to delete\n";
                            }
                        }
                    } else {
                        $failed_count++;
                        if ($verbose) {
                            echo "    ✗ Security check failed - file outside uploads directory\n";
                        }
                    }
                } else {
                    // File already doesn't exist, count as success
                    $deleted_count++;
                }
            }
        }
        
        if (!$dry_run) {
            // Remove processed entries from cleanup log
            $deleted_entries = db()->execute(
                $force ?
                "DELETE FROM attachment_cleanup_log" :
                "DELETE FROM attachment_cleanup_log WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            
            echo "\n=== Cleanup Results ===\n";
            echo "Files processed: " . count($cleanup_files) . "\n";
            echo "Files deleted: {$deleted_count}\n";
            echo "Files failed: {$failed_count}\n";
            echo "Space freed: " . formatBytes($total_size_freed) . "\n";
            echo "Cleanup log entries removed: {$deleted_entries}\n";
        } else {
            echo "\nDRY RUN - No files were actually deleted\n";
            echo "Would have processed " . count($cleanup_files) . " files\n";
        }
    }
    
    // Additional cleanup operations
    echo "\n=== Additional Cleanup ===\n";
    
    // Clean up empty directories
    $empty_dirs = findEmptyDirectories(__DIR__ . '/uploads/');
    if (!empty($empty_dirs)) {
        echo "Found " . count($empty_dirs) . " empty directories:\n";
        foreach ($empty_dirs as $dir) {
            $rel_path = str_replace(__DIR__ . '/', '', $dir);
            echo "  {$rel_path}\n";
            if (!$dry_run) {
                if (@rmdir($dir)) {
                    echo "    ✓ Removed\n";
                } else {
                    echo "    ✗ Failed to remove\n";
                }
            }
        }
        if ($dry_run) {
            echo "DRY RUN - No directories were actually removed\n";
        }
    } else {
        echo "No empty directories found.\n";
    }
    
    // Clean up old temporary files
    $temp_dir = __DIR__ . '/uploads/temp/';
    if (is_dir($temp_dir)) {
        $temp_files = glob($temp_dir . '*');
        $old_temp_files = [];
        
        foreach ($temp_files as $temp_file) {
            if (is_file($temp_file) && filemtime($temp_file) < strtotime('-24 hours')) {
                $old_temp_files[] = $temp_file;
            }
        }
        
        if (!empty($old_temp_files)) {
            echo "Found " . count($old_temp_files) . " old temporary files:\n";
            foreach ($old_temp_files as $temp_file) {
                $rel_path = str_replace(__DIR__ . '/', '', $temp_file);
                echo "  {$rel_path}\n";
                if (!$dry_run) {
                    if (@unlink($temp_file)) {
                        echo "    ✓ Deleted\n";
                    } else {
                        echo "    ✗ Failed to delete\n";
                    }
                }
            }
            if ($dry_run) {
                echo "DRY RUN - No temporary files were actually deleted\n";
            }
        } else {
            echo "No old temporary files found.\n";
        }
    }
    
    echo "\n=== Cleanup Complete ===\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log('File cleanup error: ' . $e->getMessage());
    exit(1);
}

/**
 * Format bytes into human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Find empty directories recursively
 */
function findEmptyDirectories($dir) {
    $empty_dirs = [];
    
    if (!is_dir($dir)) {
        return $empty_dirs;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $path) {
        if ($path->isDir()) {
            $dir_path = $path->getPathname();
            // Check if directory is empty
            $files = scandir($dir_path);
            if (count($files) <= 2) { // Only . and .. entries
                $empty_dirs[] = $dir_path;
            }
        }
    }
    
    return $empty_dirs;
}
?>

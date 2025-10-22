<?php
/**
 * Centralized File Cleanup System
 * Handles automatic deletion of files when parent records are deleted
 */

class FileCleanup {
    
    /**
     * Clean up all files associated with a user
     */
    public static function cleanupUserFiles($user_id) {
        $files_deleted = 0;
        
        try {
            // Get user profile photo
            $user = db()->fetchOne("SELECT profile_photo FROM users WHERE id = ?", [$user_id]);
            if ($user && $user['profile_photo']) {
                self::deleteFile($user['profile_photo']);
                $files_deleted++;
            }
            
            // Get all feed post media
            $feed_posts = db()->fetchAll("SELECT media_urls FROM feed_posts WHERE author_id = ?", [$user_id]);
            foreach ($feed_posts as $post) {
                if ($post['media_urls']) {
                    $media_urls = json_decode($post['media_urls'], true);
                    if (is_array($media_urls)) {
                        foreach ($media_urls as $url) {
                            self::deleteFile($url);
                            $files_deleted++;
                        }
                    }
                }
            }
            
            // Get all news post media
            $news_posts = db()->fetchAll("SELECT media_urls FROM news_posts WHERE author_id = ?", [$user_id]);
            foreach ($news_posts as $post) {
                if ($post['media_urls']) {
                    $media_urls = json_decode($post['media_urls'], true);
                    if (is_array($media_urls)) {
                        foreach ($media_urls as $url) {
                            self::deleteFile($url);
                            $files_deleted++;
                        }
                    }
                }
            }
            
            // Get all notice attachments
            $notices = db()->fetchAll("SELECT attachments FROM notices WHERE posted_by = ?", [$user_id]);
            foreach ($notices as $notice) {
                if ($notice['attachments']) {
                    $attachments = json_decode($notice['attachments'], true);
                    if (is_array($attachments)) {
                        foreach ($attachments as $attachment) {
                            if (isset($attachment['path'])) {
                                self::deleteFile($attachment['path']);
                                $files_deleted++;
                            }
                        }
                    }
                }
            }
            
            // Get all message files
            $messages = db()->fetchAll("SELECT file_path FROM messages WHERE sender_id = ? AND file_path IS NOT NULL", [$user_id]);
            foreach ($messages as $message) {
                self::deleteFile($message['file_path']);
                $files_deleted++;
            }
            
            // Get all gallery media uploaded by user
            $gallery_media = db()->fetchAll("SELECT file_path FROM gallery_media WHERE uploaded_by = ?", [$user_id]);
            foreach ($gallery_media as $media) {
                self::deleteFile($media['file_path']);
                $files_deleted++;
            }
            
            // Get all project attachments uploaded by user
            $project_attachments = db()->fetchAll("SELECT file_path FROM project_attachments WHERE uploader_id = ?", [$user_id]);
            foreach ($project_attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
            
            // Get all event attachments uploaded by user
            $event_attachments = db()->fetchAll("SELECT file_path FROM event_attachments WHERE uploader_id = ?", [$user_id]);
            foreach ($event_attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
            
            // Get all job attachments uploaded by user
            $job_attachments = db()->fetchAll("SELECT file_path FROM job_attachments WHERE uploader_id = ?", [$user_id]);
            foreach ($job_attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
            
            // Get all job logos posted by user
            $jobs = db()->fetchAll("SELECT company_logo FROM job_opportunities WHERE posted_by = ? AND company_logo IS NOT NULL", [$user_id]);
            foreach ($jobs as $job) {
                self::deleteFile($job['company_logo']);
                $files_deleted++;
            }
            
        } catch (Exception $e) {
            error_log('User file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific notice
     */
    public static function cleanupNoticeFiles($notice_id) {
        $files_deleted = 0;
        
        try {
            $notice = db()->fetchOne("SELECT attachments FROM notices WHERE id = ?", [$notice_id]);
            if ($notice && $notice['attachments']) {
                $attachments = json_decode($notice['attachments'], true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['path'])) {
                            self::deleteFile($attachment['path']);
                            $files_deleted++;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Notice file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific news post
     */
    public static function cleanupNewsFiles($news_id) {
        $files_deleted = 0;
        
        try {
            $news = db()->fetchOne("SELECT media_urls FROM news_posts WHERE id = ?", [$news_id]);
            if ($news && $news['media_urls']) {
                $media_urls = json_decode($news['media_urls'], true);
                if (is_array($media_urls)) {
                    foreach ($media_urls as $url) {
                        self::deleteFile($url);
                        $files_deleted++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('News file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific feed post
     */
    public static function cleanupFeedFiles($post_id) {
        $files_deleted = 0;
        
        try {
            $post = db()->fetchOne("SELECT media_urls FROM feed_posts WHERE id = ?", [$post_id]);
            if ($post && $post['media_urls']) {
                $media_urls = json_decode($post['media_urls'], true);
                if (is_array($media_urls)) {
                    foreach ($media_urls as $url) {
                        self::deleteFile($url);
                        $files_deleted++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Feed file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific job
     */
    public static function cleanupJobFiles($job_id) {
        $files_deleted = 0;
        
        try {
            // Get job logo
            $job = db()->fetchOne("SELECT company_logo FROM job_opportunities WHERE id = ?", [$job_id]);
            if ($job && $job['company_logo']) {
                self::deleteFile($job['company_logo']);
                $files_deleted++;
            }
            
            // Get job attachments (handled by CASCADE DELETE in database)
            $attachments = db()->fetchAll("SELECT file_path FROM job_attachments WHERE job_id = ?", [$job_id]);
            foreach ($attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
        } catch (Exception $e) {
            error_log('Job file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific event
     */
    public static function cleanupEventFiles($event_id) {
        $files_deleted = 0;
        
        try {
            // Get event attachments (handled by CASCADE DELETE in database)
            $attachments = db()->fetchAll("SELECT file_path FROM event_attachments WHERE event_id = ?", [$event_id]);
            foreach ($attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
        } catch (Exception $e) {
            error_log('Event file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific project
     */
    public static function cleanupProjectFiles($project_id) {
        $files_deleted = 0;
        
        try {
            // Get project attachments (handled by CASCADE DELETE in database)
            $attachments = db()->fetchAll("SELECT file_path FROM project_attachments WHERE project_id = ?", [$project_id]);
            foreach ($attachments as $attachment) {
                self::deleteFile($attachment['file_path']);
                $files_deleted++;
            }
        } catch (Exception $e) {
            error_log('Project file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific gallery album
     */
    public static function cleanupAlbumFiles($album_id) {
        $files_deleted = 0;
        
        try {
            // Get all media in the album
            $media_items = db()->fetchAll("SELECT file_path FROM gallery_media WHERE album_id = ?", [$album_id]);
            foreach ($media_items as $media) {
                self::deleteFile($media['file_path']);
                $files_deleted++;
            }
        } catch (Exception $e) {
            error_log('Album file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Clean up files for a specific message
     */
    public static function cleanupMessageFiles($message_id) {
        $files_deleted = 0;
        
        try {
            $message = db()->fetchOne("SELECT file_path FROM message_messages WHERE id = ? AND file_path IS NOT NULL", [$message_id]);
            if ($message && $message['file_path']) {
                self::deleteFile($message['file_path']);
                $files_deleted++;
            }
        } catch (Exception $e) {
            error_log('Message file cleanup error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Delete a single file from the filesystem
     */
    private static function deleteFile($file_path) {
        if (empty($file_path)) return false;
        
        // Handle both relative and absolute paths
        if (strpos($file_path, '/') === 0 || strpos($file_path, '\\') === 0) {
            // Absolute path
            $full_path = $file_path;
        } else {
            // Relative path - assume it's relative to project root
            $full_path = __DIR__ . '/../' . $file_path;
        }
        
        // Normalize path separators
        $full_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_path);
        
        // Security check - ensure file is within uploads directory
        $uploads_dir = realpath(__DIR__ . '/../uploads/');
        $file_real_path = realpath($full_path);
        
        if ($file_real_path && $uploads_dir && strpos($file_real_path, $uploads_dir) === 0) {
            if (file_exists($file_real_path)) {
                $deleted = @unlink($file_real_path);
                if ($deleted) {
                    error_log("File cleanup: Deleted {$file_path}");
                    return true;
                } else {
                    error_log("File cleanup: Failed to delete {$file_path}");
                }
            }
        } else {
            error_log("File cleanup: Security check failed for {$file_path}");
        }
        
        return false;
    }
    
    /**
     * Clean up orphaned files from cleanup log
     */
    public static function processCleanupLog() {
        $files_deleted = 0;
        
        try {
            // Get files older than 1 hour from cleanup log
            $cleanup_files = db()->fetchAll(
                "SELECT file_path FROM attachment_cleanup_log WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            
            foreach ($cleanup_files as $file) {
                if (self::deleteFile($file['file_path'])) {
                    $files_deleted++;
                }
            }
            
            // Clean up processed entries
            db()->execute("DELETE FROM attachment_cleanup_log WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            
        } catch (Exception $e) {
            error_log('Cleanup log processing error: ' . $e->getMessage());
        }
        
        return $files_deleted;
    }
    
    /**
     * Get file cleanup statistics
     */
    public static function getCleanupStats() {
        try {
            $stats = [
                'pending_cleanup' => 0,
                'total_upload_size' => 0,
                'upload_directories' => []
            ];
            
            // Count pending cleanup files
            $pending = db()->fetchOne("SELECT COUNT(*) as count FROM attachment_cleanup_log");
            $stats['pending_cleanup'] = $pending['count'] ?? 0;
            
            // Calculate total upload directory size
            $uploads_dir = __DIR__ . '/../uploads/';
            if (is_dir($uploads_dir)) {
                $stats['total_upload_size'] = self::getDirectorySize($uploads_dir);
                $stats['upload_directories'] = self::getDirectoryStructure($uploads_dir);
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log('Cleanup stats error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Get directory size recursively
     */
    private static function getDirectorySize($directory) {
        $size = 0;
        if (is_dir($directory)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        return $size;
    }
    
    /**
     * Get directory structure with file counts
     */
    private static function getDirectoryStructure($directory) {
        $structure = [];
        if (is_dir($directory)) {
            $dirs = scandir($directory);
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($directory . $dir)) {
                    $file_count = 0;
                    $dir_size = 0;
                    $sub_dir = $directory . $dir . '/';
                    
                    if (is_dir($sub_dir)) {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sub_dir));
                        foreach ($files as $file) {
                            if ($file->isFile()) {
                                $file_count++;
                                $dir_size += $file->getSize();
                            }
                        }
                    }
                    
                    $structure[$dir] = [
                        'file_count' => $file_count,
                        'size' => $dir_size
                    ];
                }
            }
        }
        return $structure;
    }
}
?>

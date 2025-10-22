<?php
/**
 * Database Setup Page
 * Access this through: http://localhost/setup_database.php
 */

require_once __DIR__ . '/includes/database.php';

echo "<!DOCTYPE html><html><head><title>Database Setup</title></head><body>";
echo "<h1>Database Setup</h1>";

function runSafeCreate($tableName, $createSql) {
    try {
        $exists = db()->fetchOne(
            "SELECT 1 AS exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$tableName]
        );
        if ($exists && (int)($exists['exists'] ?? 0) === 1) {
            echo "<p>Table $tableName already exists. Skipping.</p>";
            return true;
        }
    } catch (Exception $e) {
        // proceed
    }
    try {
        db()->execute($createSql);
        echo "<p style='color: green;'>Created table $tableName.</p>";
        return true;
    } catch (Exception $e) {
        echo "<p style='color: red;'>Failed creating $tableName: " . htmlspecialchars($e->getMessage()) . "</p>";
        return false;
    }
}

echo "<h2>Creating Project Tables</h2>";

// Create project_settings table
$success1 = runSafeCreate('project_settings', "
CREATE TABLE project_settings (
    id CHAR(36) NOT NULL,
    project_id CHAR(36) NOT NULL,
    require_file_approval TINYINT(1) NOT NULL DEFAULT 0,
    allow_public_contributions TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_project_settings (project_id),
    INDEX idx_ps_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create project_attachments table
$success2 = runSafeCreate('project_attachments', "
CREATE TABLE project_attachments (
    id CHAR(36) NOT NULL,
    project_id CHAR(36) NOT NULL,
    uploader_id CHAR(36) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pa_project (project_id),
    INDEX idx_pa_uploader (uploader_id),
    INDEX idx_pa_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create project_contributions table
$success3 = runSafeCreate('project_contributions', "
CREATE TABLE project_contributions (
    id CHAR(36) NOT NULL,
    project_id CHAR(36) NOT NULL,
    contributor_id CHAR(36) NOT NULL,
    type ENUM('comment', 'feedback', 'file') NOT NULL,
    message TEXT NULL,
    attachment_id CHAR(36) NULL,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pc_project (project_id),
    INDEX idx_pc_contributor (contributor_id),
    INDEX idx_pc_approved (is_approved),
    INDEX idx_pc_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create project_helpers table
$success4 = runSafeCreate('project_helpers', "
CREATE TABLE project_helpers (
    id CHAR(36) NOT NULL,
    project_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    role VARCHAR(100) NOT NULL DEFAULT 'helper',
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_project_helper (project_id, user_id),
    INDEX idx_ph_project (project_id),
    INDEX idx_ph_user (user_id),
    INDEX idx_ph_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create project_activity table
$success5 = runSafeCreate('project_activity', "
CREATE TABLE project_activity (
    id CHAR(36) NOT NULL,
    project_id CHAR(36) NOT NULL,
    actor_id CHAR(36) NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pa_project (project_id),
    INDEX idx_pa_actor (actor_id),
    INDEX idx_pa_type (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "<h2>Creating Upload Directories</h2>";

// Create upload directories
$upload_dirs = [
    __DIR__ . '/uploads/',
    __DIR__ . '/uploads/projects/',
    __DIR__ . '/uploads/temp/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<p style='color: green;'>Created directory: " . htmlspecialchars($dir) . "</p>";
        } else {
            echo "<p style='color: red;'>Failed to create directory: " . htmlspecialchars($dir) . "</p>";
        }
    } else {
        echo "<p>Directory already exists: " . htmlspecialchars($dir) . "</p>";
    }
}

echo "<h2>Setup Complete!</h2>";

if ($success1 && $success2 && $success3 && $success4 && $success5) {
    echo "<p style='color: green; font-weight: bold;'>All project-related tables have been created successfully!</p>";
    echo "<p><a href='public/project.php?id=74603479-b596-4d29-98b0-7a7d363d2ffc'>Test the project page</a></p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Some tables failed to create. Please check the errors above.</p>";
}

echo "</body></html>";
?>

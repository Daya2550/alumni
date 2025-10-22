<?php
/**
 * Fix Database Utilities
 * - Safe schema alterations and data fixes
 */

require_once __DIR__ . '/../includes/database.php';

echo "<h1>Fix Database Utilities</h1>";

function runSafeAlter($sql, $checkQuery, $checkParams = []) {
    try {
        $exists = db()->fetchOne($checkQuery, $checkParams);
        if ($exists && (isset($exists['exists']) ? (int)$exists['exists'] === 1 : false)) {
            echo "<p>Skip: Already applied.</p>";
            return;
        }
    } catch (Exception $e) {
        // Proceed with alter if check fails unexpectedly
    }
    try {
        db()->execute($sql);
        echo "<p style='color: green;'>Applied: " . htmlspecialchars($sql) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Failed: " . htmlspecialchars($sql) . "<br>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

function runSafeCreate($tableName, $createSql) {
    try {
        $exists = db()->fetchOne(
            "SELECT 1 AS exists FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$tableName]
        );
        if ($exists && (int)($exists['exists'] ?? 0) === 1) {
            echo "<p>Table $tableName already exists. Skipping.</p>";
            return;
        }
    } catch (Exception $e) {
        // proceed
    }
    try {
        db()->execute($createSql);
        echo "<p style='color: green;'>Created table $tableName.</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Failed creating $tableName: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<h2>Add users.department column (nullable) and index</h2>";
// Check column existence in INFORMATION_SCHEMA (MySQL)
$checkDept = "SELECT 1 AS exists FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department' LIMIT 1";
runSafeAlter(
    "ALTER TABLE users ADD COLUMN department VARCHAR(191) NULL AFTER batch",
    $checkDept
);

// Add index if missing
$checkIdx = "SELECT 1 AS exists FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_department' LIMIT 1";
runSafeAlter(
    "ALTER TABLE users ADD INDEX idx_users_department (department)",
    $checkIdx
);

echo "<h2>Add users.mobile column (nullable, unique optional) and index</h2>";
$checkMobile = "SELECT 1 AS exists FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mobile' LIMIT 1";
runSafeAlter(
    "ALTER TABLE users ADD COLUMN mobile VARCHAR(32) NULL AFTER email",
    $checkMobile
);
$checkMobileIdx = "SELECT 1 AS exists FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_mobile' LIMIT 1";
runSafeAlter(
    "ALTER TABLE users ADD INDEX idx_users_mobile (mobile)",
    $checkMobileIdx
);

echo "<hr>";
echo "<h2>Notice Attachments Fix (example)</h2>";
try {
    $notice_id = 1;
    $notice = db()->fetchOne("SELECT * FROM notices WHERE id = ?", [$notice_id]);
    if ($notice) {
        echo "<p>Notice ID 1 exists. Skipping data mutation by default.</p>";
    } else {
        echo "<p>No sample notice found; nothing to fix.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking notices: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>Create Surveys & Feedback Tables</h2>";

runSafeCreate('surveys', "
CREATE TABLE surveys (
    id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('draft','pending','approved','published','closed') NOT NULL DEFAULT 'draft',
    visibility ENUM('public','batch','private') NOT NULL DEFAULT 'public',
    requires_approval TINYINT(1) NOT NULL DEFAULT 0,
    owner_id CHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_surveys_owner (owner_id),
    INDEX idx_surveys_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

runSafeCreate('survey_questions', "
CREATE TABLE survey_questions (
    id CHAR(36) NOT NULL,
    survey_id CHAR(36) NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('single_choice','multiple_choice','text','rating') NOT NULL DEFAULT 'single_choice',
    options_json JSON NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sq_survey (survey_id),
    CONSTRAINT fk_sq_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

runSafeCreate('survey_responses', "
CREATE TABLE survey_responses (
    id CHAR(36) NOT NULL,
    survey_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    status ENUM('in_progress','submitted') NOT NULL DEFAULT 'submitted',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sr_survey (survey_id),
    INDEX idx_sr_user (user_id),
    CONSTRAINT fk_sr_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

runSafeCreate('survey_answers', "
CREATE TABLE survey_answers (
    id CHAR(36) NOT NULL,
    response_id CHAR(36) NOT NULL,
    question_id CHAR(36) NOT NULL,
    answer_text TEXT NULL,
    answer_value DECIMAL(10,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_sa_response (response_id),
    INDEX idx_sa_question (question_id),
    CONSTRAINT fk_sa_response FOREIGN KEY (response_id) REFERENCES survey_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

runSafeCreate('feedback', "
CREATE TABLE feedback (
    id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    category ENUM('event','job','portal','other') NOT NULL DEFAULT 'portal',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_type ENUM('none','event','job','news','notice') NOT NULL DEFAULT 'none',
    target_id CHAR(36) NULL,
    status ENUM('new','in_review','resolved','closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_feedback_user (user_id),
    INDEX idx_feedback_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
?>

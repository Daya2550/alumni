-- Message System Database Tables
-- Created: 2025-01-03

-- Message Rooms Table
CREATE TABLE IF NOT EXISTS message_rooms (
    id CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('group', 'batch', 'private') NOT NULL DEFAULT 'group',
    batch VARCHAR(50) NULL,
    created_by CHAR(36) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_message_rooms_type (type),
    INDEX idx_message_rooms_batch (batch),
    INDEX idx_message_rooms_created_by (created_by),
    INDEX idx_message_rooms_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message Participants Table
CREATE TABLE IF NOT EXISTS message_participants (
    id CHAR(36) NOT NULL,
    room_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_room_user (room_id, user_id),
    INDEX idx_message_participants_room (room_id),
    INDEX idx_message_participants_user (user_id),
    CONSTRAINT fk_message_participants_room FOREIGN KEY (room_id) REFERENCES message_rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message Messages Table
CREATE TABLE IF NOT EXISTS message_messages (
    id CHAR(36) NOT NULL,
    room_id CHAR(36) NOT NULL,
    sender_id CHAR(36) NOT NULL,
    message TEXT NULL,
    message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text',
    file_path VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_message_messages_room (room_id),
    INDEX idx_message_messages_sender (sender_id),
    INDEX idx_message_messages_created (created_at),
    INDEX idx_message_messages_deleted (deleted_at),
    CONSTRAINT fk_message_messages_room FOREIGN KEY (room_id) REFERENCES message_rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

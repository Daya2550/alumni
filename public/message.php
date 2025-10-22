<?php
/**
 * Message System - Main message interface (Enhanced UI)
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$room_id = $_GET['room_id'] ?? '';
$user_id = $_GET['user'] ?? '';

// Fallback server-side send (non-JS): handle POST and redirect back to same room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $post_room = $_POST['room_id'];
    $post_message = trim($_POST['message'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if ($post_room && Auth::verifyCSRFToken($csrf)) {
        // Ensure participant
        $is_participant = db()->fetchOne(
            "SELECT id FROM message_participants WHERE room_id = ? AND user_id = ?",
            [$post_room, $user['id']]
        );
        if ($is_participant) {
            try {
                if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../uploads/message/';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp','pdf','mp4','mov','avi','mkv'];
                    if (in_array($ext, $allowed, true)) {
                        $newName = uniqid('message_', true) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                            $publicPath = 'uploads/message/' . $newName;
                            $type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
                            db()->execute(
                                "INSERT INTO message_messages (room_id, sender_id, message, message_type, file_path) VALUES (?, ?, ?, ?, ?)",
                                [$post_room, $user['id'], sanitizeInput($post_message), $type, $publicPath]
                            );
                        }
                    }
                } elseif ($post_message !== '') {
                    db()->execute(
                        "INSERT INTO message_messages (room_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')",
                        [$post_room, $user['id'], sanitizeInput($post_message)]
                    );
                }
            } catch (Exception $e) {
                error_log('Chat fallback send error: ' . $e->getMessage());
            }
        }
    }
    header('Location: message.php?room_id=' . urlencode($post_room));
    exit;
}

// Get user's message rooms
$message_rooms = db()->fetchAll(
    "SELECT cr.id,
            cr.type,
            cr.batch,
            cr.created_at,
            CASE
                WHEN cr.type = 'private' THEN (
                    SELECT u.name
                    FROM message_participants mp
                    JOIN users u ON u.id = mp.user_id
                    WHERE mp.room_id = cr.id AND mp.user_id <> ?
                    LIMIT 1
                )
                ELSE cr.name
            END AS display_name,
            COUNT(cp.id) as participants_count,
            (SELECT cm.message FROM message_messages cm WHERE cm.room_id = cr.id ORDER BY cm.created_at DESC LIMIT 1) as last_message,
            (SELECT cm.created_at FROM message_messages cm WHERE cm.room_id = cr.id ORDER BY cm.created_at DESC LIMIT 1) as last_message_time
     FROM message_rooms cr
     LEFT JOIN message_participants cp ON cr.id = cp.room_id
     WHERE cr.id IN (
         SELECT room_id FROM message_participants WHERE user_id = ?
     ) AND cr.is_active = 1
     GROUP BY cr.id
     ORDER BY last_message_time DESC",
    [$user['id'], $user['id']]
);

// Get batch-wise rooms for the user
$batch_rooms = db()->fetchAll(
    "SELECT * FROM message_rooms 
     WHERE type = 'batch' AND batch = ? AND is_active = 1",
    [$user['batch']]
);

// Handle room creation for private message
if ($user_id && $user_id !== $user['id']) {
    // Check if private message room already exists
    $existing_room = db()->fetchOne(
        "SELECT cr.id FROM message_rooms cr
         JOIN message_participants cp1 ON cr.id = cp1.room_id
         JOIN message_participants cp2 ON cr.id = cp2.room_id
         WHERE cr.type = 'private' AND cp1.user_id = ? AND cp2.user_id = ?",
        [$user['id'], $user_id]
    );
    
    if (!$existing_room) {
        // Create new private message room
        $room_id = generateUUID();


        $target_user = db()->fetchOne("SELECT name, privacy_settings, batch FROM users WHERE id = ?", [$user_id]);
        
        if ($target_user) {
            // Enforce target user's message privacy
            $prefs = $target_user['privacy_settings'] ? json_decode($target_user['privacy_settings'], true) : [];
            $who = $prefs['allow_messages'] ?? 'everyone';
            if ($who === 'none') {
                header('Location: message.php');
                exit;
            }
            if ($who === 'batch') {
                $sameBatch = !empty($user['batch']) && $user['batch'] === ($target_user['batch'] ?? '');
                if (!$sameBatch && !Auth::hasAnyRole(['admin','staff'])) {
                    header('Location: message.php');
                    exit;
                }
            }
            try {
                db()->beginTransaction();
                
                // Create room
                db()->execute(
                    "INSERT INTO message_rooms (id, name, type, created_by) VALUES (?, ?, 'private', ?)",
                    [$room_id, "Message with " . $target_user['name'], $user['id']]
                );
                
                // Add participants
                db()->execute(
                    "INSERT INTO message_participants (room_id, user_id) VALUES (?, ?)",
                    [$room_id, $user['id']]
                );
                db()->execute(
                    "INSERT INTO message_participants (room_id, user_id) VALUES (?, ?)",
                    [$room_id, $user_id]
                );
                
                db()->commit();
            } catch (Exception $e) {
                db()->rollback();
                error_log("Message room creation error: " . $e->getMessage());
            }
        }
    } else {
        $room_id = $existing_room['id'];
    }
}

// Get current room details
$current_room = null;
$not_participant = false;
if ($room_id) {
    $current_room = db()->fetchOne(
        "SELECT cr.*, COUNT(cp.id) as participants_count
         FROM message_rooms cr
         LEFT JOIN message_participants cp ON cr.id = cp.room_id
         WHERE cr.id = ? AND cr.is_active = 1
         GROUP BY cr.id",
        [$room_id]
    );
    
    if ($current_room) {
        // Check if user is participant
        $is_participant = db()->fetchOne(
            "SELECT id FROM message_participants WHERE room_id = ? AND user_id = ?",
            [$room_id, $user['id']]
        );

        // Auto-join rules: batch rooms (same batch) and group rooms are open to join
        if (!$is_participant) {
            $canAutoJoin = false;
            if ($current_room['type'] === 'batch' && !empty($current_room['batch']) && $current_room['batch'] === ($user['batch'] ?? '')) {
                $canAutoJoin = true;
            }
            if ($current_room['type'] === 'group') {
                $canAutoJoin = true;
            }

            if ($canAutoJoin) {
                try {
                    db()->execute("INSERT INTO message_participants (room_id, user_id) VALUES (?, ?)", [$room_id, $user['id']]);
                    $is_participant = ['id' => true];
                } catch (Exception $e) {
                    error_log('Auto-join message failed: ' . $e->getMessage());
                }
            }
        }
        
        if (!$is_participant) {
            // Keep room visible but mark not a participant (for private rooms or disallowed joins)
            $not_participant = true;
        }
    }
}

// Compute chat display name for header (other participant for private rooms)
$chat_display_name = $current_room['name'] ?? '';
if ($current_room && ($current_room['type'] ?? '') === 'private') {
    $other = db()->fetchOne(
        "SELECT u.name FROM message_participants mp JOIN users u ON u.id = mp.user_id WHERE mp.room_id = ? AND mp.user_id <> ? LIMIT 1",
        [$room_id, $user['id']]
    );
    if ($other && !empty($other['name'])) { $chat_display_name = $other['name']; }
}

// Get room participants
$room_participants = [];
if ($current_room) {
    $room_participants = db()->fetchAll(
        "SELECT u.id, u.name, u.profile_photo, u.role, cp.joined_at
         FROM message_participants cp
         JOIN users u ON cp.user_id = u.id
         WHERE cp.room_id = ?
         ORDER BY cp.joined_at ASC",
        [$room_id]
    );
}

// Initial messages (server render) so the page isn't blank without JS
$initial_messages = [];
if ($current_room) {
    try {
        $initial_messages = db()->fetchAll(
            "SELECT cm.*, u.name as sender_name, u.profile_photo as sender_photo
             FROM message_messages cm
             JOIN users u ON cm.sender_id = u.id
             WHERE cm.room_id = ? AND cm.deleted_at IS NULL
             ORDER BY cm.created_at ASC
             LIMIT 100",
            [$room_id]
        );
    } catch (Exception $e) {
        $initial_messages = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo Auth::generateCSRFToken(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #e5ddd5; height: 100vh; overflow: hidden; }
        .app-topbar { height: 56px; background: #008069; color: #ffffff; display: flex; align-items: center; gap: 10px; padding: 0 12px; position: sticky; top: 0; z-index: 1000; }
        .app-topbar .btn-top { background: rgba(255,255,255,0.15); color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; }
        .app-topbar .btn-top:hover { background: rgba(255,255,255,0.25); }
        .container-chat { display: flex; height: calc(100vh - 75px); width: 100%; max-width: none; margin: 0; background: #ffffff; }
        .chat-list { width: 35%; border-right: 1px solid #e0e0e0; background: #ffffff; display: flex; flex-direction: column; }
        .chat-list-header { background: #008069; color: #ffffff; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .chat-list-header h2 { font-size: 20px; }
        .search-box { padding: 10px; background: #f6f6f6; }
        .search-box input { width: 100%; padding: 10px; border: none; border-radius: 20px; background: #ffffff; outline: none; }
        .chats { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
        .chat-item { display: flex; padding: 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; text-decoration: none; color: inherit; }
        .chat-item:hover, .chat-item.active { background: #f5f5f5; }
        .avatar { width: 50px; height: 50px; border-radius: 50%; background: #ddd; margin-right: 15px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #ffffff; font-weight: bold; }
        .chat-info { flex: 1; }
        .chat-info-header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .chat-name { font-weight: 600; font-size: 16px; }
        .chat-time { font-size: 12px; color: #667781; }
        .chat-preview { font-size: 14px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-window { width: 65%; display: flex; flex-direction: column; background: #e5ddd5; }
        .chat-window.hidden { display: none; }
        .chat-header { background: #008069; color: #ffffff; padding: 15px; display: flex; align-items: center; gap: 15px; }
        .chat-header .avatar { width: 40px; height: 40px; font-size: 18px; margin-right: 0; }
        .chat-header-info h3 { font-size: 16px; margin-bottom: 2px; }
        .chat-header-info .status { font-size: 12px; opacity: 0.9; }
        .messages { flex: 1; overflow-y: auto; padding: 20px; background: #ffffff; overscroll-behavior: contain; }
        .message { display: flex; margin-bottom: 15px; }
        .message.sent { justify-content: flex-end; }
        .message-content { max-width: 60%; padding: 10px 15px; border-radius: 8px; position: relative; }
        .message.received .message-content { background: #ffffff; border-radius: 0 8px 8px 8px; }
        .message.sent .message-content { background: #d9fdd3; border-radius: 8px 0 8px 8px; }
        .message-text { margin-bottom: 5px; word-wrap: break-word; }
        .message-time { font-size: 11px; color: #667781; text-align: right; display: flex; align-items: center; justify-content: flex-end; gap: 3px; }
        .checkmark { color: #53bdeb; font-size: 14px; }
        .message-input-container { background: #f0f0f0; padding: 10px; display: flex; gap: 10px; align-items: center; }
        .emoji-btn, .attach-btn, .send-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #54656f; padding: 5px; min-width: 40px; min-height: 40px; }
        .send-btn { background: #008069; color: #ffffff; border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .message-input { flex: 1; padding: 12px 15px; border: none; border-radius: 25px; outline: none; font-size: 15px; }
        .welcome-screen { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: #f8f9fa; }
        .welcome-screen h2 { color: #41525d; margin-top: 20px; }
        .welcome-screen p { color: #667781; margin-top: 10px; }
        @media (max-width: 768px) {
            body { height: 100dvh; }
            .container-chat { flex-direction: column; height: calc(100dvh - 56px); }
            .chat-list { width: 100%; display: flex; flex-direction: column; height: 100%; }
            .chat-window { width: 100%; position: fixed; top: 56px; left: 0; right: 0; bottom: 0; z-index: 10; height: calc(100dvh - 56px); display: flex; flex-direction: column; }
            .chat-list.hidden { display: none; }
            .messages { padding: 12px; -webkit-overflow-scrolling: touch; }
            .message-content { max-width: 85%; }
            .message-input-container { position: sticky; bottom: 0; padding-bottom: calc(10px + env(safe-area-inset-bottom)); background: #f0f0f0; }
            #backBtn { display: inline-block !important; }
        }
    </style>
</head>
<body data-user-id="<?php echo $user['id']; ?>">
<?php include 'includes/header.php'; ?>

    <main class="container-fluid p-0">
        <div class="container-chat">
            <div class="chat-list" id="chatList">
                <div class="chat-list-header">
                    <h2>CHATS</h2>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-plus"></i>
                            </button>
                            <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="createBatchMessage()"><i class="fas fa-users"></i> Batch Message</a></li>
                                <?php if (Auth::hasRole('staff')): ?>
                            <li><a class="dropdown-item" href="#" onclick="createGroupMessage()"><i class="fas fa-user-plus"></i> Group Message</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <div class="search-box">
                    <input type="text" placeholder="Search or start new chat" id="searchInput">
                </div>
                <div class="chats" id="chatsContainer">
                        <?php if (!empty($batch_rooms)): ?>
                                    <?php foreach ($batch_rooms as $room): ?>
                            <a class="chat-item <?php echo $room_id === $room['id'] ? 'active' : ''; ?>" href="message.php?room_id=<?php echo $room['id']; ?>">
                                <div class="avatar" style="background:#4db6ac;">B</div>
                                <div class="chat-info">
                                    <div class="chat-info-header">
                                        <span class="chat-name"><?php echo htmlspecialchars($room['name']); ?></span>
                                        <span class="chat-time">Batch</span>
                                                </div>
                                    <div class="chat-preview"><?php echo htmlspecialchars($room['name']); ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                        <?php endif; ?>
                    <?php if (!empty($message_rooms)): ?>
                        <?php foreach ($message_rooms as $room): ?>
                            <a class="chat-item <?php echo $room_id === $room['id'] ? 'active' : ''; ?>" href="message.php?room_id=<?php echo $room['id']; ?>">
                                <div class="avatar" style="background:#7c4dff;"><?php echo strtoupper(substr(trim($room['display_name'] ?? $room['name']), 0, 1)); ?></div>
                                <div class="chat-info">
                                    <div class="chat-info-header">
                                        <span class="chat-name"><?php echo htmlspecialchars($room['display_name'] ?? $room['name']); ?></span>
                                        <span class="chat-time"><?php echo $room['last_message_time'] ? date('M d, H:i', strtotime($room['last_message_time'])) : ''; ?></span>
                                </div>
                                    <div class="chat-preview"><?php echo $room['last_message'] ? htmlspecialchars(substr($room['last_message'],0,60)) : 'No messages yet'; ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-muted">No message rooms yet</div>
                            <?php endif; ?>
                </div>
            </div>
            <div class="chat-window <?php echo $current_room ? '' : 'hidden'; ?>" id="chatWindow">
                <?php if ($current_room): ?>
                <div class="chat-header">
                    <button onclick="backToList()" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; display: none; padding: 6px 8px;" id="backBtn" aria-label="Back">←</button>
<div class="avatar" id="chatAvatar"><?php echo strtoupper(substr(trim($chat_display_name),0,1)); ?></div>
                    <div class="chat-header-info">
                        <h3 id="chatName"><?php echo htmlspecialchars($chat_display_name); ?></h3>
                        <div class="status"><?php echo (int)$current_room['participants_count']; ?> members</div>
                    </div>
                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-sm btn-light" type="button" onclick="viewParticipants()" title="View Participants"><i class="fas fa-users"></i></button>
                        <?php if (Auth::hasRole('staff') || $current_room['created_by'] === $user['id']): ?>
                        <button class="btn btn-sm btn-light" type="button" onclick="openManageMembers()" title="Manage Members"><i class="fas fa-user-cog"></i></button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-light" type="button" onclick="leaveMessage()" title="Leave Message"><i class="fas fa-sign-out-alt"></i></button>
                        <button class="btn btn-sm btn-danger" type="button" onclick="deleteMessageForAll()" title="Delete for All"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="messages" id="messagesContainer">
                            <?php foreach ($initial_messages as $m): ?>
                                <div class="message <?php echo $m['sender_id'] === $user['id'] ? 'sent' : 'received'; ?>">
                            <div class="message-content">
                                        <?php if ($m['message_type'] === 'image' && !empty($m['file_path'])): ?>
                                <div class="mb-2"><a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank"><img src="<?php echo htmlspecialchars($m['file_path']); ?>" class="img-fluid rounded"></a></div>
                                        <?php elseif ($m['message_type'] === 'file' && !empty($m['file_path'])): ?>
                                <div class="mb-2"><a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars(basename($m['file_path'])); ?></a></div>
                                        <?php endif; ?>
                                        <?php if (!empty($m['message'])): ?>
                                            <div class="message-text"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                        <?php endif; ?>
                                <div class="message-time"><?php echo date('H:i', strtotime($m['created_at'])); ?><?php if ($m['sender_id'] === $user['id']): ?><span class="checkmark">✓✓</span><?php endif; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                <?php if ($not_participant): ?>
                <div class="alert alert-warning m-2">
                    <i class="fas fa-info-circle"></i> You are not a participant in this room.
                    <button class="btn btn-sm btn-primary ms-2" onclick="joinRoom('<?php echo $room_id; ?>')"><i class="fas fa-sign-in-alt"></i> Join</button>
                </div>
                <?php endif; ?>
                <div class="message-input-container">
                    <button class="emoji-btn" type="button">😊</button>
                    <label class="attach-btn" for="messageFile" title="Attach file">📎</label>
                    <form id="messageForm" method="POST" action="message.php?room_id=<?php echo htmlspecialchars($room_id); ?>" enctype="multipart/form-data" autocomplete="off" style="display:contents">
                                <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">
                                    <input type="file" id="messageFile" name="file" style="display:none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.mp4,.mov,.avi,.mkv">
                        <input type="text" class="message-input" placeholder="Type a message" id="messageInput" name="message" maxlength="1000">
                        <button class="send-btn" type="submit" <?php echo $not_participant ? 'disabled' : ''; ?>>➤</button>
                            </form>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!$current_room): ?>
            <div class="welcome-screen" id="welcomeScreen" style="width:65%;">
                <div style="font-size: 80px;">💬</div>
                <h2>Chat App</h2>
                <p>Select a chat to start messaging</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Participants Modal -->
    <div class="modal fade participants-modal" id="participantsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Room Participants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($room_participants)): ?>
                        <div class="list-group">
                            <?php foreach ($room_participants as $participant): ?>
                                <div class="list-group-item d-flex align-items-center">
                                    <?php if ($participant['profile_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($participant['profile_photo']); ?>" 
                                             class="rounded-circle me-3" width="44" height="44" style="object-fit:cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle me-3 fa-2x text-muted"></i>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($participant['name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo ucfirst($participant['role']); ?> • 
                                            Joined <?php echo date('M j, Y', strtotime($participant['joined_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">No participants found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Members Modal -->
    <div class="modal fade" id="manageMembersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Members</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Search Users</label>
                            <input type="text" id="userSearchInput" class="form-control" placeholder="Search by name, email, or batch">
                            <div id="userSearchResults" class="list-group mt-2" style="max-height: 250px; overflow:auto"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Members</label>
                            <div id="currentMembers" class="list-group" style="max-height: 300px; overflow:auto"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Set current room for JavaScript
        window.currentMessageRoom = '<?php echo $room_id; ?>';
            // Back/Home header behavior and mobile back to list
            function backToList(){
                const list = document.getElementById('chatList');
                const win = document.getElementById('chatWindow');
                if (list && win){
                    list.classList.remove('hidden');
                    win.classList.add('hidden');
                }
                const backBtn = document.getElementById('backBtn');
                if (backBtn) backBtn.style.display = 'none';
            }
            function onHeaderBack(){
                const isMobile = window.innerWidth <= 768;
                const list = document.getElementById('chatList');
                if (isMobile && list && list.classList.contains('hidden')){ backToList(); return; }
                if (document.referrer && document.referrer !== location.href){ history.back(); } else { location.href = 'index.php'; }
            }
            document.getElementById('searchInput')?.addEventListener('input', function(e){
                const q = (e.target.value || '').toLowerCase();
                document.querySelectorAll('.chat-item').forEach(function(item){
                    const name = item.querySelector('.chat-name');
                    item.style.display = name && name.textContent.toLowerCase().includes(q) ? 'flex' : 'none';
                });
            });
        
        // Message functions
        function createBatchMessage() {
            if (!confirm('Create a new batch message room for your batch?')) return;
            
            const csrfToken = getCSRFToken();
            if (!csrfToken) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }
            
            fetch('api/message_create.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ type: 'batch', name: '', csrf_token: csrfToken })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.room_id) {
                    window.location.href = 'message.php?room_id=' + encodeURIComponent(data.room_id);
                } else {
                    alert(data.message || 'Failed to create batch message');
                }
            })
            .catch(error => {
                console.error('Error creating batch message:', error);
                alert('Network error: ' + error.message);
            });
        }
        
        function createGroupMessage() {
            createGroupMessageUI();
        }
        
        function viewParticipants() {
            new bootstrap.Modal(document.getElementById('participantsModal')).show();
        }
        
        function openManageMembers() {
            const modal = new bootstrap.Modal(document.getElementById('manageMembersModal'));
            loadCurrentMembers();
            document.getElementById('userSearchInput').value = '';
            document.getElementById('userSearchResults').innerHTML = '';
            modal.show();
        }

        // Search users
        document.getElementById('userSearchInput')?.addEventListener('input', function() {
            const q = this.value.trim();
            const results = document.getElementById('userSearchResults');
            if (q.length < 2) { results.innerHTML = ''; return; }
            fetch('api/users_search.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(d => {
                    if (!d.success) return;
                    results.innerHTML = '';
                    d.users.forEach(u => {
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        a.textContent = `${u.name} (${u.email}) ${u.batch ? '• ' + u.batch : ''}`;
                        a.addEventListener('click', (e) => {
                            e.preventDefault();
                            modifyMember('add', u.id);
                        });
                        results.appendChild(a);
                    });
                });
        });

        function loadCurrentMembers() {
            const roomId = getCurrentRoomId();
            if (!roomId) {
                console.error('No room ID found');
                return;
            }
            
            fetch('api/message_members.php?room_id=' + encodeURIComponent(roomId), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                const list = document.getElementById('currentMembers');
                if (!list) return;
                
                list.innerHTML = '';
                if (!data.success) {
                    console.error('Failed to load members:', data.message);
                    return;
                }
                
                data.members.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'list-group-item d-flex justify-content-between align-items-center';
                    div.innerHTML = `<span>${m.name} (${m.email}) ${m.batch ? '• ' + m.batch : ''}</span>`;
                    const btn = document.createElement('button');
                    btn.className = 'btn btn-sm btn-outline-danger';
                    btn.textContent = 'Remove';
                    btn.addEventListener('click', () => modifyMember('remove', m.id));
                    div.appendChild(btn);
                    list.appendChild(div);
                });
            })
            .catch(error => {
                console.error('Error loading members:', error);
                alert('Failed to load members: ' + error.message);
            });
        }

        function modifyMember(action, userId) {
            const roomId = getCurrentRoomId();
            if (!roomId) {
                alert('No room ID found');
                return;
            }
            
            const csrfToken = getCSRFToken();
            if (!csrfToken) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }
            
            fetch('api/message_members.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ room_id: roomId, user_id: userId, action, csrf_token: csrfToken })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    loadCurrentMembers();
                    if (action === 'add') {
                        const searchResults = document.getElementById('userSearchResults');
                        if (searchResults) searchResults.innerHTML = '';
                    }
                } else {
                    alert(data.message || 'Operation failed');
                }
            })
            .catch(error => {
                console.error('Error modifying member:', error);
                alert('Network error: ' + error.message);
            });
        }

        function createGroupMessageUI() {
            const name = prompt('Enter group message name:');
            if (!name) return;
            
            const csrfToken = getCSRFToken();
            if (!csrfToken) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }
            
            fetch('api/message_create.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ type: 'group', name, csrf_token: csrfToken })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = 'message.php?room_id=' + encodeURIComponent(data.room_id);
                } else {
                    alert(data.message || 'Failed to create message');
                }
            })
            .catch(error => {
                console.error('Error creating group message:', error);
                alert('Network error: ' + error.message);
            });
        }
        
        // Handle Enter key for sending messages
        document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });

        function getCurrentRoomId(){ 
            return window.currentMessageRoom || '<?php echo $room_id; ?>'; 
        }
        function getCSRFToken(){ 
            const token = document.querySelector('meta[name="csrf-token"]');
            return token ? token.getAttribute('content') : '';
        }

        function leaveMessage(){
            const roomId = getCurrentRoomId();
            const csrfToken = getCSRFToken();
            
            if (!roomId) {
                alert('No room ID found');
                return;
            }
            
            if (!csrfToken) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }
            
            if (!confirm('Leave this message?')) return;
            
            fetch('api/message_delete.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    room_id: roomId, 
                    action: 'leave', 
                    csrf_token: csrfToken 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Left message successfully');
                    window.location.href = 'message.php';
                } else {
                    alert(data.message || 'Failed to leave message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error: ' + error.message);
            });
        }

        function deleteMessageForAll(){
            const roomId = getCurrentRoomId();
            const csrfToken = getCSRFToken();
            
            if (!roomId) {
                alert('No room ID found');
                return;
            }
            
            if (!csrfToken) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }
            
            if (!confirm('Delete this message for all participants? This cannot be undone.')) return;
            
            fetch('api/message_delete.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ 
                    room_id: roomId, 
                    action: 'delete_for_all', 
                    csrf_token: csrfToken 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Message deleted successfully');
                    window.location.href = 'message.php';
                } else {
                    alert(data.message || 'Failed to delete message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error: ' + error.message);
            });
        }
    </script>
</body>
</html>

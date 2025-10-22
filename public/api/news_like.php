<?php
/**
 * News Like API Endpoint
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['post_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Post ID required']);
    exit;
}

$post_id = intval($input['post_id']);

// Verify CSRF token
if (!Auth::verifyCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Check if post exists
    $post = db()->fetchOne(
        "SELECT id FROM news_posts WHERE id = ? AND status = 'published'",
        [$post_id]
    );
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }
    
    // Check if user already liked this post
    $existing_like = db()->fetchOne(
        "SELECT id FROM news_likes WHERE post_id = ? AND user_id = ?",
        [$post_id, $user['id']]
    );
    
    if ($existing_like) {
        // Unlike the post
        db()->execute(
            "DELETE FROM news_likes WHERE post_id = ? AND user_id = ?",
            [$post_id, $user['id']]
        );
        
        $liked = false;
    } else {
        // Like the post
        db()->execute(
            "INSERT INTO news_likes (post_id, user_id) VALUES (?, ?)",
            [$post_id, $user['id']]
        );
        
        $liked = true;
    }
    
    // Get updated likes count
    $likes_count = db()->fetchOne(
        "SELECT COUNT(*) as count FROM news_likes WHERE post_id = ?",
        [$post_id]
    )['count'];
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likes_count' => $likes_count
    ]);
    
} catch (Exception $e) {
    error_log("News like error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>

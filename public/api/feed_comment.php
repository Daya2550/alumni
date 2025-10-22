<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user = Auth::getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['post_id']) || empty($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Post ID and content required']);
    exit;
}

if (!Auth::verifyCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$post_id = intval($input['post_id']);
$content = trim($input['content']);
if (strlen($content) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Comment too long']);
    exit;
}

try {
    $post = db()->fetchOne("SELECT id FROM feed_posts WHERE id = ?", [$post_id]);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    db()->execute("INSERT INTO feed_comments (post_id, user_id, content) VALUES (?, ?, ?)", [$post_id, $user['id'], sanitizeInput($content)]);
    $comments_count = db()->fetchOne("SELECT COUNT(*) AS count FROM feed_comments WHERE post_id = ?", [$post_id])['count'];

    echo json_encode(['success' => true, 'comments_count' => $comments_count]);
} catch (Exception $e) {
    error_log('Feed comment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>





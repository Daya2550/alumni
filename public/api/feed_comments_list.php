<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'post_id required']);
    exit;
}

try {
    $post = db()->fetchOne("SELECT id FROM feed_posts WHERE id = ?", [$post_id]);
    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    $comments = db()->fetchAll(
        "SELECT fc.id, fc.content, fc.created_at, u.name AS author_name, u.profile_photo AS author_photo
         FROM feed_comments fc
         JOIN users u ON u.id = fc.user_id
         WHERE fc.post_id = ?
         ORDER BY fc.created_at ASC
         LIMIT ? OFFSET ?",
        [$post_id, $limit, $offset]
    );

    $total = db()->fetchOne(
        "SELECT COUNT(*) AS total FROM feed_comments WHERE post_id = ?",
        [$post_id]
    )['total'];

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'page' => $page,
        'limit' => $limit,
        'total' => intval($total)
    ]);
} catch (Exception $e) {
    error_log('Feed comments list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>











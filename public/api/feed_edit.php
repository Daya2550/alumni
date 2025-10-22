<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    Auth::requireLogin();
    $user = Auth::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

    // Support both JSON and multipart form-data
    $isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
    if ($isMultipart) {
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $content = trim((string)($_POST['content'] ?? ''));
        $csrf = $_POST['csrf_token'] ?? '';
        $removeMedia = [];
        if (!empty($_POST['remove_media'])) {
            $decoded = json_decode($_POST['remove_media'], true);
            if (is_array($decoded)) { $removeMedia = array_values(array_filter(array_map('strval', $decoded))); }
        }
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) { throw new Exception('Invalid payload'); }
        $postId = isset($data['post_id']) ? (int)$data['post_id'] : 0;
        $content = trim((string)($data['content'] ?? ''));
        $csrf = $data['csrf_token'] ?? '';
        $removeMedia = [];
    }

    if (!Auth::verifyCSRFToken($csrf)) { throw new Exception('Invalid CSRF token'); }
    if ($postId <= 0) { throw new Exception('Invalid post id'); }
    if ($content === '' || strlen($content) > 1000) { throw new Exception('Invalid content'); }

    $post = db()->fetchOne("SELECT author_id, media_urls FROM feed_posts WHERE id = ?", [$postId]);
    if (!$post) { throw new Exception('Post not found'); }

    $canEdit = ($post['author_id'] === $user['id']) || Auth::hasAnyRole(['staff','admin']);
    if (!$canEdit) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

    $currentMedia = [];
    if (!empty($post['media_urls'])) {
        $decoded = json_decode($post['media_urls'], true);
        if (is_array($decoded)) { $currentMedia = $decoded; }
    }

    // Remove selected media
    if (!empty($removeMedia)) {
        $currentMedia = array_values(array_filter($currentMedia, function($u) use ($removeMedia){ return !in_array($u, $removeMedia, true); }));
    }

    // Handle new uploads
    if ($isMultipart && !empty($_FILES['media']['name'][0])) {
        $uploadDir = __DIR__ . '/../../uploads/feed/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        foreach ($_FILES['media']['name'] as $idx => $name) {
            $tmpPath = $_FILES['media']['tmp_name'][$idx] ?? '';
            $error = $_FILES['media']['error'][$idx] ?? UPLOAD_ERR_OK;
            if ($error === UPLOAD_ERR_OK && is_uploaded_file($tmpPath)) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp','mp4','mov','avi','mkv','pdf'];
                if (in_array($ext, $allowed, true)) {
                    $newName = uniqid('feed_', true) . '.' . $ext;
                    $dest = $uploadDir . $newName;
                    if (move_uploaded_file($tmpPath, $dest)) {
                        $publicPath = 'uploads/feed/' . $newName;
                        $currentMedia[] = $publicPath;
                    }
                }
            }
        }
    }

    db()->execute(
        "UPDATE feed_posts SET content = ?, media_urls = ? WHERE id = ?",
        [sanitizeInput($content), json_encode(array_values($currentMedia)), $postId]
    );

    echo json_encode(['success' => true, 'media_urls' => array_values($currentMedia)]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>











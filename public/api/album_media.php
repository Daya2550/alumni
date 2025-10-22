<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$album_id = intval($_GET['album_id'] ?? 0);
if (!$album_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'album_id required']);
    exit;
}

// Fetch album
$album = db()->fetchOne(
    "SELECT ga.id, ga.title, ga.description, ga.batch, ga.access_level, ga.created_at, u.name AS created_by_name
     FROM gallery_albums ga JOIN users u ON u.id = ga.created_by WHERE ga.id = ?",
    [$album_id]
);

if (!$album) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Album not found']);
    exit;
}

// Fetch media
$media = db()->fetchAll(
    "SELECT id, file_path, file_type, original_filename, uploaded_at FROM gallery_media WHERE album_id = ? ORDER BY uploaded_at DESC",
    [$album_id]
);

echo json_encode(['success' => true, 'album' => $album, 'media' => $media]);
?>



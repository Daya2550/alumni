<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$post_id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($post_id <= 0) { header('Location: news.php'); exit; }

$post = db()->fetchOne("SELECT * FROM news_posts WHERE id = ?", [$post_id]);
if (!$post) { header('Location: news.php'); exit; }

$isPrivileged = Auth::hasAnyRole(['staff','admin']);
$isAuthor = ($post['author_id'] === $user['id']);
if (!($isPrivileged || $isAuthor)) { header('HTTP/1.1 403 Forbidden'); exit('Access denied'); }

$categories = [
    'academic' => 'Academic',
    'alumni_achievements' => 'Alumni Achievements',
    'campus_news' => 'Campus News',
    'opportunities' => 'Opportunities'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? $post['category'];
        $status = $post['status'];

        if ($title === '' || $content === '' || !isset($categories[$category])) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                $media = [];
                if (!empty($post['media_urls'])) {
                    $decoded = json_decode($post['media_urls'], true);
                    if (is_array($decoded)) $media = $decoded;
                }

                // Optional: new media
                if (!empty($_FILES['media']['name'][0])) {
                    $uploadDir = __DIR__ . '/../uploads/news/';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    $count = count($_FILES['media']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $name = $_FILES['media']['name'][$i] ?? '';
                        $tmp = $_FILES['media']['tmp_name'][$i] ?? '';
                        $err = $_FILES['media']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                        $size = $_FILES['media']['size'][$i] ?? 0;
                        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
                        if ($size > UPLOAD_MAX_SIZE) throw new Exception('File too large.');
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, ALLOWED_FILE_TYPES, true)) throw new Exception('Unsupported type.');
                        $newName = uniqid('news_', true) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($tmp, $dest)) $media[] = 'uploads/news/' . $newName;
                    }
                }

                // Only staff/admin can change status
                if ($isPrivileged && isset($_POST['status']) && in_array($_POST['status'], ['draft','pending','published'], true)) {
                    $status = $_POST['status'];
                }

                $publishedAt = ($status === 'published') ? ( $post['published_at'] ?: date('Y-m-d H:i:s') ) : null;

                db()->execute(
                    "UPDATE news_posts SET title=?, content=?, category=?, media_urls=?, status=?, published_at=?, updated_at=NOW() WHERE id=?",
                    [
                        sanitizeInput($title),
                        sanitizeInput($content),
                        $category,
                        !empty($media) ? json_encode($media) : null,
                        $status,
                        $publishedAt,
                        $post_id
                    ]
                );
                $message = 'Updated successfully';
                $post = db()->fetchOne("SELECT * FROM news_posts WHERE id = ?", [$post_id]);
            } catch (Exception $e) {
                error_log('Edit news error: ' . $e->getMessage());
                $error = 'Failed to update.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit News - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3"><i class="fas fa-edit"></i> Edit News</h1>
            <a href="news.php" class="btn btn-outline-secondary"><i class="fas fa-newspaper"></i> News</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $post['category'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="10" maxlength="5000" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Add Media</label>
                        <input type="file" name="media[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.mkv,.pdf">
                    </div>
                    <?php if (!empty($post['media_urls'])): ?>
                        <?php $m = json_decode($post['media_urls'], true) ?: []; ?>
                        <?php if (!empty($m)): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Media</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($m as $url): ?>
                                        <a href="../<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($isPrivileged): ?>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['draft'=>'Draft','pending'=>'Pending','published'=>'Published'] as $k=>$v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $post['status'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="text-end">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



<?php
/**
 * Submit News - Anyone logged in can submit news
 * Students/Alumni: status=pending; Staff/Admin: auto-publish
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$message = '';
$error = '';

$categories = [
    'academic' => 'Academic',
    'alumni_achievements' => 'Alumni Achievements',
    'campus_news' => 'Campus News',
    'opportunities' => 'Opportunities'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? '';

        if ($title === '' || $content === '' || !isset($categories[$category])) {
            $error = 'Please fill in all required fields.';
        } elseif (mb_strlen($title) > 255) {
            $error = 'Title is too long (max 255 characters).';
        } else {
            try {
                // Media uploads (optional)
                $mediaUrls = [];
                if (!empty($_FILES['media']['name'][0])) {
                    $uploadDir = __DIR__ . '/../uploads/news/';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

                    $count = count($_FILES['media']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $name = $_FILES['media']['name'][$i] ?? '';
                        $tmp = $_FILES['media']['tmp_name'][$i] ?? '';
                        $err = $_FILES['media']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                        $size = $_FILES['media']['size'][$i] ?? 0;

                        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) { continue; }
                        if ($size > UPLOAD_MAX_SIZE) { throw new Exception('One of the files exceeds the size limit.'); }

                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, ALLOWED_FILE_TYPES, true)) { throw new Exception('Unsupported file type: ' . htmlspecialchars($ext)); }

                        $newName = uniqid('news_', true) . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($tmp, $dest)) {
                            $mediaUrls[] = 'uploads/news/' . $newName;
                        }
                    }
                }

                // Determine status
                $isPrivileged = Auth::hasAnyRole(['staff', 'admin']);
                $status = $isPrivileged ? 'published' : 'pending';
                $publishedAt = $isPrivileged ? date('Y-m-d H:i:s') : null;

                db()->execute(
                    "INSERT INTO news_posts (title, content, category, author_id, media_urls, status, published_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        sanitizeInput($title),
                        sanitizeInput($content),
                        $category,
                        $user['id'],
                        !empty($mediaUrls) ? json_encode($mediaUrls) : null,
                        $status,
                        $publishedAt
                    ]
                );
                $newsId = db()->lastInsertId();

                if ($isPrivileged) {
                    // Notify all active users except author
                    Notifications::notifyAllActiveUsersExcept($user['id'], 'News Published', substr($title, 0, 120), 'info', 'news', $newsId);
                    header('Location: news.php');
                    exit;
                } else {
                    $message = 'Submitted successfully! Your news will be visible once approved by staff.';
                }
            } catch (Exception $e) {
                error_log('Submit news error: ' . $e->getMessage());
                $error = 'Failed to submit news. Please try again.';
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
    <title>Submit News - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3"><i class="fas fa-plus-circle text-primary"></i> Submit News</h1>
                    <a href="news.php" class="btn btn-outline-secondary"><i class="fas fa-newspaper"></i> Back to News</a>
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
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" maxlength="255" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $val => $label): ?>
                                        <option value="<?php echo $val; ?>" <?php echo (($_POST['category'] ?? '') === $val) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Content *</label>
                                <textarea class="form-control" name="content" rows="8" maxlength="5000" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                <div class="form-text">Please keep your submission clear and relevant. Staff may edit for clarity.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-paperclip"></i> Attach media (optional)</label>
                                <input type="file" class="form-control" name="media[]" multiple
                                       accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.mkv,.pdf">
                                <div class="form-text">Max per file: <?php echo round(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB</div>
                            </div>

                            <div class="text-end">
                                <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>










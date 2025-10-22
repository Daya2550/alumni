<?php
/**
 * Single News Post View
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$post_id = intval($_GET['id'] ?? 0);

if ($post_id <= 0) {
    header('Location: news.php');
    exit;
}

// Load the post
$post = db()->fetchOne(
    "SELECT np.*, u.name AS author_name, u.profile_photo AS author_photo
     FROM news_posts np
     JOIN users u ON u.id = np.author_id
     WHERE np.id = ?",
    [$post_id]
);

if (!$post) {
    header('HTTP/1.1 404 Not Found');
    echo 'News post not found';
    exit;
}

// Visibility rules: published for all; authors/staff/admin can view drafts/pending
$is_published = ($post['status'] === 'published' && !empty($post['published_at']));
$is_privileged_viewer = ($post['author_id'] === $user['id']) || Auth::hasAnyRole(['staff','admin']);
if (!$is_published && !$is_privileged_viewer) {
    header('HTTP/1.1 403 Forbidden');
    echo 'This article is not available.';
    exit;
}

// Media
$media = [];
if (!empty($post['media_urls'])) {
    $decoded = json_decode($post['media_urls'], true);
    if (is_array($decoded)) { $media = $decoded; }
}

// Categories map
$categories = [
    'academic' => 'Academic',
    'alumni_achievements' => 'Alumni Achievements',
    'campus_news' => 'Campus News',
    'opportunities' => 'Opportunities'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <div class="row">
            <div class="col-lg-9 mx-auto">
                <article class="card shadow-sm">
                    <?php if (!empty($media) && !empty($media[0])): ?>
                        <img src="<?php echo htmlspecialchars($media[0]); ?>" class="card-img-top" style="max-height: 480px; object-fit: cover;">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary"><?php echo $categories[$post['category']] ?? 'News'; ?></span>
                                <?php if (!$is_published): ?>
                                    <span class="badge bg-warning text-dark ms-2 text-uppercase"><?php echo htmlspecialchars($post['status']); ?></span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i>
                                <?php echo $is_published ? date('M j, Y', strtotime($post['published_at'])) : date('M j, Y', strtotime($post['created_at'])); ?>
                            </small>
                        </div>

                        <h1 class="h3 mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>

                        <div class="d-flex align-items-center mb-3">
                            <?php if (!empty($post['author_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($post['author_photo']); ?>" class="rounded-circle me-2" width="36" height="36">
                            <?php else: ?>
                                <i class="fas fa-user-circle me-2 fa-lg text-muted"></i>
                            <?php endif; ?>
                            <small class="text-muted">By <?php echo htmlspecialchars($post['author_name']); ?></small>
                        </div>

                        <div class="news-content" style="white-space: pre-wrap; line-height: 1.7; font-size: 1.05rem;">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>

                        <?php if (count($media) > 1): ?>
                            <div class="mt-4">
                                <h5><i class="fas fa-images"></i> Gallery</h5>
                                <div class="row g-3">
                                    <?php foreach (array_slice($media, 1) as $url): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <img src="<?php echo htmlspecialchars($url); ?>" class="img-fluid rounded" style="height: 180px; object-fit: cover;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="news.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to News</a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>











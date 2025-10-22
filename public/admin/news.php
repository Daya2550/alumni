<?php
/**
 * Admin News Management - Review and moderate submissions
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

$message = '';
$error = '';

// Actions: approve (publish), reject (set status draft), delete
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    db()->execute("UPDATE news_posts SET status='published', published_at = NOW() WHERE id = ?", [$id]);
    header('Location: news.php');
    exit;
}

if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    db()->execute("UPDATE news_posts SET status='draft', published_at = NULL WHERE id = ?", [$id]);
    header('Location: news.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    db()->execute("DELETE FROM news_posts WHERE id = ?", [$id]);
    header('Location: news.php');
    exit;
}

// Load pending and recent news
$pending = db()->fetchAll(
    "SELECT np.*, u.name AS author_name FROM news_posts np JOIN users u ON u.id = np.author_id WHERE np.status = 'pending' ORDER BY np.created_at DESC"
);
$recent = db()->fetchAll(
    "SELECT np.*, u.name AS author_name FROM news_posts np JOIN users u ON u.id = np.author_id ORDER BY np.created_at DESC LIMIT 20"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3"><i class="fas fa-newspaper"></i> Manage News</h1>
            <a href="../news.php" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View News</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Pending Submissions</strong></div>
                    <div class="card-body">
                        <?php if (empty($pending)): ?>
                            <p class="text-muted m-0">No pending submissions.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($pending as $item): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <small class="text-muted">By <?php echo htmlspecialchars($item['author_name']); ?> • <?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="?approve=<?php echo $item['id']; ?>" class="btn btn-success"><i class="fas fa-check"></i></a>
                                                <a href="?reject=<?php echo $item['id']; ?>" class="btn btn-warning"><i class="fas fa-ban"></i></a>
                                                <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-danger"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><strong>Recent News</strong></div>
                    <div class="card-body">
                        <?php if (empty($recent)): ?>
                            <p class="text-muted m-0">No news yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent as $item): ?>
                                    <a class="list-group-item list-group-item-action" href="../news_post.php?id=<?php echo $item['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['title']); ?></div>
                                                <small class="text-muted text-uppercase"><?php echo htmlspecialchars($item['status']); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>



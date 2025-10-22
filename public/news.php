<?php
/**
 * News Corner - List all news posts
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$mine = isset($_GET['mine']) && $user ? true : false;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');

// Build query
$where_conditions = ["np.status = 'published'", "np.published_at IS NOT NULL"];
$params = [];

// Category filter
if ($category) {
    $where_conditions[] = 'np.category = ?';
    $params[] = $category;
}

// Search functionality
if ($search) {
    $where_conditions[] = '(np.title LIKE ? OR np.content LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Optional ?mine=1 filter
if ($mine) {
    $where_conditions[] = 'np.author_id = ?';
    $params[] = $user['id'];
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get news posts
$news_query = "
    SELECT 
        np.*, 
        u.name AS author_name, 
        u.profile_photo AS author_photo,
        (SELECT COUNT(*) FROM news_likes nl WHERE nl.post_id = np.id) AS likes_count,
        EXISTS(
            SELECT 1 FROM news_likes ul 
            WHERE ul.post_id = np.id AND ul.user_id = ?
        ) AS user_liked
    FROM news_posts np 
    JOIN users u ON np.author_id = u.id 
    $where_clause
    ORDER BY np.published_at DESC 
    LIMIT ? OFFSET ?
";

$params = array_merge([$user['id']], $params, [$limit, $offset]);
$news_posts = db()->fetchAll($news_query, $params);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM news_posts np 
    $where_clause
";
$count_params = array_slice($params, 1, -2); // Remove user_id, limit, offset
$total_posts = db()->fetchOne($count_query, $count_params)['total'];
$total_pages = ceil($total_posts / $limit);

// Get categories for filter
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
    <title>News Corner - <?php echo APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo Auth::generateCSRFToken(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-newspaper text-info"></i> News Corner</h1>
                    <div class="d-flex gap-2">
                        <?php if (Auth::hasAnyRole(['student','alumni','staff','admin'])): ?>
                            <a href="submit_news.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Submit News
                            </a>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
                            <a href="admin/news.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Review Submissions
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search News</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search news posts...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat_value => $cat_label): ?>
                                        <option value="<?php echo $cat_value; ?>" 
                                                <?php echo $category === $cat_value ? 'selected' : ''; ?>>
                                            <?php echo $cat_label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- News Posts -->
                <?php if (empty($news_posts)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-newspaper text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-muted">No news posts found</h3>
                            <p class="text-muted">There are no news posts matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($news_posts as $post): ?>
                            <div class="col-lg-6 mb-4">
                                <article class="card h-100 shadow-sm">
                                    <?php if ($post['media_urls']): ?>
                                        <?php $media = json_decode($post['media_urls'], true); ?>
                                        <?php if (!empty($media[0])): ?>
                                            <a href="news_post.php?id=<?php echo $post['id']; ?>">
                                                <img src="<?php echo htmlspecialchars($media[0]); ?>" 
                                                     class="card-img-top" style="height: 260px; object-fit: cover;">
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-primary"><?php echo $categories[$post['category']]; ?></span>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo date('M j, Y', strtotime($post['published_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <h5 class="card-title fw-bold">
                                            <a href="news_post.php?id=<?php echo $post['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($post['title']); ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="card-text">
                                            <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 150)); ?>
                                            <?php if (strlen(strip_tags($post['content'])) > 150): ?>...<?php endif; ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <?php if ($post['author_photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($post['author_photo']); ?>" 
                                                         class="rounded-circle me-2" width="32" height="32">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle me-2 fa-lg"></i>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    By <?php echo htmlspecialchars($post['author_name']); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex align-items-center">
                                                <button class="btn btn-sm btn-outline-primary me-2 like-btn" 
                                                        data-post-id="<?php echo $post['id']; ?>">
                                                    <i class="fas fa-heart <?php echo $post['user_liked'] ? 'text-danger' : ''; ?>"></i>
                                                    <span class="like-count"><?php echo $post['likes_count']; ?></span>
                                                </button>
                                                
                                                <button class="btn btn-sm btn-outline-secondary share-btn" 
                                                        data-post-id="<?php echo $post['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($post['title']); ?>">
                                                    <i class="fas fa-share"></i> Share
                                                </button>
                                                <?php if (Auth::hasAnyRole(['staff','admin']) || $post['author_id'] === $user['id']): ?>
                                                    <a href="edit_news.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-secondary ms-2">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 delete-news-btn" data-post-id="<?php echo $post['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="News pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&mine=<?php echo $mine ? 1 : 0; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&mine=<?php echo $mine ? 1 : 0; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&mine=<?php echo $mine ? 1 : 0; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Like functionality
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const icon = this.querySelector('i');
                const count = this.querySelector('.like-count');
                
                fetch('api/news_like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.liked) {
                            icon.classList.add('text-danger');
                        } else {
                            icon.classList.remove('text-danger');
                        }
                        count.textContent = data.likes_count;
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
        
        // Share functionality
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const title = this.dataset.title;
                const url = window.location.origin + '/news_post.php?id=' + this.dataset.postId;
                
                if (navigator.share) {
                    navigator.share({
                        title: title,
                        url: url
                    });
                } else {
                    // Fallback - copy to clipboard
                    navigator.clipboard.writeText(url).then(() => {
                        alert('Link copied to clipboard!');
                    });
                }
            });
        });

        // Delete news (author or staff/admin)
        document.querySelectorAll('.delete-news-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                if (!confirm('Delete this news post? This cannot be undone.')) return;
                fetch('api/news_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        post_id: postId,
                        csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete');
                    }
                }).catch(() => alert('Network error'));
            });
        });
    </script>
</body>
</html>

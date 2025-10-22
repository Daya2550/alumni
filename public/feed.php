<?php
/**
 * Social Feed - Students can post, staff/admin can delete
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Handle new post (supports media upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if ($content !== '' && strlen($content) <= 1000) {
        $mediaUrls = [];

        if (!empty($_FILES['media']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/feed/';
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
                            $mediaUrls[] = $publicPath;
                        }
                    }
                }
            }
        }

        db()->execute(
            "INSERT INTO feed_posts (author_id, content, media_urls, visibility) VALUES (?, ?, ?, 'all')",
            [$user['id'], sanitizeInput($content), json_encode($mediaUrls)]
        );
        $postId = db()->lastInsertId();
        // Broadcast notification to all active users except author
        $titleNote = 'New Feed Post';
        $msgNote = substr($content !== '' ? $content : 'New post', 0, 120);
        Notifications::notifyAllActiveUsersExcept($user['id'], $titleNote, $msgNote, 'info', 'feed_post', $postId);
        header('Location: feed.php');
        exit;
    }
}

// Handle deletion by staff/admin or post owner
if (isset($_GET['delete'])) {
    $post_id = intval($_GET['delete']);
    $post = db()->fetchOne("SELECT author_id FROM feed_posts WHERE id = ?", [$post_id]);
    if ($post && ($post['author_id'] === $user['id'] || Auth::hasAnyRole(['staff','admin']))) {
        db()->execute("DELETE FROM feed_posts WHERE id = ?", [$post_id]);
        header('Location: feed.php');
        exit;
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Load posts
$posts = db()->fetchAll(
    "SELECT fp.*, u.name AS author_name, u.profile_photo AS author_photo,
            (SELECT COUNT(*) FROM feed_likes fl WHERE fl.post_id = fp.id) AS likes_count,
            (SELECT COUNT(*) FROM feed_comments fc WHERE fc.post_id = fp.id) AS comments_count,
            EXISTS(SELECT 1 FROM feed_likes ul WHERE ul.post_id = fp.id AND ul.user_id = ?) AS user_liked
     FROM feed_posts fp
     JOIN users u ON fp.author_id = u.id
     ORDER BY fp.created_at DESC
     LIMIT ? OFFSET ?",
    [$user['id'], $limit, $offset]
);

// Count
$total_posts = db()->fetchOne("SELECT COUNT(*) AS total FROM feed_posts")['total'];
$total_pages = ceil($total_posts / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo Auth::generateCSRFToken(); ?>">
    <style>
        * { box-sizing: border-box; }
        body { background: #f0f2f5; color: #1c1e21; height: 100vh; overflow: hidden; }
        .app-topbar { height: 56px; background: #008069; color: #ffffff; display: flex; align-items: center; gap: 10px; padding: 0 12px; }
        .app-topbar .btn-top { background: rgba(255,255,255,0.15); color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; }
        .app-topbar .btn-top:hover { background: rgba(255,255,255,0.25); }
        .feed-ui { height: calc(100vh - 56px); overflow-y: auto; }
        .feed-ui .container-narrow { max-width: 680px; margin: 0 auto; padding: 20px; }
        .feed-ui .create-post { background: #ffffff; border-radius: 8px; padding: 16px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .feed-ui .create-post-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .feed-ui .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 600; }
        .feed-ui .post-input { flex: 1; padding: 12px 16px; border: 1px solid #ddd; border-radius: 24px; outline: none; font-size: 15px; cursor: pointer; background: #f0f2f5; }
        .feed-ui .post-input:focus { background: #ffffff; border-color: #1877f2; }
        .feed-ui .create-post-actions { display: flex; gap: 8px; padding-top: 12px; border-top: 1px solid #e4e6eb; margin-top: 12px; }
        @media (max-width: 480px) {
            .feed-ui .create-post-actions { gap: 4px; }
            .feed-ui .action-btn { font-size: 14px; padding: 6px; }
            .feed-ui .action-btn i { font-size: 18px; }
        }
        .feed-ui .action-btn { flex: 1; padding: 8px; border: none; background: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 15px; font-weight: 500; color: #65676b; transition: background 0.2s; }
        .feed-ui .action-btn:hover { background: #f0f2f5; }
        .feed-ui .action-btn i { font-size: 20px; }
        .feed-ui .action-btn.photo i { color: #45bd62; }
        .feed-ui .action-btn.video i { color: #f3425f; }
        .feed-ui .action-btn.feeling i { color: #f7b928; }
        .feed-ui .post-card { background: #ffffff; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); overflow: hidden; }
        .feed-ui .post-header { padding: 16px; display: flex; align-items: center; justify-content: space-between; }
        .feed-ui .post-author { display: flex; align-items: center; gap: 12px; }
        .feed-ui .author-info h3 { font-size: 15px; font-weight: 600; margin-bottom: 2px; }
        .feed-ui .post-time { font-size: 13px; color: #65676b; }
        .feed-ui .post-menu { background: none; border: none; cursor: pointer; font-size: 20px; color: #65676b; padding: 8px; border-radius: 50%; text-decoration: none; }
        .feed-ui .post-menu:hover { background: #f0f2f5; }
        .feed-ui .dropdown-menu { min-width: 160px; }
        .feed-ui .post-content { padding: 0 16px 16px; font-size: 15px; line-height: 1.5; }
        .feed-ui .post-image { width: 100%; display: block; background: #f0f2f5; }
        .feed-ui .post-stats { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 15px; color: #65676b; border-bottom: 1px solid #e4e6eb; }
        .feed-ui .likes { display: flex; align-items: center; gap: 6px; cursor: pointer; }
        .feed-ui .like-icon { width: 18px; height: 18px; border-radius: 50%; background: #1877f2; display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 10px; }
        .feed-ui .post-actions { display: flex; padding: 8px 16px; gap: 4px; }
        .feed-ui .post-action-btn { flex: 1; padding: 8px; border: none; background: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 15px; font-weight: 500; color: #65676b; transition: background 0.2s; }
        .feed-ui .post-action-btn:hover { background: #f0f2f5; }
        .feed-ui .post-action-btn.active { color: #1877f2; }
        .feed-ui .comments-section { padding: 16px; background: #f7f8fa; }
        .feed-ui .comment { display: flex; gap: 12px; margin-bottom: 12px; }
        .feed-ui .comment-content { flex: 1; background: #e4e6eb; padding: 12px; border-radius: 18px; }
        .feed-ui .comment-author { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .feed-ui .comment-text { font-size: 15px; line-height: 1.4; }
        .feed-ui .comment-actions { display: flex; gap: 12px; padding-left: 52px; font-size: 13px; color: #65676b; font-weight: 500; margin-top: 4px; }
        .feed-ui .comment-actions span { cursor: pointer; }
        .feed-ui .comment-actions span:hover { text-decoration: underline; }
        .feed-ui .comment-input-wrapper { display: flex; gap: 12px; margin-top: 12px; }
        .feed-ui .comment-input { flex: 1; padding: 10px 16px; border: 1px solid #ddd; border-radius: 18px; outline: none; font-size: 15px; background: #ffffff; }
        .feed-ui .comment-input:focus { border-color: #1877f2; }
        .feed-ui .feed-media { padding: 0 16px 16px; }
        .feed-ui .feed-media-strip { display: flex; gap: 8px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: thin; }
        .feed-ui .feed-media-img { max-width: 100%; border-radius: 8px; }
        .feed-ui .feed-media-video { width: 100%; max-height: 420px; border-radius: 8px; }
        .edit-media-strip { display: flex; gap: 8px; flex-wrap: wrap; }
        .edit-media-item { position: relative; }
        .edit-media-item img, .edit-media-item video { max-width: 120px; max-height: 120px; border-radius: 8px; display: block; }
        .edit-media-remove { position: absolute; top: -6px; right: -6px; background: #dc3545; color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; }
        .fab-post { position: fixed; right: 20px; bottom: 20px; width: 56px; height: 56px; border-radius: 50%; background: #1877f2; color: #fff; border: none; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 6px 16px rgba(0,0,0,0.2); cursor: pointer; z-index: 1050; }
        .fab-post:hover { background: #166fe5; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

    <main class="feed-ui">
        <div class="container-narrow">
            <!-- Create Post (hidden; opened via modal) -->
            <div class="create-post" style="display:none"></div>

            <!-- Feed Posts -->
            <?php foreach ($posts as $post): ?>
                <div class="post-card" id="post-<?php echo $post['id']; ?>">
                    <div class="post-header">
                        <div class="post-author">
                            <?php if ($post['author_photo']): ?>
                                <img src="<?php echo htmlspecialchars($post['author_photo']); ?>" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="avatar"><?php echo strtoupper(substr(trim($post['author_name'] ?? 'U'),0,1)); ?></div>
                            <?php endif; ?>
                            <div class="author-info">
                                <h3><?php echo htmlspecialchars($post['author_name']); ?></h3>
                                <div class="post-time"><?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php if ($post['author_id'] === $user['id'] || Auth::hasAnyRole(['staff','admin'])): ?>
                            <div class="dropdown">
                                <button class="post-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="editPost(<?php echo (int)$post['id']; ?>); return false;"><i class="fas fa-pen me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item text-danger" href="?delete=<?php echo $post['id']; ?>" onclick="return confirmDeletePost();"><i class="fas fa-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($post['content'])): ?>
                    <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($post['media_urls'])): ?>
                        <?php $media = json_decode($post['media_urls'], true) ?: []; ?>
                        <?php if (!empty($media)): ?>
                            <div class="feed-media">
                                <div class="feed-media-strip">
                                    <?php foreach ($media as $url): ?>
                                        <?php $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION)); ?>
                                        <?php if (in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                            <img src="<?php echo htmlspecialchars($url); ?>" class="feed-media-img" alt="Post media image">
                                        <?php elseif (in_array($ext, ['mp4','mov','avi','mkv'])): ?>
                                            <video src="<?php echo htmlspecialchars($url); ?>" controls class="feed-media-video"></video>
                                        <?php elseif ($ext === 'pdf'): ?>
                                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-pdf"></i> View PDF</a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="post-stats">
                        <div class="likes">
                            <div class="like-icon">👍</div>
                            <span class="like-count"><?php echo $post['likes_count']; ?></span>
                        </div>
                        <div><span class="comment-count"><?php echo $post['comments_count']; ?></span> comments</div>
                    </div>
                    <div class="post-actions">
                        <button class="post-action-btn like-btn" data-post-id="<?php echo $post['id']; ?>">
                            <i class="fa-<?php echo $post['user_liked'] ? 'solid' : 'regular'; ?> fa-thumbs-up"></i> Like
                        </button>
                        <button class="post-action-btn comments-toggle" data-post-id="<?php echo $post['id']; ?>">
                            <i class="far fa-comment"></i> Comment
                        </button>
                        <button class="post-action-btn share-btn" data-post-id="<?php echo $post['id']; ?>">
                            <i class="far fa-share-square"></i> Share
                        </button>
                    </div>
                    <div class="comments-section d-none" id="comments-<?php echo $post['id']; ?>">
                        <div class="comments-list small mb-2"></div>
                        <div class="comment-input-wrapper">
                            <div class="avatar"><?php echo strtoupper(substr(trim($user['name'] ?? 'U'),0,1)); ?></div>
                            <input type="text" class="comment-input" placeholder="Write a comment..." maxlength="500">
                            <button class="btn btn-primary btn-sm comment-submit" type="button">Post</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Feed pagination">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
        </div>
    </main>

    <!-- Floating create post button -->
    <button class="fab-post" type="button" onclick="openFeedModal()" data-bs-toggle="tooltip" data-bs-placement="left" title="New Post">+</button>

    <!-- Create Post Modal -->
    <div class="modal" id="postModal" tabindex="-1" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create post</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="closeFeedModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="feedForm" method="POST" enctype="multipart/form-data">
                        <div class="mb-3 d-flex align-items-center gap-2">
                            <div class="avatar"><?php echo strtoupper(substr(trim($user['name'] ?? 'U'),0,1)); ?></div>
                            <div class="text-muted small">Posting as <?php echo htmlspecialchars($user['name'] ?? 'You'); ?></div>
                        </div>
                        <textarea id="feedContent" name="content" rows="4" maxlength="1000" placeholder="What's on your mind?" class="form-control mb-2"></textarea>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-light" onclick="document.getElementById('feedMedia').click()" title="Add photo/video"><i class="fas fa-image"></i></button>
                            <button type="button" class="btn btn-light" title="Feeling"><i class="fas fa-smile"></i></button>
                        </div>
                        <input type="file" id="feedMedia" name="media[]" multiple accept="image/*,video/*,.pdf">
                        <div class="text-end mt-3">
                            <button class="btn btn-primary" type="submit">Post</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <div class="modal" id="editPostModal" tabindex="-1" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit post</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="closeEditModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="editPostForm">
                        <input type="hidden" name="post_id" id="editPostId">
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea id="editContent" name="content" rows="4" maxlength="1000" class="form-control" placeholder="Update your post..."></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Media</label>
                            <div id="editMediaStrip" class="edit-media-strip"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" id="editMediaInput" name="media[]" multiple accept="image/*,video/*,.pdf" class="form-control">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditPost()">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let feedModal;
        function openFeedModal(){
            const el = document.getElementById('postModal');
            if (!feedModal) feedModal = new bootstrap.Modal(el);
            feedModal.show();
            document.getElementById('feedContent')?.focus();
        }
        function closeFeedModal(){ feedModal && feedModal.hide(); }
        // Enable tooltip for + button
        document.addEventListener('DOMContentLoaded', function(){
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        function feedBack(){
            if (document.referrer && document.referrer !== location.href) { history.back(); } else { location.href = 'index.php'; }
        }
        function confirmDeletePost(){
            return confirm('Delete this post?');
        }
        let editModal;
        let removeMediaSet = new Set();
        function openEditModal(){
            const el = document.getElementById('editPostModal');
            if (!editModal) editModal = new bootstrap.Modal(el);
            editModal.show();
        }
        function closeEditModal(){ editModal && editModal.hide(); removeMediaSet = new Set(); document.getElementById('editPostForm').reset(); document.getElementById('editMediaStrip').innerHTML = ''; }
        function editPost(postId){
            const postCard = document.getElementById('post-' + postId);
            if (!postCard) return;
            removeMediaSet = new Set();
            document.getElementById('editPostId').value = String(postId);
            const contentEl = postCard.querySelector('.post-content');
            document.getElementById('editContent').value = contentEl ? contentEl.innerText : '';
            const mediaStrip = document.getElementById('editMediaStrip');
            mediaStrip.innerHTML = '';
            const mediaContainer = postCard.querySelector('.feed-media-strip');
            if (mediaContainer) {
                mediaContainer.querySelectorAll('img, video, a.btn').forEach(el => {
                    let url = '';
                    if (el.tagName === 'IMG' || el.tagName === 'VIDEO') { url = el.getAttribute('src'); }
                    else if (el.tagName === 'A') { url = el.getAttribute('href'); }
                    if (!url) return;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'edit-media-item';
                    if (el.tagName === 'IMG') {
                        const img = document.createElement('img'); img.src = url; wrapper.appendChild(img);
                    } else if (el.tagName === 'VIDEO') {
                        const vid = document.createElement('video'); vid.src = url; vid.controls = true; wrapper.appendChild(vid);
                    } else {
                        const link = document.createElement('a'); link.href = url; link.target = '_blank'; link.textContent = 'PDF'; wrapper.appendChild(link);
                    }
                    const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'edit-media-remove'; rm.title = 'Remove'; rm.innerHTML = '&times;';
                    rm.addEventListener('click', function(){ removeMediaSet.add(url); wrapper.remove(); });
                    wrapper.appendChild(rm);
                    mediaStrip.appendChild(wrapper);
                });
            }
            openEditModal();
        }
        function submitEditPost(){
            const form = document.getElementById('editPostForm');
            const postId = document.getElementById('editPostId').value;
            const fd = new FormData();
            fd.append('post_id', postId);
            fd.append('content', document.getElementById('editContent').value);
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            if (removeMediaSet.size) { fd.append('remove_media', JSON.stringify(Array.from(removeMediaSet))); }
            const files = document.getElementById('editMediaInput').files;
            for (let i = 0; i < files.length; i++) { fd.append('media[]', files[i]); }
            fetch('api/feed_edit.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (!d.success) { alert(d.message || 'Failed to edit post'); return; }
                    // Update UI
                    const postCard = document.getElementById('post-' + postId);
                    if (postCard) {
                        const contentEl = postCard.querySelector('.post-content');
                        if (contentEl) contentEl.textContent = document.getElementById('editContent').value;
                        if (Array.isArray(d.media_urls)) {
                            let mediaWrap = postCard.querySelector('.feed-media');
                            if (!mediaWrap) {
                                mediaWrap = document.createElement('div');
                                mediaWrap.className = 'feed-media';
                                const insertAfter = postCard.querySelector('.post-content') || postCard.querySelector('.post-stats');
                                const strip = document.createElement('div'); strip.className = 'feed-media-strip';
                                mediaWrap.appendChild(strip);
                                if (insertAfter) insertAfter.parentNode.insertBefore(mediaWrap, insertAfter.nextSibling);
                            }
                            const strip = postCard.querySelector('.feed-media-strip');
                            strip.innerHTML = '';
                            d.media_urls.forEach(url => {
                                const ext = (url.split('.').pop() || '').toLowerCase();
                                if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                                    const img = document.createElement('img'); img.src = url; img.className = 'feed-media-img'; strip.appendChild(img);
                                } else if (['mp4','mov','avi','mkv'].includes(ext)) {
                                    const vid = document.createElement('video'); vid.src = url; vid.className = 'feed-media-video'; vid.controls = true; strip.appendChild(vid);
                                } else if (ext === 'pdf') {
                                    const a = document.createElement('a'); a.href = url; a.target = '_blank'; a.className = 'btn btn-sm btn-outline-secondary'; a.innerHTML = '<i class="fas fa-file-pdf"></i> View PDF'; strip.appendChild(a);
                                }
                            });
                            applyFeedMediaAlignment();
                        }
                    }
                    closeEditModal();
                })
                .catch(() => alert('Network error'));
        }

        function savePost(postId){
            try {
                const saved = JSON.parse(localStorage.getItem('saved_posts') || '[]');
                if (!saved.includes(postId)) saved.push(postId);
                localStorage.setItem('saved_posts', JSON.stringify(saved));
                alert('Post saved');
            } catch(e) { alert('Could not save post'); }
        }

        // Like toggle with count update
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                const icon = this.querySelector('i');
                const count = (this.closest('.post-card') || document).querySelector('.like-count');
                fetch('api/feed_like.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        post_id: postId,
                        csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    })
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        if (data.liked) { icon.classList.add('text-primary'); icon.classList.replace('fa-regular','fa-solid'); }
                        else { icon.classList.remove('text-primary'); icon.classList.replace('fa-solid','fa-regular'); }
                        if (count) count.textContent = data.likes_count;
                    }
                });
            });
        });

        // Share handler
        document.querySelectorAll('.share-btn').forEach(btn => {
            btn.addEventListener('click', function(){
                const postCard = this.closest('.post-card');
                const content = postCard ? (postCard.querySelector('.post-content') ? postCard.querySelector('.post-content').innerText : '') : '';
                const postId = postCard ? postCard.id.replace('post-','') : '';
                // Prefer direct media URL (image/video/pdf) if available
                let url = '';
                if (postCard) {
                    const img = postCard.querySelector('.feed-media-img');
                    const vid = postCard.querySelector('.feed-media-video');
                    const pdf = postCard.querySelector('.feed-media a[href$=".pdf"], .feed-media a[href*=".pdf"]');
                    if (img && img.src) url = img.src;
                    else if (vid && vid.src) url = vid.src;
                    else if (pdf && pdf.href) url = pdf.href;
                }
                if (!url) {
                    url = location.origin + location.pathname + '#post-' + postId;
                }
                if (navigator.share) {
                    navigator.share({ title: document.title, text: content, url: url }).catch(()=>{});
                } else {
                    const shareText = (content ? content + '\n' : '') + url;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareText).then(() => alert('Link copied to clipboard')); 
                    } else {
                        prompt('Copy this link:', shareText);
                    }
                }
            });
        });

        // Fallback centering for single media item (in case :has() unsupported)
        function applyFeedMediaAlignment() {
            document.querySelectorAll('.feed-media-strip').forEach(strip => {
                const visibleChildren = Array.from(strip.children).filter(el => el.tagName);
                if (visibleChildren.length === 1) { strip.style.justifyContent = 'center'; }
                else { strip.style.justifyContent = 'flex-start'; }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyFeedMediaAlignment);
        } else {
            applyFeedMediaAlignment();
        }
        window.addEventListener('resize', applyFeedMediaAlignment);

        // Comments: toggle, load, submit
        function attachCommentsHandlers(scope) {
            (scope || document).querySelectorAll('.comments-toggle').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.dataset.postId;
                    const section = document.getElementById(`comments-${postId}`);
                    if (!section) return;
                    section.classList.toggle('d-none');
                    if (!section.classList.contains('d-none')) {
                        loadComments(postId, section);
                    }
                });
            });

            (scope || document).querySelectorAll('.comment-submit').forEach(btn => {
                btn.addEventListener('click', function() {
                    const section = this.closest('.comments-section');
                    if (!section) return;
                    const postId = section.id.replace('comments-', '');
                    const input = section.querySelector('.comment-input');
                    const content = (input && input.value || '').trim();
                    if (!content) return;
                    btn.disabled = true;
                    fetch('api/feed_comment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            post_id: postId,
                            content: content,
                            csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            input.value = '';
                            const countEl = (section.previousElementSibling || document).querySelector && section.previousElementSibling.querySelector('.comment-count');
                            if (countEl && typeof data.comments_count !== 'undefined') {
                                countEl.textContent = data.comments_count;
                            }
                            loadComments(postId, section);
                        }
                    }).finally(() => { btn.disabled = false; });
                });
            });
        }

        function loadComments(postId, section) {
            const list = section.querySelector('.comments-list');
            if (!list) return;
            list.textContent = 'Loading...';
            fetch(`api/feed_comments_list.php?post_id=${encodeURIComponent(postId)}&limit=20`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { list.textContent = 'Failed to load comments'; return; }
                    if (!Array.isArray(data.comments) || data.comments.length === 0) { list.textContent = 'No comments yet.'; return; }
                    list.innerHTML = '';
                    data.comments.forEach(c => {
                        const row = document.createElement('div');
                        row.className = 'd-flex align-items-start mb-2';
                        row.innerHTML = `
                            <div class="me-2">
                                ${c.author_photo ? `<img src="${c.author_photo}" class="rounded-circle" width="28" height="28">` : `<i class=\"fas fa-user-circle text-muted\"></i>`}
                            </div>
                            <div>
                                <div class="fw-semibold">${escapeHtml(c.author_name || 'User')} <small class="text-muted">${formatDateTime(c.created_at)}</small></div>
                                <div>${escapeHtml(c.content || '')}</div>
                            </div>`;
                        list.appendChild(row);
                    });
                });
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"]+/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]));
        }

        function formatDateTime(iso) {
            try { return new Date(iso).toLocaleString(); } catch(e) { return ''; }
        }

        attachCommentsHandlers();
    </script>
</body>
</html>




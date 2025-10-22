<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$album_id = intval($_GET['id'] ?? 0);
if ($album_id <= 0) { header('Location: gallery.php'); exit; }

// Load album
$album = db()->fetchOne(
	"SELECT ga.*, u.name as created_by_name FROM gallery_albums ga JOIN users u ON ga.created_by = u.id WHERE ga.id = ?",
	[$album_id]
);
if (!$album) {
	header('HTTP/1.1 404 Not Found');
	echo 'Album not found';
	exit;
}

// Access control
$canView = false;
if ($album['access_level'] === 'public') {
	$canView = true;
} else {
	if (Auth::hasAnyRole(['staff','admin'])) { $canView = true; }
	if ($album['created_by'] === ($user['id'] ?? '')) { $canView = true; }
	// Batch-only: same batch users can view
	if ($album['access_level'] === 'batch_only' && !empty($album['batch']) && ($user['batch'] ?? '') === $album['batch']) { $canView = true; }
}
if (!$canView) {
	header('HTTP/1.1 403 Forbidden');
	echo 'You do not have access to this album.';
	exit;
}

// Load media
$media = db()->fetchAll(
	"SELECT * FROM gallery_media WHERE album_id = ? ORDER BY uploaded_at DESC",
	[$album_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($album['title']); ?> - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
	<?php include 'includes/header.php'; ?>

	<main class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<div>
				<h1 class="h3 mb-1"><i class="fas fa-folder-open text-info"></i> <?php echo htmlspecialchars($album['title']); ?></h1>
				<div class="text-muted">
					By <?php echo htmlspecialchars($album['created_by_name']); ?>
					<?php if ($album['batch']): ?>
						<span class="mx-2">•</span> Batch: <?php echo htmlspecialchars($album['batch']); ?>
					<?php endif; ?>
					<span class="mx-2">•</span> <?php echo strtoupper($album['access_level']); ?>
				</div>
			</div>
    <div>
        <a href="gallery.php" class="btn btn-outline-secondary"><i class="fas fa-images"></i> Gallery</a>
        <?php if (Auth::hasAnyRole(['staff','admin']) && $album['access_level'] !== 'public'): ?>
            <a href="admin/gallery.php?approve=<?php echo $album_id; ?>" class="btn btn-success ms-1"><i class="fas fa-check"></i> Approve</a>
        <?php endif; ?>
        <?php if (Auth::hasAnyRole(['staff','admin']) || $album['created_by'] === ($user['id'] ?? '')): ?>
            <a class="btn btn-outline-secondary ms-1" href="edit_album.php?id=<?php echo $album_id; ?>"><i class="fas fa-edit"></i> Edit</a>
            <button class="btn btn-outline-danger ms-1" onclick="deleteAlbum(<?php echo $album_id; ?>)"><i class="fas fa-trash"></i> Delete</button>
        <?php endif; ?>
    </div>
		</div>

		<?php if (!empty($album['description'])): ?>
			<div class="card mb-3"><div class="card-body"><?php echo nl2br(htmlspecialchars($album['description'])); ?></div></div>
		<?php endif; ?>

		<?php if (empty($media)): ?>
			<div class="alert alert-info"><i class="fas fa-info-circle"></i> No media in this album yet.</div>
		<?php else: ?>
			<div class="row g-3">
				<?php foreach ($media as $m): ?>
					<div class="col-6 col-md-4 col-lg-3">
						<div class="card h-100">
							<?php if ($m['file_type'] === 'image'): ?>
								<a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank">
									<img src="<?php echo htmlspecialchars($m['file_path']); ?>" class="card-img-top" alt="">
								</a>
							<?php else: ?>
								<video class="card-img-top" controls src="<?php echo htmlspecialchars($m['file_path']); ?>"></video>
							<?php endif; ?>
							<div class="card-body p-2">
								<small class="text-muted">Uploaded: <?php echo date('Y-m-d H:i', strtotime($m['uploaded_at'])); ?></small>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</main>

	<?php include 'includes/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if (Auth::hasAnyRole(['staff','admin']) || $album['created_by'] === ($user['id'] ?? '')): ?>
    <script>
        async function deleteAlbum(albumId) {
            if (!confirm('Delete this album and all its media?')) return;
            try {
                const res = await fetch('api/album_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ album_id: albumId, csrf_token: '<?php echo Auth::generateCSRFToken(); ?>' })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'gallery.php';
                } else {
                    alert(data.message || 'Failed to delete album');
                }
            } catch (e) {
                alert('Error deleting album');
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>



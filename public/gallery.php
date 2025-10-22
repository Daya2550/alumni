<?php
/**
 * Gallery - Browse photos and videos
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$mine = isset($_GET['mine']) && $user ? true : false;

// Fetch albums with a computed cover media (first image/video) to avoid listing all images
if ($mine) {
	$albums = db()->fetchAll(
		"SELECT a.id, a.title, a.access_level, a.created_at,
			(SELECT gm.file_path FROM gallery_media gm 
			 WHERE gm.album_id = a.id AND gm.file_type IN ('image','video') 
			 ORDER BY gm.id ASC LIMIT 1) AS cover_path
		 FROM gallery_albums a
		 WHERE a.created_by = ?
		 ORDER BY a.created_at DESC",
		[$user['id']]
	);
} else {
	$albums = db()->fetchAll(
		"SELECT a.id, a.title, a.access_level, a.created_at,
			(SELECT gm.file_path FROM gallery_media gm 
			 WHERE gm.album_id = a.id AND gm.file_type IN ('image','video') 
			 ORDER BY gm.id ASC LIMIT 1) AS cover_path
		 FROM gallery_albums a
		 WHERE a.access_level = 'public'
		 ORDER BY a.created_at DESC"
	);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h4 mb-0"><i class="fas fa-images text-primary"></i> <?php echo $mine ? 'My Albums' : 'Gallery'; ?></h1>
		<div class="d-flex align-items-center gap-2">
			<?php if ($mine): ?>
				<a class="btn btn-outline-primary btn-sm" href="gallery.php"><i class="fas fa-globe"></i> Show Public</a>
			<?php else: ?>
				<?php if ($user): ?>
					<a class="btn btn-outline-primary btn-sm" href="gallery.php?mine=1"><i class="fas fa-user"></i> My Albums</a>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ($user): ?>
				<a class="btn btn-primary btn-sm" href="upload_gallery.php"><i class="fas fa-plus"></i> Upload Album</a>
			<?php endif; ?>
			<?php if ($user && in_array($user['role'], ['staff','admin'])): ?>
				<a class="btn btn-outline-success btn-sm" href="admin/gallery.php"><i class="fas fa-check-circle"></i> Review Submissions</a>
			<?php endif; ?>
		</div>
	</div>
                <?php if (empty($albums)): ?>
		<div class="alert alert-info">No albums to show.</div>
                <?php else: ?>
		<div class="row g-3">
			<?php foreach ($albums as $a): ?>
				<div class="col-md-4">
					<div class="card h-100">
						<?php if (!empty($a['cover_path'])): ?>
                            <img src="<?php echo htmlspecialchars($a['cover_path']); ?>" class="card-img-top gallery-cover" alt="Album cover" style="height: 180px; object-fit: cover;">
						<?php else: ?>
							<div class="d-flex align-items-center justify-content-center bg-light" style="height: 180px;">
								<i class="fas fa-image text-muted" style="font-size: 2rem;"></i>
							</div>
						<?php endif; ?>
						<div class="card-body d-flex flex-column">
							<h5 class="card-title d-flex justify-content-between align-items-center mb-1">
								<span class="text-truncate" title="<?php echo htmlspecialchars($a['title']); ?>"><?php echo htmlspecialchars($a['title']); ?></span>
								<?php if ($mine): ?>
									<span class="badge rounded-pill <?php echo ($a['access_level']==='public') ? 'bg-primary' : 'bg-success'; ?>"><?php echo ($a['access_level']==='public') ? 'Public' : 'Private'; ?></span>
								<?php endif; ?>
							</h5>
							<small class="text-muted mb-2">Created <?php echo date('M j, Y', strtotime($a['created_at'])); ?></small>
							<a href="album.php?id=<?php echo $a['id']; ?>" class="stretched-link"></a>
						</div>
					</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
    @media (max-width: 576px){
        .gallery-cover{ height:auto !important; max-height:none; width:100%; object-fit:contain; }
    }
    </style>
</body>
</html>

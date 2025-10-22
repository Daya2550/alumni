<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

if (isset($_GET['approve'])) {
	$id = intval($_GET['approve']);
	db()->execute("UPDATE gallery_albums SET access_level='public', updated_at = NOW() WHERE id = ?", [$id]);
	header('Location: gallery.php');
	exit;
}

if (isset($_GET['make_batch'])) {
	$id = intval($_GET['make_batch']);
	db()->execute("UPDATE gallery_albums SET access_level='batch_only', updated_at = NOW() WHERE id = ?", [$id]);
	header('Location: gallery.php');
	exit;
}

if (isset($_GET['delete'])) {
	$id = intval($_GET['delete']);
	db()->execute("DELETE FROM gallery_albums WHERE id = ?", [$id]);
	header('Location: gallery.php');
	exit;
}

$pending = db()->fetchAll(
	"SELECT ga.*, u.name as created_by_name FROM gallery_albums ga JOIN users u ON ga.created_by = u.id WHERE ga.access_level = 'private' ORDER BY ga.created_at DESC"
);
$recent = db()->fetchAll(
	"SELECT ga.*, u.name as created_by_name FROM gallery_albums ga JOIN users u ON ga.created_by = u.id ORDER BY ga.created_at DESC LIMIT 50"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Gallery - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3"><i class="fas fa-images"></i> Manage Gallery</h1>
			<a href="../gallery.php" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View Gallery</a>
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
												<small class="text-muted">By <?php echo htmlspecialchars($item['created_by_name']); ?> • <?php echo date('M j, Y', strtotime($item['created_at'])); ?></small>
											</div>
											<div class="btn-group btn-group-sm">
												<a href="../album.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-secondary"><i class="fas fa-eye"></i></a>
												<a href="?approve=<?php echo $item['id']; ?>" class="btn btn-success"><i class="fas fa-check"></i></a>
												<a href="?make_batch=<?php echo $item['id']; ?>" class="btn btn-warning"><i class="fas fa-users"></i></a>
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
					<div class="card-header"><strong>Recent Albums</strong></div>
					<div class="card-body">
						<?php if (empty($recent)): ?>
							<p class="text-muted m-0">No albums yet.</p>
						<?php else: ?>
							<div class="list-group">
								<?php foreach ($recent as $item): ?>
									<a class="list-group-item list-group-item-action" href="../album.php?id=<?php echo $item['id']; ?>">
										<div class="d-flex justify-content-between align-items-start">
											<div>
												<div class="fw-bold"><?php echo htmlspecialchars($item['title']); ?></div>
												<small class="text-muted text-uppercase"><?php echo htmlspecialchars($item['access_level']); ?></small>
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
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>










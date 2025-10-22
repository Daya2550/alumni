<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$album_id = intval($_GET['id'] ?? 0);
if ($album_id <= 0) { header('Location: gallery.php'); exit; }

$album = db()->fetchOne("SELECT * FROM gallery_albums WHERE id = ?", [$album_id]);
if (!$album) { header('Location: gallery.php'); exit; }

$isPrivileged = Auth::hasAnyRole(['staff','admin']);
$isOwner = ($album['created_by'] === ($user['id'] ?? ''));
if (!($isPrivileged || $isOwner)) { header('HTTP/1.1 403 Forbidden'); exit('Access denied'); }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$album_type = $_POST['album_type'] ?? '';
		$batch = trim($_POST['batch'] ?? '');
		$event_id = intval($_POST['event_id'] ?? 0);
		$access_level = $album['access_level'];

		if ($title === '') {
			$error = 'Please provide a title.';
		} else {
			try {
				if ($isPrivileged && isset($_POST['access_level']) && in_array($_POST['access_level'], ['public','batch_only','private'], true)) {
					$access_level = $_POST['access_level'];
				}

				db()->execute(
					"UPDATE gallery_albums SET title=?, description=?, event_id=?, batch=?, access_level=?, updated_at=NOW() WHERE id=?",
					[
						sanitizeInput($title),
						sanitizeInput($description),
						($album_type === 'event' && $event_id > 0) ? $event_id : null,
						($album_type === 'batch' && $batch !== '') ? $batch : null,
						$access_level,
						$album_id
					]
				);
				$message = 'Updated successfully';
				$album = db()->fetchOne("SELECT * FROM gallery_albums WHERE id = ?", [$album_id]);
			} catch (Exception $e) {
				$error = 'Failed to update.';
				error_log('Edit album error: ' . $e->getMessage());
			}
		}
	}
}

$album_type_current = $album['event_id'] ? 'event' : 'batch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Edit Album - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3"><i class="fas fa-edit"></i> Edit Album</h1>
			<a href="album.php?id=<?php echo $album_id; ?>" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View Album</a>
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
				<form method="POST" class="row g-3">
					<input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

					<div class="col-12">
						<label class="form-label">Title *</label>
						<input type="text" name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($album['title']); ?>">
					</div>

					<div class="col-12">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($album['description']); ?></textarea>
					</div>

					<div class="col-md-6">
						<label class="form-label">Album Type</label>
						<select name="album_type" class="form-select">
							<option value="batch" <?php echo $album_type_current === 'batch' ? 'selected' : ''; ?>>Batch Album</option>
							<option value="event" <?php echo $album_type_current === 'event' ? 'selected' : ''; ?>>Event Album</option>
						</select>
					</div>
					<div class="col-md-6">
						<label class="form-label">Batch</label>
						<input type="text" name="batch" class="form-control" value="<?php echo htmlspecialchars($album['batch']); ?>">
					</div>

					<div class="col-12">
						<label class="form-label">Event ID</label>
						<input type="number" name="event_id" class="form-control" min="1" value="<?php echo htmlspecialchars((string)($album['event_id'] ?? '')); ?>">
					</div>

					<?php if ($isPrivileged): ?>
						<div class="col-md-6">
							<label class="form-label">Access Level</label>
							<select name="access_level" class="form-select">
								<?php foreach (['public'=>'Public','batch_only'=>'Batch Only','private'=>'Private'] as $k=>$v): ?>
									<option value="<?php echo $k; ?>" <?php echo $album['access_level'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<div class="col-12 text-end">
						<button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>










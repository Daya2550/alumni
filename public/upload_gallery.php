<?php
/**
 * Upload Gallery - Create album and upload media
 * Students' albums are set to private (pending approval). Staff/Admin auto-public.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$message = '';
$error = '';

$isPrivileged = Auth::hasAnyRole(['staff','admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request. Please refresh and try again.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$album_type = $_POST['album_type'] ?? '';
		$batch = trim($_POST['batch'] ?? '');
		$event_id = intval($_POST['event_id'] ?? 0);

		if ($title === '') {
			$error = 'Please provide an album title.';
		} else {
			try {
				$access_level = $isPrivileged ? 'public' : 'private';
				if ($album_type === 'batch' && $batch === '') {
					$batch = $user['batch'] ?? '';
				}

				// Create album
				db()->execute(
					"INSERT INTO gallery_albums (title, description, event_id, batch, access_level, created_by) VALUES (?, ?, ?, ?, ?, ?)",
					[
						sanitizeInput($title),
						sanitizeInput($description),
						($album_type === 'event' && $event_id > 0) ? $event_id : null,
						($album_type === 'batch' && $batch !== '') ? $batch : null,
						$access_level,
						$user['id']
					]
				);
				$albumId = db()->lastInsertId();

				// Handle media uploads
				$uploadDir = __DIR__ . '/../uploads/gallery/';
				if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

				if (!empty($_FILES['media']['name'][0])) {
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
						$type = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : (in_array($ext, ['mp4','mov','avi','mkv']) ? 'video' : 'image');
						$newName = uniqid('gal_', true) . '.' . $ext;
						$dest = $uploadDir . $newName;
						if (move_uploaded_file($tmp, $dest)) {
							$filePath = 'uploads/gallery/' . $newName;
							db()->execute(
								"INSERT INTO gallery_media (album_id, filename, original_filename, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
								[
									$albumId,
									$newName,
									$name,
									$filePath,
									$size,
									$type,
									$user['id']
								]
							);
						}
					}
				}

				if ($isPrivileged) {
					header('Location: gallery.php');
					exit;
				} else {
					$message = 'Submitted! Your album will be visible once approved by staff.';
				}
			} catch (Exception $e) {
				$error = 'Failed to upload. Please try again.';
				error_log('Upload gallery error: ' . $e->getMessage());
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
	<title>Upload Gallery - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
	<?php include 'includes/header.php'; ?>

	<main class="container py-4">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h1 class="h3"><i class="fas fa-cloud-upload-alt text-info"></i> Upload Gallery</h1>
					<a href="gallery.php" class="btn btn-outline-secondary"><i class="fas fa-images"></i> Back to Gallery</a>
				</div>

				<?php if ($error): ?>
					<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
				<?php endif; ?>
				<?php if ($message): ?>
					<div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body">
						<form method="POST" enctype="multipart/form-data" class="row g-3">
							<input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

							<div class="col-12">
								<label class="form-label">Album Title *</label>
								<input type="text" name="title" class="form-control" required maxlength="255">
							</div>

							<div class="col-12">
								<label class="form-label">Description</label>
								<textarea name="description" class="form-control" rows="4"></textarea>
							</div>

							<div class="col-md-6">
								<label class="form-label">Album Type</label>
								<select name="album_type" class="form-select">
									<option value="batch">Batch Album</option>
									<option value="event">Event Album</option>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label">Batch (optional)</label>
								<input type="text" name="batch" class="form-control" placeholder="e.g., 2024-2028" value="<?php echo htmlspecialchars($user['batch'] ?? ''); ?>">
							</div>

							<div class="col-12">
								<label class="form-label">Event ID (optional, for event albums)</label>
								<input type="number" name="event_id" class="form-control" min="1">
							</div>

							<div class="col-12">
								<label class="form-label"><i class="fas fa-paperclip"></i> Media Files</label>
								<input type="file" name="media[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.mkv">
								<div class="form-text">Max per file: <?php echo round(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB</div>
							</div>

							<div class="col-12 text-end">
								<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</main>

	<?php include 'includes/footer.php'; ?>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script src="assets/js/main.js"></script>
</body>
</html>










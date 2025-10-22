<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$event_id = intval($_GET['id'] ?? 0);
if ($event_id <= 0) { header('Location: events.php'); exit; }

$event = db()->fetchOne("SELECT * FROM events WHERE id = ?", [$event_id]);
if (!$event) { header('Location: events.php'); exit; }

// Get existing attachments
$attachments = db()->fetchAll(
    "SELECT * FROM event_attachments WHERE event_id = ? ORDER BY created_at ASC",
    [$event_id]
);

$isPrivileged = Auth::hasAnyRole(['staff','admin']);
$isAuthor = ($event['created_by'] === $user['id']);
if (!($isPrivileged || $isAuthor)) { header('HTTP/1.1 403 Forbidden'); exit('Access denied'); }

$message = '';
$error = '';

$event_types = [
	'webinar' => 'Webinar',
	'reunion' => 'Reunion',
	'workshop' => 'Workshop',
	'career_fair' => 'Career Fair'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
		$error = 'Invalid request.';
	} elseif (isset($_POST['delete_attachment'])) {
		// Handle attachment deletion
		$attachment_id = trim($_POST['delete_attachment']);
		try {
			$attachment = db()->fetchOne("SELECT * FROM event_attachments WHERE id = ? AND event_id = ?", [$attachment_id, $event_id]);
			if ($attachment) {
				// Delete file from filesystem
				$filePath = __DIR__ . '/../' . $attachment['file_path'];
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
				// Delete from database
				db()->execute("DELETE FROM event_attachments WHERE id = ?", [$attachment_id]);
				$message = 'Attachment deleted successfully.';
				// Refresh attachments list
				$attachments = db()->fetchAll("SELECT * FROM event_attachments WHERE event_id = ? ORDER BY created_at ASC", [$event_id]);
			} else {
				$error = 'Attachment not found.';
			}
		} catch (Exception $e) {
			$error = 'Failed to delete attachment.';
			error_log('Delete attachment error: ' . $e->getMessage());
		}
	} else {
		$title = trim($_POST['title'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$date = trim($_POST['date'] ?? '');
		$time = trim($_POST['time'] ?? '');
		$venue = trim($_POST['venue'] ?? '');
		$venue_map_url = trim($_POST['venue_map_url'] ?? '');
		$agenda = trim($_POST['agenda'] ?? '');
		$registration_link = trim($_POST['registration_link'] ?? '');
		$capacity = trim($_POST['capacity'] ?? '');
		$event_type = $_POST['event_type'] ?? $event['event_type'];
		$status = $event['status'];

		if ($title === '' || $date === '' || $time === '' || !isset($event_types[$event_type])) {
			$error = 'Please fill in Title, Date, Time and select a valid Event Type.';
		} else {
			try {
				$eventDateTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
				$capacityVal = ($capacity !== '' && ctype_digit($capacity)) ? intval($capacity) : null;

				if ($isPrivileged && isset($_POST['status']) && in_array($_POST['status'], ['draft','published','cancelled'], true)) {
					$status = $_POST['status'];
				}

				// Start transaction for event and attachments
				db()->beginTransaction();

				try {
					db()->execute(
						"UPDATE events SET title=?, description=?, event_date=?, venue=?, venue_map_url=?, agenda=?, registration_link=?, capacity=?, event_type=?, status=?, updated_at=NOW() WHERE id=?",
						[
							sanitizeInput($title),
							sanitizeInput($description),
							$eventDateTime,
							sanitizeInput($venue),
							$venue_map_url,
							sanitizeInput($agenda),
							$registration_link,
							$capacityVal,
							$event_type,
							$status,
							$event_id
						]
					);

					// Handle new file attachments
					if (!empty($_FILES['new_attachments']['name'][0])) {
						$uploadDir = __DIR__ . '/../uploads/events/';
						if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

						$fileCount = count($_FILES['new_attachments']['name']);
						for ($i = 0; $i < $fileCount; $i++) {
							$fileName = $_FILES['new_attachments']['name'][$i] ?? '';
							$fileTmp = $_FILES['new_attachments']['tmp_name'][$i] ?? '';
							$fileError = $_FILES['new_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
							$fileSize = $_FILES['new_attachments']['size'][$i] ?? 0;

							if ($fileError === UPLOAD_ERR_OK && is_uploaded_file($fileTmp) && $fileName !== '') {
								if ($fileSize > UPLOAD_MAX_SIZE) {
									throw new Exception('File "' . $fileName . '" is too large.');
								}

								$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
								$allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ['pdf', 'doc', 'docx', 'txt']);
								if (!in_array($ext, $allowedTypes, true)) {
									throw new Exception('File type not allowed for "' . $fileName . '".');
								}

								$newFileName = uniqid('event_', true) . '.' . $ext;
								$filePath = $uploadDir . $newFileName;

								if (move_uploaded_file($fileTmp, $filePath)) {
									$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
									
									db()->execute(
										"INSERT INTO event_attachments (id, event_id, uploader_id, file_path, file_name, mime_type, file_size) 
										 VALUES (?, ?, ?, ?, ?, ?, ?)",
										[
											generateUUID(),
											$event_id,
											$user['id'],
											'uploads/events/' . $newFileName,
											$fileName,
											$mimeType,
											$fileSize
										]
									);
								}
							}
						}
					}

					db()->commit();
					$message = 'Updated successfully';
					$event = db()->fetchOne("SELECT * FROM events WHERE id = ?", [$event_id]);
					// Refresh attachments list
					$attachments = db()->fetchAll("SELECT * FROM event_attachments WHERE event_id = ? ORDER BY created_at ASC", [$event_id]);
				} catch (Exception $e) {
					db()->rollback();
					throw $e;
				}
			} catch (Exception $e) {
				$error = 'Failed to update.';
				error_log('Edit event error: ' . $e->getMessage());
			}
		}
	}
}

$dateVal = substr($event['event_date'], 0, 10);
$timeVal = substr($event['event_date'], 11, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Edit Event - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3"><i class="fas fa-calendar-edit"></i> Edit Event</h1>
			<a href="event.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View Event</a>
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
				<form method="POST" enctype="multipart/form-data" class="row g-3">
					<input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

					<div class="col-12">
						<label class="form-label">Title *</label>
						<input type="text" name="title" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($event['title']); ?>">
					</div>

					<div class="col-md-6">
						<label class="form-label">Date *</label>
						<input type="date" name="date" class="form-control" required value="<?php echo htmlspecialchars($dateVal); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label">Time *</label>
						<input type="time" name="time" class="form-control" required value="<?php echo htmlspecialchars($timeVal); ?>">
					</div>

					<div class="col-md-6">
						<label class="form-label">Event Type *</label>
						<select name="event_type" class="form-select" required>
							<?php foreach ($event_types as $key=>$label): ?>
								<option value="<?php echo $key; ?>" <?php echo $event['event_type'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6">
						<label class="form-label">Capacity</label>
						<input type="number" name="capacity" class="form-control" min="1" value="<?php echo htmlspecialchars((string)($event['capacity'] ?? '')); ?>">
					</div>

					<div class="col-12">
						<label class="form-label">Venue</label>
						<input type="text" name="venue" class="form-control" value="<?php echo htmlspecialchars($event['venue']); ?>">
					</div>

					<div class="col-12">
						<label class="form-label">Venue Map Embed</label>
						<textarea name="venue_map_url" class="form-control" rows="2"><?php echo htmlspecialchars($event['venue_map_url']); ?></textarea>
					</div>

					<div class="col-12">
						<label class="form-label">Registration Link</label>
						<input type="url" name="registration_link" class="form-control" value="<?php echo htmlspecialchars($event['registration_link']); ?>">
					</div>

					<div class="col-12">
						<label class="form-label">Agenda</label>
						<textarea name="agenda" class="form-control" rows="3"><?php echo htmlspecialchars($event['agenda']); ?></textarea>
					</div>

					<div class="col-12">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($event['description']); ?></textarea>
					</div>

					<?php if ($isPrivileged): ?>
						<div class="col-md-6">
							<label class="form-label">Status</label>
							<select name="status" class="form-select">
								<?php foreach (['draft'=>'Draft','published'=>'Published','cancelled'=>'Cancelled'] as $k=>$v): ?>
									<option value="<?php echo $k; ?>" <?php echo $event['status'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<!-- Existing Attachments -->
					<?php if (!empty($attachments)): ?>
					<div class="col-12">
						<h5><i class="fas fa-paperclip"></i> Current Attachments</h5>
						<div class="row g-2">
							<?php foreach ($attachments as $attachment): ?>
								<div class="col-md-6 col-lg-4">
									<div class="card">
										<div class="card-body p-3">
											<div class="d-flex align-items-center mb-2">
												<?php 
												$ext = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
												$isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
												if ($isImage): ?>
													<i class="fas fa-image text-primary me-2"></i>
												<?php elseif ($ext === 'pdf'): ?>
													<i class="fas fa-file-pdf text-danger me-2"></i>
												<?php elseif (in_array($ext, ['doc', 'docx'])): ?>
													<i class="fas fa-file-word text-info me-2"></i>
												<?php else: ?>
													<i class="fas fa-file text-secondary me-2"></i>
												<?php endif; ?>
												<div class="flex-grow-1">
													<div class="fw-bold small"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
													<div class="text-muted small"><?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB</div>
												</div>
											</div>
											<div class="d-flex gap-1">
												<a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
												   class="btn btn-sm btn-outline-primary flex-grow-1" 
												   target="_blank">
													<i class="fas fa-download me-1"></i>Download
												</a>
												<button type="submit" name="delete_attachment" value="<?php echo htmlspecialchars($attachment['id']); ?>" 
														class="btn btn-sm btn-outline-danger" 
														onclick="return confirm('Are you sure you want to delete this attachment?')">
													<i class="fas fa-trash"></i>
												</button>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<?php endif; ?>

					<!-- Add New Attachments -->
					<div class="col-12">
						<label class="form-label">Add New Attachments (optional)</label>
						<input type="file" name="new_attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
						<div class="form-text">
							<i class="fas fa-info-circle me-1"></i>
							You can upload multiple files. Supported formats: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX, TXT). Max size per file: <?php echo number_format(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB
						</div>
					</div>

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










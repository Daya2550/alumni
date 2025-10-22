<?php
/**
 * Submit Event - Logged-in users can propose events. Staff/Admin auto-publish.
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
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
		$error = 'Invalid request. Please refresh and try again.';
	} else {
		$title = trim($_POST['title'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$date = trim($_POST['date'] ?? ''); // YYYY-MM-DD
		$time = trim($_POST['time'] ?? ''); // HH:MM
		$venue = trim($_POST['venue'] ?? '');
		$venue_map_url = trim($_POST['venue_map_url'] ?? '');
		$agenda = trim($_POST['agenda'] ?? '');
		$registration_link = trim($_POST['registration_link'] ?? '');
		$capacity = trim($_POST['capacity'] ?? '');
		$event_type = $_POST['event_type'] ?? '';

		if ($title === '' || $date === '' || $time === '' || !isset($event_types[$event_type])) {
			$error = 'Please fill in Title, Date, Time and select a valid Event Type.';
		} else {
			try {
				$eventDateTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
				$capacityVal = ($capacity !== '' && ctype_digit($capacity)) ? intval($capacity) : null;

				$isPrivileged = Auth::hasAnyRole(['staff','admin']);
				$status = $isPrivileged ? 'published' : 'draft';

				// Start transaction for event and attachments
				db()->beginTransaction();

				try {
					db()->execute(
						"INSERT INTO events (title, description, event_date, venue, venue_map_url, agenda, registration_link, capacity, event_type, created_by, status)
						 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
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
							$user['id'],
							$status
						]
					);

					$eventId = db()->lastInsertId();

					// Handle file attachments
					if (!empty($_FILES['attachments']['name'][0])) {
						$uploadDir = __DIR__ . '/../uploads/events/';
						if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

						$fileCount = count($_FILES['attachments']['name']);
						for ($i = 0; $i < $fileCount; $i++) {
							$fileName = $_FILES['attachments']['name'][$i] ?? '';
							$fileTmp = $_FILES['attachments']['tmp_name'][$i] ?? '';
							$fileError = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
							$fileSize = $_FILES['attachments']['size'][$i] ?? 0;

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
											$eventId,
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
				} catch (Exception $e) {
					db()->rollback();
					throw $e;
				}

                // If published immediately, notify all active users except creator
                if ($status === 'published') {
                    Notifications::notifyAllActiveUsersExcept($user['id'], 'New Event', substr($title, 0, 120), 'info', 'event', $eventId);
                }

				if ($isPrivileged) {
					header('Location: events.php');
					exit;
				} else {
					$message = 'Submitted! Your event will be visible once approved by staff.';
				}
			} catch (Exception $e) {
				$error = 'Failed to submit event. Please try again.';
				error_log('Submit event error: ' . $e->getMessage());
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
	<title>Submit Event - <?php echo APP_NAME; ?></title>
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
					<h1 class="h3"><i class="fas fa-calendar-plus text-primary"></i> Submit Event</h1>
					<a href="events.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Events</a>
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
								<label class="form-label">Title</label>
								<input type="text" name="title" class="form-control" required>
							</div>

							<div class="col-md-6">
								<label class="form-label">Date</label>
								<input type="date" name="date" class="form-control" required>
							</div>
							<div class="col-md-6">
								<label class="form-label">Time</label>
								<input type="time" name="time" class="form-control" required>
							</div>

							<div class="col-md-6">
								<label class="form-label">Event Type</label>
								<select class="form-select" name="event_type" required>
									<option value="">Select type</option>
									<?php foreach ($event_types as $value => $label): ?>
										<option value="<?php echo $value; ?>"><?php echo $label; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label">Capacity (optional)</label>
								<input type="number" name="capacity" class="form-control" min="1">
							</div>

							<div class="col-12">
								<label class="form-label">Venue (optional)</label>
								<input type="text" name="venue" class="form-control" placeholder="e.g., Main Auditorium">
							</div>

							<div class="col-12">
								<label class="form-label">Venue Map Embed (optional)</label>
								<textarea name="venue_map_url" class="form-control" rows="2" placeholder="Paste Google Maps embed iframe or link"></textarea>
							</div>

							<div class="col-12">
								<label class="form-label">Registration Link (optional)</label>
								<input type="url" name="registration_link" class="form-control" placeholder="https://...">
							</div>

							<div class="col-12">
								<label class="form-label">Agenda (optional)</label>
								<textarea name="agenda" class="form-control" rows="3"></textarea>
							</div>

							<div class="col-12">
								<label class="form-label">Description</label>
								<textarea name="description" class="form-control" rows="6" required></textarea>
							</div>

							<div class="col-12">
								<label class="form-label">Attachments (optional)</label>
								<input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
								<div class="form-text">
									<i class="fas fa-info-circle me-1"></i>
									You can upload multiple files. Supported formats: Images (JPG, PNG, GIF), Documents (PDF, DOC, DOCX, TXT). Max size per file: <?php echo number_format(UPLOAD_MAX_SIZE / 1024 / 1024, 1); ?>MB
								</div>
							</div>

							<div class="col-12 d-flex justify-content-end gap-2">
								<button type="reset" class="btn btn-outline-secondary"><i class="fas fa-undo"></i> Reset</button>
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



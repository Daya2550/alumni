<?php
/**
 * Admin Events Management - Review and moderate event submissions
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

$message = '';
$error = '';

// Actions: publish, cancel, delete
if (isset($_GET['publish'])) {
	$id = intval($_GET['publish']);
	db()->execute("UPDATE events SET status='published', updated_at = NOW() WHERE id = ?", [$id]);
	header('Location: events.php');
	exit;
}

if (isset($_GET['cancel'])) {
	$id = intval($_GET['cancel']);
	db()->execute("UPDATE events SET status='cancelled', updated_at = NOW() WHERE id = ?", [$id]);
	header('Location: events.php');
	exit;
}

if (isset($_GET['delete'])) {
	$id = intval($_GET['delete']);
	db()->execute("DELETE FROM events WHERE id = ?", [$id]);
	header('Location: events.php');
	exit;
}

// Load draft/pending-like and recent events
$pending = db()->fetchAll(
	"SELECT e.*, u.name AS author_name FROM events e JOIN users u ON u.id = e.created_by WHERE e.status IN ('draft') ORDER BY e.created_at DESC"
);
$recent = db()->fetchAll(
	"SELECT e.*, u.name AS author_name FROM events e JOIN users u ON u.id = e.created_by ORDER BY e.created_at DESC LIMIT 50"
);

$event_types = [
	'webinar' => 'Webinar',
	'reunion' => 'Reunion',
	'workshop' => 'Workshop',
	'career_fair' => 'Career Fair'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Events - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
	<div class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3"><i class="fas fa-calendar-check"></i> Manage Events</h1>
			<a href="../events.php" class="btn btn-outline-secondary"><i class="fas fa-eye"></i> View Events</a>
		</div>

		<div class="row g-4">
			<div class="col-lg-6">
				<div class="card">
					<div class="card-header"><strong>Pending/Drafts</strong></div>
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
												<small class="text-muted">By <?php echo htmlspecialchars($item['author_name']); ?> • <?php echo date('M j, Y', strtotime($item['created_at'])); ?> • <?php echo $event_types[$item['event_type']] ?? $item['event_type']; ?></small>
											</div>
											<div class="btn-group btn-group-sm">
												<a href="?publish=<?php echo $item['id']; ?>" class="btn btn-success" title="Publish"><i class="fas fa-check"></i></a>
												<a href="?cancel=<?php echo $item['id']; ?>" class="btn btn-warning" title="Cancel"><i class="fas fa-ban"></i></a>
												<a href="?delete=<?php echo $item['id']; ?>" class="btn btn-danger" title="Delete"><i class="fas fa-trash"></i></a>
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
					<div class="card-header"><strong>Recent Events</strong></div>
					<div class="card-body">
						<?php if (empty($recent)): ?>
							<p class="text-muted m-0">No events yet.</p>
						<?php else: ?>
							<div class="list-group">
								<?php foreach ($recent as $item): ?>
									<a class="list-group-item list-group-item-action" href="../event.php?id=<?php echo $item['id']; ?>">
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










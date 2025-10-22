<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

// Load notifications for user
$notifications = db()->fetchAll(
	"SELECT id, title, message, type, is_read, related_type, related_id, created_at
	 FROM notifications
	 WHERE user_id = ?
	 ORDER BY created_at DESC
	 LIMIT 200",
	[$user['id']]
);

// Mark all as read when viewing
db()->execute("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Notifications - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
	<?php include 'includes/header.php'; ?>

	<main class="container py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3"><i class="fas fa-bell text-warning"></i> Notifications</h1>
		</div>

		<div class="card">
			<div class="list-group list-group-flush">
				<?php if (empty($notifications)): ?>
					<div class="list-group-item text-center text-muted py-5">
						<i class="fas fa-bell-slash fa-2x"></i>
						<div class="mt-2">No notifications yet.</div>
					</div>
				<?php else: ?>
					<?php foreach ($notifications as $n): ?>
						<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start" href="<?php echo htmlspecialchars(resolveNotificationLink($n)); ?>">
							<div>
								<div class="fw-bold"><?php echo htmlspecialchars($n['title']); ?></div>
								<div class="text-muted small"><?php echo htmlspecialchars($n['message']); ?></div>
							</div>
							<small class="text-muted"><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?></small>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</main>

	<?php include 'includes/footer.php'; ?>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper to route to related content
function resolveNotificationLink(array $n): string {
	switch ($n['related_type']) {
		case 'news': return 'news_post.php?id=' . urlencode((string)$n['related_id']);
		case 'job': return 'job.php?id=' . urlencode((string)$n['related_id']);
		case 'event': return 'event.php?id=' . urlencode((string)$n['related_id']);
		case 'notice': return 'notice.php?id=' . urlencode((string)$n['related_id']);
		case 'message': return 'message.php?room_id=' . urlencode((string)$n['related_id']);
		case 'survey': return 'survey_take.php?id=' . urlencode((string)$n['related_id']);
	case 'project': return 'project.php?id=' . urlencode((string)$n['related_id']);
		case 'feed_post': return 'feed.php#post-' . urlencode((string)$n['related_id']);
		default: return '#';
	}
}
?>










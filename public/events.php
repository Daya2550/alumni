<?php
/**
 * Events - List all events
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$mine = isset($_GET['mine']) && $user ? true : false;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$event_type = sanitizeInput($_GET['type'] ?? '');
$date_filter = sanitizeInput($_GET['date'] ?? '');
if ($date_filter === '') { $date_filter = 'all'; }

// Build query
$where_conditions = ["e.status = 'published'"];
$params = [];

// Event type filter
if ($event_type) {
    $where_conditions[] = 'e.event_type = ?';
    $params[] = $event_type;
}

// Date filter
if ($date_filter === 'upcoming') {
    // Include events happening today or later
    $where_conditions[] = 'DATE(e.event_date) >= CURDATE()';
} elseif ($date_filter === 'past') {
    $where_conditions[] = 'DATE(e.event_date) < CURDATE()';
} else {
    // 'all' -> no date restriction
}

// Search functionality
if ($search) {
    $where_conditions[] = '(e.title LIKE ? OR e.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get events
$events_query = "
    SELECT 
        e.id,
        e.title,
        e.description,
        e.event_date,
        e.venue,
        e.venue_map_url,
        e.agenda,
        e.registration_link,
        e.capacity,
        e.event_type,
        e.status,
        e.created_at,
        e.created_by,
        u.name AS created_by_name,
        (
            SELECT COUNT(*) 
            FROM event_rsvps er 
            WHERE er.event_id = e.id AND er.status = 'attending'
        ) AS rsvp_count,
        (
            SELECT COALESCE(
                (SELECT er2.status FROM event_rsvps er2 WHERE er2.event_id = e.id AND er2.user_id = ? LIMIT 1),
                'not_attending'
            )
        ) AS user_rsvp_status
    FROM events e
    JOIN users u ON e.created_by = u.id
    $where_clause
    ORDER BY e.event_date ASC
    LIMIT ? OFFSET ?
";

$params = array_merge([$user ? $user['id'] : ''], $params, [$limit, $offset]);
$events = db()->fetchAll($events_query, $params);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM events e 
    $where_clause
";
$count_params = array_slice($params, 1, -2); // Remove user_id, limit, offset
$total_events = db()->fetchOne($count_query, $count_params)['total'];
$total_pages = ceil($total_events / $limit);

// Get event types for filter
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
    <title>Events - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-calendar-alt text-success"></i> Events</h1>
                    <div class="d-flex gap-2">
                        <?php if (Auth::hasAnyRole(['student','alumni','staff','admin'])): ?>
                            <a href="submit_event.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Submit Event
                            </a>
                        <?php endif; ?>
                        <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
                            <a href="admin/events.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Review Submissions
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Events</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search events...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Event Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <?php foreach ($event_types as $type_value => $type_label): ?>
                                        <option value="<?php echo $type_value; ?>" 
                                                <?php echo $event_type === $type_value ? 'selected' : ''; ?>>
                                            <?php echo $type_label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date Filter</label>
                                <select class="form-select" id="date" name="date">
                                    <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Events</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Events List -->
                <?php if (empty($events)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-alt text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-muted">No events found</h3>
                            <p class="text-muted">There are no events matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($events as $event): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100 event-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <span class="badge bg-primary mb-2"><?php echo $event_types[$event['event_type']]; ?></span>
                                                <h5 class="card-title mb-1">
                                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </a>
                                                </h5>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($event['user_rsvp_status'] === 'attending'): ?>
                                                    <span class="badge bg-success">RSVP'd</span>
                                                <?php elseif ($event['user_rsvp_status'] === 'maybe'): ?>
                                                    <span class="badge bg-warning">Maybe</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Attending</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar text-primary me-2"></i>
                                                <strong><?php echo date('F j, Y', strtotime($event['event_date'])); ?></strong>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-clock text-primary me-2"></i>
                                                <span><?php echo date('g:i A', strtotime($event['event_date'])); ?></span>
                                            </div>
                                            <?php if ($event['venue']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                    <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="card-text">
                                            <?php echo htmlspecialchars(substr(strip_tags($event['description']), 0, 120)); ?>
                                            <?php if (strlen(strip_tags($event['description'])) > 120): ?>...<?php endif; ?>
                                        </p>
                                        
                                        <?php if ($event['capacity']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-users"></i> 
                                                    Capacity: <?php echo $event['capacity']; ?> people
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-muted">
                                                <small>
                                                    <i class="fas fa-users"></i> <?php echo $event['rsvp_count']; ?> attending
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                                <?php if (Auth::hasAnyRole(['staff','admin']) || ($event['created_by'] === ($user['id'] ?? ''))): ?>
                                                    <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Events pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($event_type); ?>&date=<?php echo urlencode($date_filter); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($event_type); ?>&date=<?php echo urlencode($date_filter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($event_type); ?>&date=<?php echo urlencode($date_filter); ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
    <script>
        async function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event? This cannot be undone.')) return;
            try {
                const res = await fetch('api/event_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId, csrf_token: '<?php echo Auth::generateCSRFToken(); ?>' })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to delete event');
                }
            } catch (e) {
                alert('Error deleting event');
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>

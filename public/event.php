<?php
/**
 * Individual Event View
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();
$event_id = intval($_GET['id'] ?? 0);

if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Get event details
$event = db()->fetchOne(
    "SELECT e.*, u.name as created_by_name, u.email as created_by_email
     FROM events e 
     JOIN users u ON e.created_by = u.id 
     WHERE e.id = ?",
    [$event_id]
);

if (!$event) {
    header('HTTP/1.1 404 Not Found');
    echo 'Event not found';
    exit;
}

// Only allow viewing if published, or if user is staff/admin, or if user is the author
if ($event['status'] !== 'published') {
    $canView = false;
    if ($user) {
        if (Auth::hasAnyRole(['staff','admin'])) { $canView = true; }
        if ($event['created_by'] === $user['id']) { $canView = true; }
    }
    if (!$canView) {
        header('HTTP/1.1 403 Forbidden');
        echo 'This event is not published.';
        exit;
    }
}

// Get event attachments
$attachments = db()->fetchAll(
    "SELECT * FROM event_attachments WHERE event_id = ? ORDER BY created_at ASC",
    [$event_id]
);

// Get user's RSVP status
$user_rsvp = db()->fetchOne(
    "SELECT * FROM event_rsvps WHERE event_id = ? AND user_id = ?",
    [$event_id, $user['id']]
);

// Handle RSVP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp_status'])) {
    $rsvp_status = sanitizeInput($_POST['rsvp_status']);
    
    if (in_array($rsvp_status, ['attending', 'not_attending', 'maybe'])) {
        try {
            if ($user_rsvp) {
                // Update existing RSVP
                db()->execute(
                    "UPDATE event_rsvps SET status = ?, updated_at = NOW() WHERE event_id = ? AND user_id = ?",
                    [$rsvp_status, $event_id, $user['id']]
                );
            } else {
                // Create new RSVP
                db()->execute(
                    "INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?)",
                    [$event_id, $user['id'], $rsvp_status]
                );
            }
            
            $success_message = 'RSVP updated successfully!';
            $user_rsvp = ['status' => $rsvp_status];
            
        } catch (Exception $e) {
            $error_message = 'Failed to update RSVP. Please try again.';
            error_log("Event RSVP error: " . $e->getMessage());
        }
    }
}

// Get RSVP statistics
$rsvp_stats = db()->fetchOne(
    "SELECT 
        COUNT(CASE WHEN status = 'attending' THEN 1 END) as attending_count,
        COUNT(CASE WHEN status = 'maybe' THEN 1 END) as maybe_count,
        COUNT(CASE WHEN status = 'not_attending' THEN 1 END) as not_attending_count
     FROM event_rsvps WHERE event_id = ?",
    [$event_id]
);

// Get attendees list
$attendees = db()->fetchAll(
    "SELECT u.name, u.profile_photo, u.batch, er.rsvped_at
     FROM event_rsvps er
     JOIN users u ON er.user_id = u.id
     WHERE er.event_id = ? AND er.status = 'attending'
     ORDER BY er.rsvped_at ASC",
    [$event_id]
);

// Event types
$event_types = [
    'webinar' => 'Webinar',
    'reunion' => 'Reunion',
    'workshop' => 'Workshop',
    'career_fair' => 'Career Fair'
];

// Check if event has passed
$event_passed = strtotime($event['event_date']) < time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Event Details -->
                <article class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="h3 mb-2"><?php echo htmlspecialchars($event['title']); ?></h1>
                                <span class="badge bg-primary mb-2"><?php echo $event_types[$event['event_type']]; ?></span>
                                <div class="text-muted">
                                    <i class="fas fa-user"></i> Created by <?php echo htmlspecialchars($event['created_by_name']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y \a\t g:i A', strtotime($event['created_at'])); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if ($event_passed): ?>
                                    <span class="badge bg-secondary fs-6">Past Event</span>
                                <?php else: ?>
                                    <span class="badge bg-success fs-6">Upcoming</span>
                                <?php endif; ?>
                                <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
                                    <div class="dropdown d-inline-block ms-2">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-download"></i> Export RSVPs
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="admin/event_rsvps_export.php?id=<?php echo $event_id; ?>&status=all">All</a></li>
                                            <li><a class="dropdown-item" href="admin/event_rsvps_export.php?id=<?php echo $event_id; ?>&status=attending">Attending</a></li>
                                            <li><a class="dropdown-item" href="admin/event_rsvps_export.php?id=<?php echo $event_id; ?>&status=maybe">Maybe</a></li>
                                            <li><a class="dropdown-item" href="admin/event_rsvps_export.php?id=<?php echo $event_id; ?>&status=not_attending">Not Attending</a></li>
                                        </ul>
                                    </div>
                                    <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-edit"></i> Edit</a>
                                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteEvent(<?php echo $event_id; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Event Information -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar text-primary me-3"></i>
                                    <div>
                                        <strong>Date & Time</strong><br>
                                        <span class="text-muted">
                                            <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?><br>
                                            <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($event['venue']): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-map-marker-alt text-success me-3"></i>
                                        <div>
                                            <strong>Venue</strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($event['venue']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($event['capacity']): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-users text-warning me-3"></i>
                                        <div>
                                            <strong>Capacity</strong><br>
                                            <span class="text-muted"><?php echo $event['capacity']; ?> people</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-bar text-info me-3"></i>
                                    <div>
                                        <strong>RSVP Status</strong><br>
                                        <span class="text-muted">
                                            <?php echo $rsvp_stats['attending_count']; ?> attending, 
                                            <?php echo $rsvp_stats['maybe_count']; ?> maybe
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Event Description -->
                        <div class="mb-4">
                            <h5><i class="fas fa-file-text"></i> Event Description</h5>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        </div>

                        <?php if (!empty($attachments)): ?>
                        <div class="mb-4">
                            <h5><i class="fas fa-paperclip"></i> Event Attachments</h5>
                            <div class="row g-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card h-100">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center">
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
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" 
                                                       class="btn btn-sm btn-outline-primary w-100" 
                                                       target="_blank">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Agenda -->
                        <?php if ($event['agenda']): ?>
                            <div class="mb-4">
                                <h5><i class="fas fa-list"></i> Agenda</h5>
                                <div class="agenda-content">
                                    <?php echo nl2br(htmlspecialchars($event['agenda'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Venue Map -->
                        <?php if ($event['venue_map_url']): ?>
                            <div class="mb-4">
                                <h5><i class="fas fa-map"></i> Location Map</h5>
                                <div class="map-container">
                                    <?php echo $event['venue_map_url']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- RSVP Actions -->
                        <?php if (!$event_passed): ?>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><i class="fas fa-hand-paper"></i> RSVP for this Event</h5>
                                    <form method="POST" class="d-flex gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rsvp_status" id="attending" 
                                                   value="attending" <?php echo ($user_rsvp['status'] ?? '') === 'attending' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="attending">
                                                <i class="fas fa-check text-success"></i> I will attend
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rsvp_status" id="maybe" 
                                                   value="maybe" <?php echo ($user_rsvp['status'] ?? '') === 'maybe' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="maybe">
                                                <i class="fas fa-question text-warning"></i> Maybe
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="rsvp_status" id="not_attending" 
                                                   value="not_attending" <?php echo ($user_rsvp['status'] ?? '') === 'not_attending' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="not_attending">
                                                <i class="fas fa-times text-danger"></i> I cannot attend
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-primary ms-auto">
                                            <i class="fas fa-paper-plane"></i> Submit RSVP
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                This event has already passed.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Registration Link -->
                        <?php if ($event['registration_link']): ?>
                            <div class="mt-3">
                                <a href="<?php echo htmlspecialchars($event['registration_link']); ?>" 
                                   class="btn btn-success" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Register for Event
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <i class="fas fa-users"></i> 
                                <?php echo $rsvp_stats['attending_count'] + $rsvp_stats['maybe_count']; ?> responses
                            </div>
                            <div>
                                <a href="events.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Events
                                </a>
                                <button onclick="window.print()" class="btn btn-outline-primary">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            
            <div class="col-lg-4">
                <!-- Attendees -->
                <?php if (!empty($attendees)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-users"></i> Attendees (<?php echo count($attendees); ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($attendees as $attendee): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center">
                                            <?php if ($attendee['profile_photo']): ?>
                                                <img src="<?php echo htmlspecialchars($attendee['profile_photo']); ?>" 
                                                     class="rounded-circle me-3" width="40" height="40">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle me-3 fa-2x text-muted"></i>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($attendee['name']); ?></div>
                                                <?php if ($attendee['batch']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($attendee['batch']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- RSVP Statistics -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> RSVP Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-check text-success"></i> Attending</span>
                                <span class="fw-bold"><?php echo $rsvp_stats['attending_count']; ?></span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-question text-warning"></i> Maybe</span>
                                <span class="fw-bold"><?php echo $rsvp_stats['maybe_count']; ?></span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-times text-danger"></i> Not Attending</span>
                                <span class="fw-bold"><?php echo $rsvp_stats['not_attending_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="events.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> All Events
                            </a>
                            <button onclick="shareEvent()" class="btn btn-outline-secondary">
                                <i class="fas fa-share"></i> Share Event
                            </button>
                            <button onclick="addToCalendar()" class="btn btn-outline-success">
                                <i class="fas fa-calendar-plus"></i> Add to Calendar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function shareEvent() {
            const url = window.location.href;
            const title = document.querySelector('h1').textContent;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Event link copied to clipboard!');
                });
            }
        }
        
        function addToCalendar() {
            // Create ICS file for calendar import
            const eventData = {
                title: document.querySelector('h1').textContent,
                start: '<?php echo date('Ymd\THis\Z', strtotime($event['event_date'])); ?>',
                end: '<?php echo date('Ymd\THis\Z', strtotime($event['event_date']) + 7200); ?>',
                location: '<?php echo addslashes($event['venue']); ?>',
                description: '<?php echo addslashes(strip_tags($event['description'])); ?>'
            };
            
            const escapeICS = (s)=> String(s||'').replace(/[\n\r]/g, ' ');
            const icsContent = 'BEGIN:VCALENDAR\n'
                + 'VERSION:2.0\n'
                + 'PRODID:-//Alumni Portal//Event//EN\n'
                + 'BEGIN:VEVENT\n'
                + 'DTSTART:' + eventData.start + '\n'
                + 'DTEND:' + eventData.end + '\n'
                + 'SUMMARY:' + escapeICS(eventData.title) + '\n'
                + 'DESCRIPTION:' + escapeICS(eventData.description) + '\n'
                + 'LOCATION:' + escapeICS(eventData.location) + '\n'
                + 'END:VEVENT\n'
                + 'END:VCALENDAR';
            
            const blob = new Blob([icsContent], { type: 'text/calendar' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'event.ics';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
    <?php if (Auth::hasAnyRole(['staff','admin'])): ?>
    <script>
        async function deleteEvent(eventId) {
            if (!confirm('Delete this event permanently?')) return;
            try {
                const res = await fetch('api/event_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId, csrf_token: '<?php echo Auth::generateCSRFToken(); ?>' })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'events.php';
                } else {
                    alert(data.message || 'Failed to delete');
                }
            } catch (e) {
                alert('Error deleting event');
            }
        }
    </script>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <script>
            showAlert('success', '<?php echo addslashes($success_message); ?>');
        </script>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <script>
            showAlert('danger', '<?php echo addslashes($error_message); ?>');
        </script>
    <?php endif; ?>
</body>
</html>

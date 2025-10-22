<?php
/**
 * Export RSVPs for an event as CSV (Excel-compatible)
 * Access: Staff/Admin only
 * Query params:
 *   id     - event id (required)
 *   status - one of: all (default), attending, maybe, not_attending
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['staff','admin']);

$eventId = intval($_GET['id'] ?? 0);
$status = $_GET['status'] ?? 'all';

if (!$eventId) {
	header('HTTP/1.1 400 Bad Request');
	echo 'Missing event id';
	exit;
}

$validStatuses = ['all','attending','maybe','not_attending'];
if (!in_array($status, $validStatuses, true)) {
	$status = 'all';
}

// Load event for filename context
$event = db()->fetchOne("SELECT id, title, event_date FROM events WHERE id = ?", [$eventId]);
if (!$event) {
	header('HTTP/1.1 404 Not Found');
	echo 'Event not found';
	exit;
}

$where = 'WHERE er.event_id = ?';
$params = [$eventId];
if ($status !== 'all') {
	$where .= ' AND er.status = ?';
	$params[] = $status;
}

$rows = db()->fetchAll(
	"SELECT 
		er.status,
		er.rsvped_at,
		u.name,
		u.email,
		u.batch,
		u.company,
		u.job_title
	 FROM event_rsvps er
	 JOIN users u ON u.id = er.user_id
	 $where
	 ORDER BY FIELD(er.status,'attending','maybe','not_attending'), er.rsvped_at ASC",
	$params
);

// Prepare CSV
$filenameTitle = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower(substr($event['title'], 0, 60)));
$datePart = date('Ymd', strtotime($event['event_date']));
$statusPart = $status === 'all' ? 'all' : $status;
$filename = "event_{$event['id']}_{$filenameTitle}_{$datePart}_rsvps_{$statusPart}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, ['Name', 'Email', 'Batch', 'Company', 'Job Title', 'RSVP Status', 'RSVPed At']);

foreach ($rows as $r) {
	$line = [
		$r['name'],
		$r['email'],
		$r['batch'],
		$r['company'],
		$r['job_title'],
		$r['status'],
		date('Y-m-d H:i:s', strtotime($r['rsvped_at']))
	];
	fputcsv($out, $line);
}

fclose($out);
exit;










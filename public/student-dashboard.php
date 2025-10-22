<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAnyRole(['student', 'alumni']);
$user = Auth::getCurrentUser();
$userId = (int)($user['id'] ?? 0);
$userName = $user['name'] ?? '';
$userBatch = $user['batch'] ?? '';

$notice_count = db()->fetchOne("SELECT COUNT(*) AS c FROM notices WHERE is_active = 1")['c'] ?? 0;
$event_count = db()->fetchOne("SELECT COUNT(*) AS c FROM events WHERE status = 'published' AND event_date > NOW()")['c'] ?? 0;
$job_count = db()->fetchOne("SELECT COUNT(*) AS c FROM job_opportunities WHERE status = 'approved' AND deadline >= CURDATE()")['c'] ?? 0;
$my_pending_surveys = db()->fetchOne("SELECT COUNT(*) AS c FROM surveys WHERE owner_id = ? AND status = 'pending'", [$userId])['c'] ?? 0;
$my_completed_responses = db()->fetchOne("SELECT COUNT(*) AS c FROM survey_responses WHERE user_id = ?", [$userId])['c'] ?? 0;

// My submissions (latest)
$my_news = db()->fetchAll(
    "SELECT id, title, status, created_at, published_at FROM news_posts WHERE author_id = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
$my_events = db()->fetchAll(
    "SELECT id, title, status, event_date, created_at FROM events WHERE created_by = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
$my_jobs = db()->fetchAll(
    "SELECT id, title, company, status, deadline, created_at FROM job_opportunities WHERE posted_by = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
$my_albums = db()->fetchAll(
    "SELECT id, title, access_level, created_at FROM gallery_albums WHERE created_by = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
$my_projects = db()->fetchAll(
    "SELECT id, title, status, approval_status, created_at FROM projects WHERE owner_id = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
$my_surveys = db()->fetchAll(
    "SELECT id, title, status, created_at FROM surveys WHERE owner_id = ? ORDER BY created_at DESC LIMIT 6",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Hero */
        .dash-hero {
            position: relative;
            background:
                radial-gradient(1200px 240px at 90% -20%, rgba(255,255,255,.25), rgba(255,255,255,0)),
                linear-gradient(135deg, #7dccec 0%, #124170 100%);
            color: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 12px 28px rgba(18,65,112,.22);
            overflow: hidden;
        }
        .dash-hero:after {
            content: "";
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,.12);
            filter: blur(4px);
            border-radius: 50%;
        }
        .dash-hero .lead { opacity: .95; }

        /* Quick actions */
        .quick-actions { gap: .75rem !important; }
        .quick-actions .btn {
            display: flex;
            align-items: center;
            gap: .5rem;
            border-radius: .75rem;
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(18,65,112,.08) !important;
            box-shadow: 0 6px 14px rgba(18,65,112,.08);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(18,65,112,.14);
            background: #ffffff;
        }
        .quick-actions i { width: 20px; text-align: center; }

        /* KPI Cards */
        .stat-card .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 12px 28px rgba(18,65,112,.10);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .stat-card .card:hover { transform: translateY(-3px); box-shadow: 0 18px 36px rgba(18,65,112,.16); }
        .stat-card .card-body { padding: 1rem 1rem; }
        .stat-card i {
            width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: .65rem;
            background: linear-gradient(145deg, rgba(18,65,112,.08), rgba(18,65,112,.04));
        }

        /* Cards */
        .card-header { background: #f4fbf7; border-bottom: 1px solid rgba(18,65,112,.08); color: #0f172a; }

        /* Lists */
        .list-group-item { transition: background .18s ease, transform .18s ease; border-color: rgba(18,65,112,.08); }
        .list-group-item:hover { background: #f6faf8; }
        .list-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; text-decoration: none; color: inherit; }
        .list-row .meta { font-size: .85rem; color: #4b5563; }
        .list-row .fw-semibold { color: #0f172a; }
        .chevron { color: #98a6ad; transition: transform .18s ease; }
        .list-group-item:hover .chevron { transform: translateX(2px); }

        /* Click-to-expand sections */
        .section-toggle { cursor: pointer; user-select: none; }
        .section-toggle .header-wrap { display: flex; align-items: center; gap: .5rem; }
        .section-thumb { width: 36px; height: 36px; border-radius: .5rem; display: inline-flex; align-items: center; justify-content: center; background: rgba(18,65,112,.06); }
        .section-title { display: flex; align-items: center; gap: .5rem; font-weight: 700; color: #0f172a; }
        .card-header.section-toggle { color: #0f172a; }
        .card-header.section-toggle .section-title { color: #0b1220 !important; }
        .card-header.section-toggle .collapse-chevron { color: #475569; }
        .card-header.section-toggle .section-thumb i { color: #0b1220; }
        .section-actions { margin-left: auto; }
        .collapse-chevron { color: #98a6ad; transition: transform .18s ease; }
        .collapse.show + .card-body + .card-footer .collapse-chevron,
        .collapse.show + .card-body .collapse-chevron,
        .collapse.show ~ .collapse-chevron { transform: rotate(90deg); }

        /* Empty */
        .empty-state { text-align: center; color: #6c757d; padding: 1rem 0; }
        .empty-state i { display: block; font-size: 28px; margin-bottom: .25rem; opacity: .6; }

        /* Highlights horizontal scroller */
        .highlights { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .highlights-track { display: flex; align-items: stretch; gap: .75rem; padding-bottom: .25rem; }
        .highlight-card { flex: 0 0 auto; width: 240px; border-radius: .75rem; box-shadow: 0 8px 20px rgba(18,65,112,.10); border: 1px solid rgba(18,65,112,.08); background: #fff; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease; }
        .highlight-card:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(18,65,112,.16); }
        .highlight-media { height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #eaf6fb, #f4fbf7); }
        .highlight-media .icon { width: 44px; height: 44px; border-radius: .75rem; display: inline-flex; align-items: center; justify-content: center; background: rgba(18,65,112,.06); }
        .highlight-body { padding: .75rem .85rem; }
        .highlight-title { font-weight: 600; color: #0f172a; margin-bottom: .25rem; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
        .highlight-meta { font-size: .8rem; color: #4b5563; display: flex; align-items: center; gap: .5rem; }
        .highlight-badge { font-size: .7rem; padding: .2rem .45rem; border-radius: 999px; background: #e6f2ff; color: #124170; font-weight: 600; }
        .highlight-link { text-decoration: none; color: inherit; display: block; height: 100%; }

        /* Generic horizontal list cards */
        .hscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .htrack { display: flex; align-items: stretch; gap: .5rem; padding-bottom: .25rem; }
        .mini-card { flex: 0 0 auto; width: 260px; border-radius: .75rem; border: 1px solid rgba(18,65,112,.08); box-shadow: 0 8px 20px rgba(18,65,112,.08); background: #fff; padding: .75rem .9rem; transition: transform .18s ease, box-shadow .18s ease; text-decoration: none; color: inherit; }
        .mini-card:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(18,65,112,.14); }
        .mini-card-title { font-weight: 600; color: #0f172a; margin: 0 0 .25rem 0; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
        .mini-card-meta { font-size: .82rem; color: #4b5563; display: flex; align-items: center; gap: .5rem; }
        .mini-card-badge { font-size: .7rem; padding: .2rem .45rem; border-radius: 999px; background: #e6f2ff; color: #124170; font-weight: 600; }

        /* Responsive */
        @media (max-width: 576px) {
            .dash-hero { padding: 1rem; border-radius: .875rem; }
            .stat-card .card-body { padding: .875rem; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <section>
                <div class="dash-hero mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h2 class="h4 mb-1">Welcome, <?php echo htmlspecialchars($user['name']); ?> 👋</h2>
                            <p class="lead mb-0">Here are your campus updates and your latest activity.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="feed.php" class="btn btn-light btn-sm"><i class="fas fa-stream me-1"></i> Open Feed</a>
                            <a href="message.php" class="btn btn-outline-light btn-sm"><i class="fas fa-comments me-1"></i> Messages</a>
                        </div>
                    </div>
                </div>

                <div class="quick-actions d-grid gap-2 mb-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                    <a href="feed.php" class="btn btn-light border"><i class="fas fa-plus-circle text-primary"></i><span>Create Post</span></a>
                    <a href="projects.php" class="btn btn-light border"><i class="fas fa-rocket text-success"></i><span>New Project</span></a>
                    <a href="gallery.php" class="btn btn-light border"><i class="fas fa-upload text-info"></i><span>Upload Album</span></a>
                    <a href="surveys.php" class="btn btn-light border"><i class="fas fa-poll text-warning"></i><span>Start Survey</span></a>
                    <a href="message.php" class="btn btn-light border"><i class="fas fa-comments text-secondary"></i><span>Open Messages</span></a>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-star"></i> Recent Highlights</strong>
                        <div class="small text-muted">Latest notices, events, jobs, and news</div>
                    </div>
                    <div class="card-body">
                        <?php
                        $high_notices = db()->fetchAll("SELECT id, title, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
                        $high_events = db()->fetchAll("SELECT id, title, event_date FROM events WHERE status='published' AND event_date > NOW() ORDER BY event_date ASC LIMIT 5");
                        $high_jobs   = db()->fetchAll("SELECT id, title, company, created_at FROM job_opportunities WHERE status='approved' AND deadline >= CURDATE() ORDER BY created_at DESC LIMIT 5");
                        $high_news   = db()->fetchAll("SELECT id, title, published_at FROM news_posts WHERE status='published' ORDER BY published_at DESC LIMIT 5");

                        $highlights = [];
                        foreach ($high_notices as $n) {
                            $highlights[] = [
                                'type' => 'notice',
                                'id' => $n['id'],
                                'title' => $n['title'],
                                'meta' => date('M j', strtotime($n['created_at'])),
                                'url' => 'notice.php?id=' . (int)$n['id'],
                            ];
                        }
                        foreach ($high_events as $e) {
                            $highlights[] = [
                                'type' => 'event',
                                'id' => $e['id'],
                                'title' => $e['title'],
                                'meta' => date('M j, g:ia', strtotime($e['event_date'])),
                                'url' => 'event.php?id=' . (int)$e['id'],
                            ];
                        }
                        foreach ($high_jobs as $j) {
                            $highlights[] = [
                                'type' => 'job',
                                'id' => $j['id'],
                                'title' => $j['title'] . ' • ' . $j['company'],
                                'meta' => date('M j', strtotime($j['created_at'])),
                                'url' => 'job.php?id=' . (int)$j['id'],
                            ];
                        }
                        foreach ($high_news as $n) {
                            $highlights[] = [
                                'type' => 'news',
                                'id' => $n['id'],
                                'title' => $n['title'],
                                'meta' => date('M j', strtotime($n['published_at'] ?? 'now')),
                                'url' => 'news.php?id=' . (int)$n['id'],
                            ];
                        }

                        // Sort by recency if dates present in meta are comparable; otherwise keep grouping
                        ?>
                        <?php if (empty($highlights)): ?>
                            <div class="empty-state"><i class="fas fa-star"></i>No recent highlights.</div>
                        <?php else: ?>
                            <div class="highlights">
                                <div class="highlights-track">
                                    <?php foreach ($highlights as $h): ?>
                                        <?php
                                            $iconClass = 'fas fa-bullhorn text-primary';
                                            $badge = 'Notice';
                                            if ($h['type'] === 'event') { $iconClass = 'fas fa-calendar-alt text-success'; $badge = 'Event'; }
                                            if ($h['type'] === 'job') { $iconClass = 'fas fa-briefcase text-warning'; $badge = 'Job'; }
                                            if ($h['type'] === 'news') { $iconClass = 'fas fa-newspaper text-info'; $badge = 'News'; }
                                        ?>
                                        <a class="highlight-link" href="<?php echo htmlspecialchars($h['url']); ?>">
                                            <div class="highlight-card">
                                                <div class="highlight-media">
                                                    <div class="icon"><i class="<?php echo $iconClass; ?>"></i></div>
                                                </div>
                                                <div class="highlight-body">
                                                    <div class="highlight-title"><?php echo htmlspecialchars($h['title']); ?></div>
                                                    <div class="highlight-meta">
                                                        <span class="highlight-badge"><?php echo $badge; ?></span>
                                                        <span><?php echo htmlspecialchars($h['meta']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <strong><i class="fas fa-users"></i> Directory - Your Batch</strong>
                        <div class="small text-muted">People from batch <?php echo htmlspecialchars($userBatch); ?></div>
                    </div>
                    <div class="card-body">
                        <?php
                        $batch_value = $userBatch;
                        $batch_mates = [];
                        if (!empty($batch_value)) {
                            $batch_mates = db()->fetchAll(
                                "SELECT id, name, profile_photo, role FROM users WHERE is_active = 1 AND batch = ? ORDER BY name ASC LIMIT 20",
                                [$batch_value]
                            );
                        }
                        ?>
                        <?php if (empty($batch_mates)): ?>
                            <div class="empty-state"><i class="fas fa-users"></i>No members found in your batch.</div>
                        <?php else: ?>
                            <div class="highlights">
                                <div class="highlights-track">
                                    <?php foreach ($batch_mates as $m): ?>
                                        <a class="highlight-link" href="profile.php?id=<?php echo (int)$m['id']; ?>">
                                            <div class="highlight-card" style="width: 200px;">
                                                <div class="highlight-media" style="height: 140px;">
                                                    <?php if (!empty($m['profile_photo'])): ?>
                                                        <img src="<?php echo htmlspecialchars($m['profile_photo']); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" style="width:100%; height:100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="icon"><i class="fas fa-user text-muted"></i></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="highlight-body">
                                                    <div class="highlight-title" style="min-height:auto; -webkit-line-clamp:1; line-clamp:1;">
                                                        <?php echo htmlspecialchars($m['name']); ?>
                                                    </div>
                                                    <div class="highlight-meta">
                                                        <span class="highlight-badge"><?php echo ucfirst($m['role'] ?? ''); ?></span>
                                                        <span><?php echo htmlspecialchars($batch_value); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                    </div>
                        <?php endif; ?>
                    </div>
                </div>
                

                

                <div class="row g-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <strong><i class="fas fa-folder-open"></i> My Submissions</strong>
                                <div class="small text-muted">Track your pending and published items</div>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-news" type="button" role="tab"><i class="fas fa-newspaper"></i> News</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-events" type="button" role="tab"><i class="fas fa-calendar-alt"></i> Events</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-jobs" type="button" role="tab"><i class="fas fa-briefcase"></i> Jobs</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-gallery" type="button" role="tab"><i class="fas fa-images"></i> Gallery</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-projects" type="button" role="tab"><i class="fas fa-code"></i> Projects</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-surveys" type="button" role="tab"><i class="fas fa-poll"></i> Surveys</button>
                                    </li>
                                </ul>
                                <div class="tab-content pt-3">
                                    <div class="tab-pane fade show active" id="tab-news" role="tabpanel">
                                        <?php if (empty($my_news)): ?>
                                            <div class="empty-state"><i class="fas fa-newspaper"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_news as $n): ?>
                                                    <li class="list-group-item">
                                                        <a class="list-row" href="news.php?id=<?php echo $n['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($n['title']); ?></div>
                                                                <div class="meta">Submitted <?php echo date('M j, Y', strtotime($n['created_at'])); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($n['status']==='published') ? 'bg-primary' : 'bg-success'; ?>"><?php echo ucfirst($n['status']); ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="news.php?mine=1">View all my news</a></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="tab-events" role="tabpanel">
                                        <?php if (empty($my_events)): ?>
                                            <div class="empty-state"><i class="fas fa-calendar-alt"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_events as $e): ?>
                                                    <li class="list-group-item">
                                                        <a class="list-row" href="event.php?id=<?php echo $e['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($e['title']); ?></div>
                                                                <div class="meta">Event <?php echo date('M j, Y g:ia', strtotime($e['event_date'])); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($e['status']==='published') ? 'bg-primary' : 'bg-success'; ?>"><?php echo ucfirst($e['status']); ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="events.php?mine=1">View all my events</a></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="tab-jobs" role="tabpanel">
                                        <?php if (empty($my_jobs)): ?>
                                            <div class="empty-state"><i class="fas fa-briefcase"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_jobs as $j): ?>
                                                    <li class="list-group-item">
                                                        <a class="list-row" href="job.php?id=<?php echo $j['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($j['title']); ?> <span class="text-muted">• <?php echo htmlspecialchars($j['company']); ?></span></div>
                                                                <div class="meta">Deadline <?php echo htmlspecialchars($j['deadline']); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($j['status']==='approved') ? 'bg-primary' : 'bg-success'; ?>"><?php echo ucfirst($j['status']); ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="jobs.php?mine=1">View all my jobs</a></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="tab-gallery" role="tabpanel">
                                        <?php if (empty($my_albums)): ?>
                                            <div class="empty-state"><i class="fas fa-images"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_albums as $a): ?>
                                                    <li class="list-group-item">
                    								<a class="list-row" href="gallery.php?album_id=<?php echo $a['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($a['title']); ?></div>
                                                                <div class="meta">Created <?php echo date('M j, Y', strtotime($a['created_at'])); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($a['access_level']==='public') ? 'bg-primary' : 'bg-success'; ?>"><?php echo ($a['access_level']==='public') ? 'Public' : 'Private'; ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="gallery.php?mine=1">View all my albums</a></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="tab-projects" role="tabpanel">
                                        <?php if (empty($my_projects)): ?>
                                            <div class="empty-state"><i class="fas fa-code"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_projects as $p): ?>
                                                    <li class="list-group-item">
                                                        <a class="list-row" href="project.php?id=<?php echo $p['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($p['title']); ?></div>
                                                                <div class="meta">Submitted <?php echo date('M j, Y', strtotime($p['created_at'])); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($p['status']==='completed') ? 'bg-success' : 'bg-warning'; ?>"><?php echo ucfirst(str_replace('_',' ',$p['status'])); ?></span>
                                                                <span class="badge rounded-pill <?php echo ($p['approval_status']==='published') ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo ucfirst($p['approval_status']); ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="projects.php?mine=1">View all my projects</a></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tab-pane fade" id="tab-surveys" role="tabpanel">
                                        <?php if (empty($my_surveys)): ?>
                                            <div class="empty-state"><i class="fas fa-poll"></i>No submissions yet.</div>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($my_surveys as $s): ?>
                                                    <li class="list-group-item">
                                                        <a class="list-row" href="survey_edit_my.php?id=<?php echo $s['id']; ?>">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($s['title']); ?></div>
                                                                <div class="meta">Created <?php echo date('M j, Y', strtotime($s['created_at'])); ?></div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge rounded-pill <?php echo ($s['status']==='published') ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo ucfirst($s['status']); ?></span>
                                                                <i class="fas fa-chevron-right chevron"></i>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="text-end mt-2"><a class="btn btn-sm btn-outline-primary" href="my_surveys.php">View all my surveys</a></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseNotices" aria-controls="collapseNotices" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-bullhorn text-primary"></i></div>
                                    <div class="section-title">Recent Notices</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="notices.php" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">View all</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseNotices" class="collapse">
                            <div class="card-body">
                                <?php $recent_notices = db()->fetchAll("SELECT id, title, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 10"); ?>
                                <?php if (empty($recent_notices)): ?>
                                    <div class="empty-state"><i class="fas fa-bullhorn"></i>No recent notices.</div>
                                <?php else: ?>
                                    <div class="hscroll"><div class="htrack">
                                        <?php foreach ($recent_notices as $n): ?>
                                            <a class="mini-card" href="notice.php?id=<?php echo (int)$n['id']; ?>">
                                                <div class="mini-card-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                                <div class="mini-card-meta">
                                                    <span class="mini-card-badge">Notice</span>
                                                    <span><?php echo date('M j', strtotime($n['created_at'])); ?></span>
                                                    </div>
                                                </a>
                                        <?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseEvents" aria-controls="collapseEvents" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-calendar-alt text-success"></i></div>
                                    <div class="section-title">Upcoming Events</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="events.php" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">View all</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseEvents" class="collapse">
                            <div class="card-body">
                                <?php $upcoming_events = db()->fetchAll("SELECT id, title, event_date, venue FROM events WHERE status='published' AND event_date > NOW() ORDER BY event_date ASC LIMIT 10"); ?>
                                <?php if (empty($upcoming_events)): ?>
                                    <div class="empty-state"><i class="fas fa-calendar-alt"></i>No upcoming events.</div>
                                <?php else: ?>
                                    <div class="hscroll"><div class="htrack">
                                        <?php foreach ($upcoming_events as $e): ?>
                                            <a class="mini-card" href="event.php?id=<?php echo (int)$e['id']; ?>">
                                                <div class="mini-card-title"><?php echo htmlspecialchars($e['title']); ?><?php if ($e['venue']): ?><span class="text-muted"> • <?php echo htmlspecialchars($e['venue']); ?></span><?php endif; ?></div>
                                                <div class="mini-card-meta">
                                                    <span class="mini-card-badge">Event</span>
                                                    <span><?php echo date('M j, g:ia', strtotime($e['event_date'])); ?></span>
                                                    </div>
                                                </a>
                                        <?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseJobs" aria-controls="collapseJobs" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-briefcase text-warning"></i></div>
                                    <div class="section-title">New Jobs</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="jobs.php" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">View all</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseJobs" class="collapse">
                            <div class="card-body">
                                <?php $new_jobs = db()->fetchAll("SELECT id, title, company, created_at FROM job_opportunities WHERE status='approved' AND deadline >= CURDATE() ORDER BY created_at DESC LIMIT 10"); ?>
                                <?php if (empty($new_jobs)): ?>
                                    <div class="empty-state"><i class="fas fa-briefcase"></i>No new jobs.</div>
                                <?php else: ?>
                                    <div class="hscroll"><div class="htrack">
                                        <?php foreach ($new_jobs as $j): ?>
                                            <a class="mini-card" href="job.php?id=<?php echo (int)$j['id']; ?>">
                                                <div class="mini-card-title"><?php echo htmlspecialchars($j['title']); ?> <span class="text-muted">• <?php echo htmlspecialchars($j['company']); ?></span></div>
                                                <div class="mini-card-meta">
                                                    <span class="mini-card-badge">Job</span>
                                                    <span><?php echo date('M j', strtotime($j['created_at'])); ?></span>
                                                    </div>
                                                </a>
                                        <?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseFeed" aria-controls="collapseFeed" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-stream text-info"></i></div>
                                    <div class="section-title">Recent Feed</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="feed.php" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">Open feed</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseFeed" class="collapse">
                            <div class="card-body">
                                <?php $recent_feed = db()->fetchAll("SELECT fp.id, u.name, fp.content, fp.created_at FROM feed_posts fp JOIN users u ON fp.author_id = u.id ORDER BY fp.created_at DESC LIMIT 10"); ?>
                                <?php if (empty($recent_feed)): ?>
                                    <div class="empty-state"><i class="fas fa-stream"></i>No recent updates.</div>
                                <?php else: ?>
                                    <div class="hscroll"><div class="htrack">
                                        <?php foreach ($recent_feed as $f): ?>
                                            <a class="mini-card" href="feed.php#post-<?php echo (int)$f['id']; ?>">
                                                <div class="mini-card-title" style="min-height:auto; -webkit-line-clamp:3; line-clamp:3;">
                                                    <?php echo htmlspecialchars(mb_strimwidth($f['content'], 0, 140, '...')); ?>
                                                </div>
                                                <div class="mini-card-meta">
                                                    <span class="mini-card-badge">Feed</span>
                                                    <span><?php echo htmlspecialchars($f['name']); ?> • <?php echo date('M j, g:ia', strtotime($f['created_at'])); ?></span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div></div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseGallery" aria-controls="collapseGallery" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-images text-info"></i></div>
                                    <div class="section-title">Recent Gallery</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="gallery.php" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">Open gallery</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseGallery" class="collapse">
                                <div class="card-body">
                                <?php $recent_albums = db()->fetchAll(
                                    "SELECT a.id, a.title, a.created_at,
                                        (SELECT gm.file_path FROM gallery_media gm 
                                         WHERE gm.album_id = a.id AND gm.file_type IN ('image','video') 
                                         ORDER BY gm.id ASC LIMIT 1) AS cover_path
                                     FROM gallery_albums a
                                     WHERE a.access_level = 'public'
                                     ORDER BY a.created_at DESC LIMIT 12"
                                ); ?>
                                <?php if (empty($recent_albums)): ?>
                                    <div class="empty-state"><i class="fas fa-images"></i>No recent albums.</div>
                                <?php else: ?>
                                    <div class="highlights">
                                        <div class="highlights-track">
                                            <?php foreach ($recent_albums as $a): ?>
                                                <a class="highlight-link" href="album.php?id=<?php echo (int)$a['id']; ?>">
                                                    <div class="highlight-card" style="width: 200px;">
                                                        <div class="highlight-media" style="height: 140px;">
                                                            <?php if (!empty($a['cover_path'])): ?>
                                                                <img src="<?php echo htmlspecialchars($a['cover_path']); ?>" alt="<?php echo htmlspecialchars($a['title']); ?>" style="width:100%; height:100%; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="icon"><i class="fas fa-image text-muted"></i></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="highlight-body">
                                                            <div class="highlight-title" style="min-height:auto; -webkit-line-clamp:1; line-clamp:1;">
                                                                <?php echo htmlspecialchars($a['title']); ?>
                                                            </div>
                                                            <div class="highlight-meta">
                                                                <span class="highlight-badge">Album</span>
                                                                <span><?php echo date('M j', strtotime($a['created_at'])); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireRole('admin');
$user = Auth::getCurrentUser();

// Get dashboard statistics
$total_users = db()->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role IN ('student', 'alumni')")['c'] ?? 0;
$total_staff = db()->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role = 'staff'")['c'] ?? 0;
$pending_staff = db()->fetchOne("SELECT COUNT(*) AS c FROM users WHERE role = 'staff' AND is_active = 0")['c'] ?? 0;
$pending_jobs = db()->fetchOne("SELECT COUNT(*) AS c FROM job_opportunities WHERE status = 'pending'")['c'] ?? 0;
$total_notices = db()->fetchOne("SELECT COUNT(*) AS c FROM notices WHERE is_active = 1")['c'] ?? 0;
$total_events = db()->fetchOne("SELECT COUNT(*) AS c FROM events WHERE status = 'published'")['c'] ?? 0;
$surveys_pending = db()->fetchOne("SELECT COUNT(*) AS c FROM surveys WHERE status = 'pending'")['c'] ?? 0;
$surveys_published = db()->fetchOne("SELECT COUNT(*) AS c FROM surveys WHERE status = 'published'")['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Hero Section */
        .dash-hero {
            position: relative;
            background:
                radial-gradient(1200px 240px at 90% -20%, rgba(255,255,255,.25), rgba(255,255,255,0)),
                linear-gradient(135deg, #dc2626 0%, #7c2d12 100%);
            color: #fff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 12px 28px rgba(220,38,38,.22);
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
            border: 1px solid rgba(220,38,38,.08) !important;
            box-shadow: 0 6px 14px rgba(220,38,38,.08);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(220,38,38,.14);
            background: #ffffff;
        }
        .quick-actions i { width: 20px; text-align: center; }

        /* KPI Cards */
        .stat-card .card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 12px 28px rgba(220,38,38,.10);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .stat-card .card:hover { transform: translateY(-3px); box-shadow: 0 18px 36px rgba(220,38,38,.16); }
        .stat-card .card-body { padding: 1rem 1rem; }
        .stat-card i {
            width: 40px; height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: .65rem;
            background: linear-gradient(145deg, rgba(220,38,38,.08), rgba(220,38,38,.04));
        }

        /* Cards */
        .card-header { background: #fef2f2; border-bottom: 1px solid rgba(220,38,38,.08); color: #0f172a; }

        /* Lists */
        .list-group-item { transition: background .18s ease, transform .18s ease; border-color: rgba(220,38,38,.08); }
        .list-group-item:hover { background: #fef7f7; }
        .list-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; text-decoration: none; color: inherit; }
        .list-row .meta { font-size: .85rem; color: #4b5563; }
        .list-row .fw-semibold { color: #0f172a; }
        .chevron { color: #98a6ad; transition: transform .18s ease; }
        .list-group-item:hover .chevron { transform: translateX(2px); }

        /* Click-to-expand sections */
        .section-toggle { cursor: pointer; user-select: none; }
        .section-toggle .header-wrap { display: flex; align-items: center; gap: .5rem; }
        .section-thumb { width: 36px; height: 36px; border-radius: .5rem; display: inline-flex; align-items: center; justify-content: center; background: rgba(220,38,38,.06); }
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
        .highlight-card { flex: 0 0 auto; width: 240px; border-radius: .75rem; box-shadow: 0 8px 20px rgba(220,38,38,.10); border: 1px solid rgba(220,38,38,.08); background: #fff; overflow: hidden; transition: transform .18s ease, box-shadow .18s ease; }
        .highlight-card:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(220,38,38,.16); }
        .highlight-media { height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #fef2f2, #fef7f7); }
        .highlight-media .icon { width: 44px; height: 44px; border-radius: .75rem; display: inline-flex; align-items: center; justify-content: center; background: rgba(220,38,38,.06); }
        .highlight-body { padding: .75rem .85rem; }
        .highlight-title { font-weight: 600; color: #0f172a; margin-bottom: .25rem; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
        .highlight-meta { font-size: .8rem; color: #4b5563; display: flex; align-items: center; gap: .5rem; }
        .highlight-badge { font-size: .7rem; padding: .2rem .45rem; border-radius: 999px; background: #fef2f2; color: #dc2626; font-weight: 600; }
        .highlight-link { text-decoration: none; color: inherit; display: block; height: 100%; }

        /* Generic horizontal list cards */
        .hscroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .htrack { display: flex; align-items: stretch; gap: .5rem; padding-bottom: .25rem; }
        .mini-card { flex: 0 0 auto; width: 260px; border-radius: .75rem; border: 1px solid rgba(220,38,38,.08); box-shadow: 0 8px 20px rgba(220,38,38,.08); background: #fff; padding: .75rem .9rem; transition: transform .18s ease, box-shadow .18s ease; text-decoration: none; color: inherit; }
        .mini-card:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(220,38,38,.14); }
        .mini-card-title { font-weight: 600; color: #0f172a; margin: 0 0 .25rem 0; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
        .mini-card-meta { font-size: .82rem; color: #4b5563; display: flex; align-items: center; gap: .5rem; }
        .mini-card-badge { font-size: .7rem; padding: .2rem .45rem; border-radius: 999px; background: #fef2f2; color: #dc2626; font-weight: 600; }

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
                            <h2 class="h4 mb-1">Admin Dashboard 👑</h2>
                            <p class="lead mb-0">Welcome back, <?php echo htmlspecialchars($user['name']); ?>! Manage your alumni portal system.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin/user_management.php" class="btn btn-light btn-sm"><i class="fas fa-users me-1"></i> Manage Users</a>
                            <a href="analytics.php" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar me-1"></i> Analytics</a>
                        </div>
                    </div>
                </div>

                <div class="quick-actions d-grid gap-2 mb-4" style="grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));">
                    <a href="admin/user_management.php" class="btn btn-light border"><i class="fas fa-users text-primary"></i><span>Manage Users</span></a>
                    <a href="notices.php" class="btn btn-light border"><i class="fas fa-bullhorn text-success"></i><span>Post Notice</span></a>
                    <a href="news.php" class="btn btn-light border"><i class="fas fa-newspaper text-info"></i><span>Post News</span></a>
                    <a href="jobs.php" class="btn btn-light border"><i class="fas fa-briefcase text-warning"></i><span>Manage Jobs</span></a>
                    <a href="events.php" class="btn btn-light border"><i class="fas fa-calendar-alt text-danger"></i><span>Create Event</span></a>
                    <a href="analytics.php" class="btn btn-light border"><i class="fas fa-chart-bar text-dark"></i><span>Analytics</span></a>
                    <a href="excel-viewer.php" class="btn btn-light border"><i class="fas fa-file-excel text-success"></i><span>Excel Data</span></a>
                    <a href="aiassistant-admin.php" class="btn btn-light border"><i class="fas fa-robot text-secondary"></i><span>AI Assistant</span></a>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-users text-primary"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Total Users</div>
                                        <div class="h4 mb-0"><?php echo $total_users; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-tie text-secondary"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Staff Members</div>
                                        <div class="h4 mb-0"><?php echo $total_staff; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-clock text-warning"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Pending Staff</div>
                                        <div class="h4 mb-0"><?php echo $pending_staff; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-briefcase text-info"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Pending Jobs</div>
                                        <div class="h4 mb-0"><?php echo $pending_jobs; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-bullhorn text-success"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Active Notices</div>
                                        <div class="h4 mb-0"><?php echo $total_notices; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-calendar-alt text-danger"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Published Events</div>
                                        <div class="h4 mb-0"><?php echo $total_events; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-poll text-success"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Pending Surveys</div>
                                        <div class="h4 mb-0"><?php echo $surveys_pending; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 stat-card">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chart-pie text-info"></i>
                                    <div class="ms-3">
                                        <div class="text-muted small">Published Surveys</div>
                                        <div class="h4 mb-0"><?php echo $surveys_published; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card h-100" tabindex="0">
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseRecentActivity" aria-controls="collapseRecentActivity" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-clock text-primary"></i></div>
                                    <div class="section-title">Recent Activity</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <a href="admin/" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();">View all</a>
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseRecentActivity" class="collapse">
                                <div class="card-body">
                                    <?php 
                                    $recent_users = db()->fetchAll("SELECT id, name, role, created_at FROM users ORDER BY created_at DESC LIMIT 8");
                                    $recent_notices = db()->fetchAll("SELECT id, title, created_at FROM notices WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5");
                                    ?>
                                    <?php if (empty($recent_users) && empty($recent_notices)): ?>
                                        <div class="empty-state"><i class="fas fa-clock"></i>No recent activity.</div>
                                    <?php else: ?>
                                        <div class="hscroll"><div class="htrack">
                                            <?php foreach ($recent_users as $u): ?>
                                                <a class="mini-card" href="profile.php?id=<?php echo (int)$u['id']; ?>">
                                                    <div class="mini-card-title"><?php echo htmlspecialchars($u['name']); ?> <span class="text-muted">• <?php echo ucfirst($u['role']); ?></span></div>
                                                    <div class="mini-card-meta">
                                                        <span class="mini-card-badge">User</span>
                                                        <span><?php echo date('M j', strtotime($u['created_at'])); ?></span>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
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
                            <div class="card-header section-toggle" data-bs-toggle="collapse" data-bs-target="#collapseSystemInfo" aria-controls="collapseSystemInfo" aria-expanded="false">
                                <div class="header-wrap">
                                    <div class="section-thumb"><i class="fas fa-info-circle text-info"></i></div>
                                    <div class="section-title">System Information</div>
                                    <div class="section-actions ms-auto d-flex align-items-center gap-2">
                                        <i class="fas fa-chevron-right collapse-chevron"></i>
                                    </div>
                                </div>
                            </div>
                            <div id="collapseSystemInfo" class="collapse">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-center p-3">
                                                <div class="h5 mb-1"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-muted small">Administrator</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-center p-3">
                                                <div class="h6 mb-1">Last Login</div>
                                                <div class="text-muted small"><?php echo date('M j, Y', strtotime($user['updated_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">System Status</span>
                                            <span class="badge bg-success">Online</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Database</span>
                                            <span class="badge bg-success">Connected</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small text-muted">Admin Access</span>
                                            <span class="badge bg-danger">Full</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Integration Management Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plug me-2"></i>
                                    Integration Management
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-4">Manage OAuth providers, email settings, and security integrations</p>
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100 border-0 bg-light">
                                            <div class="card-body text-center">
                                                <i class="fab fa-linkedin fa-2x text-primary mb-3"></i>
                                                <h6>LinkedIn OAuth</h6>
                                                <p class="small text-muted">Social login integration</p>
                                                <a href="admin/integrations.php" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-cog me-1"></i>
                                                    Configure
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100 border-0 bg-light">
                                            <div class="card-body text-center">
                                                <i class="fab fa-google fa-2x text-danger mb-3"></i>
                                                <h6>Google OAuth</h6>
                                                <p class="small text-muted">Google sign-in integration</p>
                                                <a href="admin/integrations.php" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-cog me-1"></i>
                                                    Configure
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100 border-0 bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-envelope fa-2x text-info mb-3"></i>
                                                <h6>Email SMTP</h6>
                                                <p class="small text-muted">Email notification settings</p>
                                                <a href="admin/integrations.php" class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-cog me-1"></i>
                                                    Configure
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <div class="card h-100 border-0 bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                                                <h6>reCAPTCHA</h6>
                                                <p class="small text-muted">Security verification</p>
                                                <a href="admin/integrations.php" class="btn btn-outline-warning btn-sm">
                                                    <i class="fas fa-cog me-1"></i>
                                                    Configure
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-4 mb-2">
                                        <a href="admin/test_integrations.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-flask me-2"></i>
                                            Test All Integrations
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <a href="admin/integration_guide.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-book me-2"></i>
                                            Setup Guide
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <a href="test_oauth_flow.php" class="btn btn-outline-success w-100" target="_blank">
                                            <i class="fas fa-play me-2"></i>
                                            Live OAuth Test
                                        </a>
                                    </div>
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
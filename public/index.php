<?php
/**
 * Alumni Portal - Homepage
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = Auth::getCurrentUser();

// Redirect authenticated users to their appropriate dashboard
if ($user) {
    switch ($user['role']) {
        case 'admin':
            header('Location: home-admin.php');
            exit;
        case 'staff':
            header('Location: home-staff.php');
            exit;
        case 'student':
        case 'alumni':
            header('Location: home-student.php');
            exit;
    }
}

// Get recent notices for public view
$recent_notices = db()->fetchAll(
    "SELECT n.*, u.name as posted_by_name 
     FROM notices n 
     JOIN users u ON n.posted_by = u.id 
     WHERE n.is_active = 1 AND (n.expiry_date IS NULL OR n.expiry_date >= CURDATE())
     ORDER BY n.created_at DESC 
     LIMIT 5"
);

// Get recent news posts for public view
$recent_news = db()->fetchAll(
    "SELECT np.*, u.name as author_name 
     FROM news_posts np 
     JOIN users u ON np.author_id = u.id 
     WHERE np.status = 'published' AND np.published_at IS NOT NULL
     ORDER BY np.published_at DESC 
     LIMIT 6"
);

// Get upcoming events for public view
$upcoming_events = db()->fetchAll(
    "SELECT e.*, COUNT(er.id) as rsvp_count
     FROM events e 
     LEFT JOIN event_rsvps er ON e.id = er.event_id AND er.status = 'attending'
     WHERE e.status = 'published' AND e.event_date > NOW()
     GROUP BY e.id
     ORDER BY e.event_date ASC 
     LIMIT 5"
);

// Get recent job opportunities for public view
$recent_jobs = db()->fetchAll(
    "SELECT jo.*, COUNT(ja.id) as applications_count
     FROM job_opportunities jo 
     LEFT JOIN job_applications ja ON jo.id = ja.job_id
     WHERE jo.status = 'approved' AND jo.deadline >= CURDATE()
     GROUP BY jo.id
     ORDER BY jo.created_at DESC 
     LIMIT 6"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <!-- Hero Section -->
                <div class="hero-section bg-primary text-white rounded-3 p-5 mb-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h1 class="display-4 fw-bold">Welcome to Alumni Portal</h1>
                            <p class="lead">Connect with fellow alumni, stay updated with campus news, explore job opportunities, and build lasting professional relationships.</p>
                            <?php if (!$user): ?>
                                <a href="login.php" class="btn btn-light btn-lg me-3">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </a>
                                <a href="register.php" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                            <?php else: ?>
                                <p class="mb-0">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-4 text-center">
                            <i class="fas fa-graduation-cap" style="font-size: 8rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user): ?>
        <!-- Dashboard Content -->
        <div class="row">
            <!-- Recent Notices -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn text-primary"></i> Recent Notices
                        </h5>
                        <a href="notices.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_notices)): ?>
                            <p class="text-muted">No notices available.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_notices as $notice): ?>
                                    <div class="list-group-item px-0">
                                        <h6 class="mb-1">
                                            <a href="notice.php?id=<?php echo $notice['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($notice['title']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            By <?php echo htmlspecialchars($notice['posted_by_name']); ?> • 
                                            <?php echo date('M j, Y', strtotime($notice['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt text-success"></i> Upcoming Events
                        </h5>
                        <a href="events.php" class="btn btn-sm btn-outline-success">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_events)): ?>
                            <p class="text-muted">No upcoming events.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i> 
                                                    <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                                                </small>
                                                <?php if ($event['venue']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-primary"><?php echo $event['rsvp_count']; ?> attending</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent News -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-newspaper text-info"></i> Latest News
                        </h5>
                        <a href="news.php" class="btn btn-sm btn-outline-info">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_news)): ?>
                            <p class="text-muted">No news posts available.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($recent_news as $news): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-0">
                                            <?php if ($news['media_urls']): ?>
                                                <?php $media = json_decode($news['media_urls'], true); ?>
                                                <?php if (!empty($media[0])): ?>
                                                    <img src="<?php echo htmlspecialchars($media[0]); ?>" class="card-img-top" style="height: 120px; object-fit: cover;">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <div class="card-body p-2">
                                                <h6 class="card-title">
                                                    <a href="news.php?id=<?php echo $news['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars(substr($news['title'], 0, 50)) . (strlen($news['title']) > 50 ? '...' : ''); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">
                                                    By <?php echo htmlspecialchars($news['author_name']); ?> • 
                                                    <?php echo date('M j', strtotime($news['published_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Job Opportunities -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase text-warning"></i> Job Opportunities
                        </h5>
                        <a href="jobs.php" class="btn btn-sm btn-outline-warning">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_jobs)): ?>
                            <p class="text-muted">No job opportunities available.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_jobs as $job): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="job.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($job['title']); ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company']); ?>
                                                </small>
                                                <?php if ($job['location']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($job['user_applied']): ?>
                                                    <span class="badge bg-success">Applied</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary"><?php echo $job['applications_count']; ?> applications</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Public Content for Non-Authenticated Users -->
        <div class="row">
            <!-- Login Options -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-in-alt text-primary"></i> Login Options
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="login.php" class="btn btn-primary">
                                <i class="fas fa-user"></i> Student/Alumni Login
                            </a>
                            <a href="staff-login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-tie"></i> Staff Login
                            </a>
                            <a href="admin-login.php" class="btn btn-outline-danger">
                                <i class="fas fa-shield-alt"></i> Admin Login
                            </a>
                        </div>
                        <hr>
                        <div class="text-center">
                            <p class="text-muted mb-2">Don't have an account?</p>
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus"></i> Register as Student/Alumni
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Notices -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn text-primary"></i> Recent Notices
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_notices)): ?>
                            <p class="text-muted">No notices available.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_notices as $notice): ?>
                                    <div class="list-group-item px-0">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($notice['title']); ?></h6>
                                        <small class="text-muted">
                                            Posted by <?php echo htmlspecialchars($notice['posted_by_name']); ?>
                                            • <?php echo date('M j, Y', strtotime($notice['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt text-success"></i> Upcoming Events
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_events)): ?>
                            <p class="text-muted">No upcoming events.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="list-group-item px-0">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                                            <?php if ($event['venue']): ?>
                                                <br><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star text-warning"></i> Platform Features
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <i class="fas fa-users text-primary fa-3x mb-2"></i>
                                <h6>Connect</h6>
                                <small class="text-muted">Network with fellow alumni and students</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <i class="fas fa-briefcase text-success fa-3x mb-2"></i>
                                <h6>Job Opportunities</h6>
                                <small class="text-muted">Find and apply for job openings</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <i class="fas fa-calendar-alt text-info fa-3x mb-2"></i>
                                <h6>Events</h6>
                                <small class="text-muted">Stay updated with campus events</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <i class="fas fa-newspaper text-warning fa-3x mb-2"></i>
                                <h6>News & Updates</h6>
                                <small class="text-muted">Get the latest campus news</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

$user = Auth::getCurrentUser();

$surveys = db()->fetchAll(
    "SELECT s.id, s.title, s.description, s.status, s.visibility, s.owner_id, s.created_at, u.name AS owner_name
     FROM surveys s
     LEFT JOIN users u ON u.id = s.owner_id
     WHERE s.status IN ('published','approved')
     ORDER BY s.created_at DESC",
    []
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveys - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0"><i class="fas fa-poll"></i> Surveys</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="my_surveys.php"><i class="fas fa-folder-open"></i> My Surveys</a>
                <?php if (Auth::hasAnyRole(['admin','staff'])): ?>
                    <a class="btn btn-primary" href="admin/survey_edit.php"><i class="fas fa-plus"></i> Create Survey</a>
                    <a class="btn btn-outline-primary" href="admin/surveys.php"><i class="fas fa-sliders-h"></i> Manage</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="survey_edit_my.php"><i class="fas fa-plus"></i> Create Survey</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!Auth::hasAnyRole(['admin','staff'])): ?>
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                Surveys created by students/alumni require staff approval before publishing.
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <?php if (empty($surveys)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center text-muted">No surveys available.</div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($surveys as $s): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($s['title']); ?></h5>
                                <div class="text-muted small mb-2">By <?php echo htmlspecialchars($s['owner_name'] ?: 'Unknown'); ?> · <?php echo date('M j, Y', strtotime($s['created_at'])); ?></div>
                                <?php if (!empty($s['description'])): ?>
                                <p class="card-text"><?php echo htmlspecialchars(mb_strimwidth($s['description'], 0, 120, '...')); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <span class="badge bg-success"><?php echo ucfirst($s['status']); ?></span>
                                <a class="btn btn-sm btn-primary" href="survey_take.php?id=<?php echo $s['id']; ?>"><i class="fas fa-play"></i> Take Survey</a>
                                <?php if (Auth::hasAnyRole(['admin','staff']) || $s['owner_id'] === $user['id']): ?>
                                    <a class="btn btn-sm btn-outline-info" href="survey_analytics.php?id=<?php echo $s['id']; ?>"><i class="fas fa-chart-pie"></i></a>
                                    <?php if (Auth::hasAnyRole(['admin','staff'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="admin/survey_edit.php?id=<?php echo $s['id']; ?>"><i class="fas fa-edit"></i></a>
                                    <?php else: ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="survey_edit_my.php?id=<?php echo $s['id']; ?>"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



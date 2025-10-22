<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAnyRole(['student','alumni','staff','admin']);
$user = Auth::getCurrentUser();

// Handle delete (own only)
if (isset($_GET['do']) && $_GET['do'] === 'delete') {
    $id = $_GET['id'] ?? '';
    if ($id) {
        try {
            $s = db()->fetchOne("SELECT id, owner_id, status FROM surveys WHERE id = ?", [$id]);
            if ($s && $s['owner_id'] === $user['id']) {
                db()->execute("DELETE FROM surveys WHERE id = ?", [$id]);
                header('Location: my_surveys.php?msg=deleted');
                exit;
            }
        } catch (Exception $e) { /* ignore */ }
    }
}

$mine = db()->fetchAll("SELECT id, title, status, created_at FROM surveys WHERE owner_id = ? ORDER BY created_at DESC", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Surveys - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1><i class="fas fa-poll"></i> My Surveys</h1>
            <a class="btn btn-primary" href="survey_edit_my.php"><i class="fas fa-plus"></i> New Survey</a>
        </div>

        <?php if (!empty($_GET['msg']) && $_GET['msg']==='deleted'): ?><div class="alert alert-success">Survey deleted.</div><?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Title</th><th>Status</th><th>Created</th><th></th></tr></thead>
                    <tbody>
                        <?php if (empty($mine)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No surveys yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mine as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['title']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($s['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="survey_edit_my.php?id=<?php echo $s['id']; ?>"><i class="fas fa-edit"></i></a>
                                        <a class="btn btn-sm btn-outline-info" href="survey_analytics.php?id=<?php echo $s['id']; ?>"><i class="fas fa-chart-pie"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="my_surveys.php?do=delete&id=<?php echo $s['id']; ?>" onclick="return confirm('Delete this survey?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>



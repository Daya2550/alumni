<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAnyRole(['admin','staff']);

$user = Auth::getCurrentUser();

// Handle delete
if (isset($_GET['do']) && $_GET['do'] === 'delete') {
    $id = $_GET['id'] ?? '';
    if ($id) {
        try {
            db()->execute("DELETE FROM surveys WHERE id = ?", [$id]);
            header('Location: surveys.php?msg=deleted');
            exit;
        } catch (Exception $e) {
            $error = 'Failed to delete survey.';
        }
    }
}

// Filters
$status = sanitizeInput($_GET['status'] ?? '');
$owner  = sanitizeInput($_GET['owner'] ?? '');

$where = [];
$params = [];
if ($status) { $where[] = 's.status = ?'; $params[] = $status; }
if ($owner)  { $where[] = 's.owner_id = ?'; $params[] = $owner; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$surveys = db()->fetchAll("\nSELECT s.id, s.title, s.status, s.requires_approval, s.visibility, s.owner_id, s.created_at, u.name AS owner_name\nFROM surveys s\nLEFT JOIN users u ON u.id = s.owner_id\n$whereSql\nORDER BY s.created_at DESC\n", $params);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveys - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1><i class="fas fa-poll"></i> Surveys</h1>
            <a class="btn btn-primary" href="survey_edit.php"><i class="fas fa-plus"></i> New Survey</a>
        </div>

        <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">Survey deleted.</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <form class="row g-2" method="GET">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All</option>
                            <?php foreach (['draft','pending','approved','published','closed'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $status===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Owner ID</label>
                        <input type="text" class="form-control" name="owner" value="<?php echo htmlspecialchars($owner); ?>" placeholder="Filter by owner id">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-secondary w-100" type="submit"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($surveys)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No surveys found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($surveys as $s): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['title']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($s['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($s['visibility']); ?></td>
                                    <td><?php echo htmlspecialchars($s['owner_name'] ?: $s['owner_id']); ?></td>
                                    <td><?php echo htmlspecialchars($s['created_at']); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="survey_edit.php?id=<?php echo $s['id']; ?>"><i class="fas fa-edit"></i></a>
                                        <a class="btn btn-sm btn-outline-info" href="../survey_analytics.php?id=<?php echo $s['id']; ?>"><i class="fas fa-chart-pie"></i></a>
                                        <a class="btn btn-sm btn-outline-danger" href="surveys.php?do=delete&id=<?php echo $s['id']; ?>" onclick="return confirm('Delete this survey?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>


